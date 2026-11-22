<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\ImportResult;
use App\Domain\KnowledgeBase\Data\Package;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Description;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RegistryEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Sprawdza paczkę, zapisuje ją jako nową partię (pending) i aktywuje atomowo.
 * Ten sam skrót paczki = brak zmian. Paczka z błędami zostawia tylko wpis `failed` z raportem.
 */
class ImportPackage
{
    public function __construct(private ValidatePackage $validate, private ActivateBatch $activate) {}

    public function handle(string $path, ?User $by = null): ImportResult
    {
        if ($by !== null) {
            Gate::forUser($by)->authorize('manage-knowledge');
        }

        $package = $this->validate->handle($path);

        if (! $package->ok()) {
            $batch = Batch::create([
                'state' => Batch::FAILED, 'files' => $package->files, 'counts' => $package->counts(),
                'errors' => $package->errors, 'created_by' => $by?->id,
            ]);

            return new ImportResult(ImportResult::FAILED, $batch, $package->errors);
        }

        $existing = Batch::where('checksum', $package->checksum)->first();
        if ($existing !== null) {
            return new ImportResult(ImportResult::UNCHANGED, $existing);
        }

        $batch = DB::transaction(fn () => $this->store($package, $by));
        $this->activate->handle($batch, $by);

        return new ImportResult(ImportResult::IMPORTED, $batch->refresh());
    }

    private function store(Package $package, ?User $by): Batch
    {
        $batch = Batch::create([
            'state' => Batch::PENDING, 'checksum' => $package->checksum, 'files' => $package->files,
            'counts' => $package->counts(), 'errors' => [], 'created_by' => $by?->id,
        ]);
        $now = now();

        // rejestr tylko rośnie; wycofany identyfikator zostaje wycofany
        $retired = RegistryEntry::query()->where('status', RegistryEntry::RETIRED)->pluck('legacy_id')->flip();
        $registry = array_filter($package->registry, fn ($r) => ! $retired->has($r['id']));
        RegistryEntry::upsert(
            array_map(fn ($r) => ['area' => $r['area'], 'legacy_id' => $r['id'], 'status' => $r['status'], 'source_status' => $r['source_status'], 'name' => $r['name'], 'created_at' => $now, 'updated_at' => $now], array_values($registry)),
            ['area', 'legacy_id'],
            ['status', 'source_status', 'name', 'updated_at'],
        );

        $recordRows = [];
        foreach (Area::cases() as $area) {
            foreach ($package->rows[$area->value] as $row) {
                $recordRows[] = ['area' => $area->value, 'legacy_id' => $row['id'], 'created_at' => $now, 'updated_at' => $now];
            }
        }
        Record::upsert($recordRows, ['area', 'legacy_id'], ['updated_at']);
        $recordIds = Record::query()->get(['id', 'area', 'legacy_id'])->mapWithKeys(fn (Record $r) => [$r->area->value.'|'.$r->legacy_id => $r->id]);

        $entries = [];
        foreach (Area::cases() as $area) {
            foreach ($package->rows[$area->value] as $position => $row) {
                $entries[] = $this->entry($area, $row) + [
                    'batch_id' => $batch->id, 'record_id' => $recordIds[$area->value.'|'.$row['id']],
                    'position' => $position + 1, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        foreach (array_chunk($entries, 100) as $chunk) {
            DB::table('catalog_entries')->insert($chunk);
        }

        $this->storeDescriptions($package, $recordIds->all(), $now);

        activity()->performedOn($batch)->causedBy($by)->withProperties(['counts' => $package->counts()])->log('catalog batch stored');

        return $batch;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function entry(Area $area, array $row): array
    {
        $names = $area->nameColumns();
        $skip = [...array_filter($names), ...$area->freeTextColumns(), 'id'];
        $tokens = [];
        foreach ($row as $header => $value) {
            if (! in_array($header, $skip, true)) {
                $tokens[$header] = array_values(array_filter(array_map('trim', explode(';', $value)), fn ($t) => $t !== ''));
            }
        }

        return [
            'name_pl' => $names['name_pl'] ? $row[$names['name_pl']] : null,
            'name_latin' => $names['name_latin'] ? $row[$names['name_latin']] : null,
            'product_name' => $names['product_name'] ? $row[$names['product_name']] : null,
            'fields' => json_encode($row, JSON_UNESCAPED_UNICODE),
            'tokens' => json_encode($tokens, JSON_UNESCAPED_UNICODE),
            'search_text' => Catalog::fold(implode(' ', array_map(fn ($h) => $row[$h], $area->searchColumns()))),
        ];
    }

    /**
     * Opisy i metadane zdjęć z importu; opis zredagowany ręcznie zostaje bez zmian.
     *
     * @param  array<string, int>  $recordIds
     */
    private function storeDescriptions(Package $package, array $recordIds, mixed $now): void
    {
        $edited = Description::query()->whereNotNull('edited_at')->pluck('record_id')->flip();
        $photos = Photo::query()->get(['record_id', 'url'])->mapWithKeys(fn (Photo $p) => [$p->record_id.'|'.$p->url => true]);

        $descriptions = [];
        $newPhotos = [];
        foreach ($package->descriptions as $area => $items) {
            foreach ($items as $id => $item) {
                $recordId = $recordIds["$area|$id"];
                if ($item['opis'] !== '' && ! $edited->has($recordId)) {
                    $descriptions[] = ['record_id' => $recordId, 'body' => $item['opis'], 'source_ref' => $item['zrodlo_opisu'] ?? null, 'created_at' => $now, 'updated_at' => $now];
                }
                $url = $item['zdjecie'] ?? '';
                if ($url !== '' && ! isset($photos["$recordId|$url"])) {
                    $newPhotos[] = [
                        'record_id' => $recordId, 'url' => $url, 'source_url' => ($item['zdjecie_zrodlo_url'] ?? '') ?: null,
                        'author' => ($item['zdjecie_autor'] ?? '') ?: null, 'licence' => ($item['zdjecie_licencja'] ?? '') ?: null,
                        'verified' => false, 'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
        }
        foreach (array_chunk($descriptions, 50) as $chunk) {
            Description::upsert($chunk, ['record_id'], ['body', 'source_ref', 'updated_at']);
        }
        Photo::insert($newPhotos);
    }
}
