<?php

namespace App\Domain\Shops\Jobs;

use App\Domain\Shops\Actions\ApplyOfferImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessOfferImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $batchId) {}

    public function handle(ApplyOfferImport $apply): void
    {
        if ($apply->handle($this->batchId, 100)) {
            self::dispatch($this->batchId)->afterCommit();
        }
    }

    public function failed(): void
    {
        app(ApplyOfferImport::class)->fail($this->batchId, 'Przerwano zapis importu. Sprawdź raport przed ponownym wgraniem pliku.');
    }
}
