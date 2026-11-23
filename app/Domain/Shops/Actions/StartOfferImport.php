<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Access;
use App\Domain\Shops\Jobs\ProcessOfferImport;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class StartOfferImport
{
    public function __construct(private Access $access, private ApplyOfferImport $apply) {}

    public function handle(User $actor, Shop $shop, int $id, bool $confirmed): void
    {
        $this->access->authorizeMember($actor, $shop->id);
        if (! $confirmed) {
            throw ValidationException::withMessages(['confirmed' => 'Potwierdź, że dostępne ilości zgadzają się ze stanem fizycznym.']);
        }

        $start = DB::transaction(function () use ($actor, $shop, $id) {
            $batch = ImportBatch::forActor($actor, $shop)->lockForUpdate()->findOrFail($id);
            if ($batch->state !== 'preview') {
                return false;
            }
            if ($batch->approved === []) {
                throw ValidationException::withMessages(['confirmed' => 'W tym pliku brak poprawnych wierszy do zapisu.']);
            }

            $batch->update(['state' => 'queued', 'counts' => ['added' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => $batch->counts['errors']]]);

            return true;
        });

        if ($start) {
            try {
                ProcessOfferImport::dispatch($id)->afterCommit();
            } catch (Throwable $e) {
                $this->apply->fail($id, 'Nie udało się uruchomić importu.');
                report($e);
            }
        }
    }
}
