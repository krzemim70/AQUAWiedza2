<?php

namespace App\Domain\KnowledgeBase\Contracts;

use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Odczyt aktywnego katalogu dla innych modułów. Bez aktywnej partii zwraca puste wyniki. */
class Catalog
{
    public function __construct(private Settings $settings) {}

    /** Data aktywacji aktywnej partii (null, gdy nie ma żadnej). */
    public function activatedAt(): ?CarbonInterface
    {
        return Batch::query()->active()->first(['activated_at'])?->activated_at;
    }

    /** Id aktywnej partii (null, gdy nie ma żadnej). */
    public function activeBatchId(): ?int
    {
        return Batch::query()->active()->value('id');
    }

    /**
     * Rekordy obszaru z aktywnej (lub wskazanej) partii w kolejności pliku, bez zdjęć.
     *
     * @param  list<string>|null  $ids
     * @return list<CatalogEntry>
     */
    public function snapshot(Area $area, ?int $batch = null, ?array $ids = null, bool $withDescriptions = false): array
    {
        return Entry::query()->inBatch($batch)->with('record')
            ->whereHas('record', fn ($q) => $q->where('area', $area->value)->when($ids !== null, fn ($q) => $q->whereIn('legacy_id', $ids ?? [])))
            ->when($withDescriptions, fn ($q) => $q->with('description'))
            ->orderBy('position')->get()
            ->map(fn (Entry $e) => $this->dto($e, withDescription: $withDescriptions))->all();
    }

    /** Liczba rekordów obszaru w aktywnej partii. */
    public function count(Area $area): int
    {
        return Entry::query()->inActiveBatch()->whereHas('record', fn ($q) => $q->where('area', $area->value))->count();
    }

    public function find(Area $area, string $id, ?int $batch = null): ?CatalogEntry
    {
        $entry = $this->records($area, [$id], $batch)
            ->with(['record', 'description', 'photos' => fn ($q) => $q->where('verified', true)])
            ->first();

        return $entry === null ? null : $this->dto($entry, true);
    }

    /**
     * Nazwy rekordów obszaru obecnych w aktywnej partii (klucz: id). Brakujące id są pominięte.
     *
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    public function labels(Area $area, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return $this->records($area, $ids)->with('record')
            ->get()->mapWithKeys(fn (Entry $e) => [$e->record->legacy_id => $e->label()])->all();
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string> adresy zweryfikowanych zdjęć po ID rekordu
     */
    public function thumbnails(Area $area, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $thumbnails = [];
        foreach ($this->records($area, $ids)->select(['id', 'record_id'])
            ->with(['record', 'photos' => fn ($q) => $q->where('verified', true)->whereNotNull('url')->where('url', '!=', '')])
            ->get() as $entry) {
            $url = $entry->photos->first()?->url;
            if ($url !== null) {
                $thumbnails[$entry->record->legacy_id] = $url;
            }
        }

        return $thumbnails;
    }

    /**
     * @param  list<string>  $ids
     * @return Builder<Entry>
     */
    private function records(Area $area, array $ids, ?int $batch = null): Builder
    {
        return Entry::query()->inBatch($batch)
            ->whereHas('record', fn ($q) => $q->where('area', $area->value)->whereIn('legacy_id', $ids));
    }

    /**
     * Wyszukiwanie po nazwie (polskiej, łacińskiej, produktu) lub pełnym ID rekordu, bez rozróżniania wielkości liter
     * i polskich znaków.
     *
     * @param  list<Area>|null  $areas
     * @return list<CatalogEntry>
     */
    public function search(string $phrase, ?array $areas = null, ?int $batch = null): array
    {
        $needle = self::fold($phrase);
        if (mb_strlen($needle) < (int) $this->settings->get(SettingKey::SearchMinChars)) {
            return [];
        }

        return Entry::query()->inBatch($batch)->with('record')
            ->where(fn ($q) => $q->where('search_text', 'like', '%'.addcslashes($needle, '%_\\').'%')
                ->orWhereHas('record', fn ($r) => $r->whereRaw('lower(legacy_id) = ?', [trim($needle)])))
            ->when($areas !== null, fn ($q) => $q->whereHas('record', fn ($r) => $r->whereIn('area', array_map(fn (Area $a) => $a->value, $areas ?? []))))
            ->orderBy('id')->limit((int) $this->settings->get(SettingKey::SearchMaxHits))->get()
            ->map(fn (Entry $e) => $this->dto($e))->all();
    }

    /** Klucz wyszukiwania: małe litery bez znaków diakrytycznych (ł → l). */
    public static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function dto(Entry $entry, bool $full = false, bool $withDescription = false): CatalogEntry
    {
        return new CatalogEntry(
            $entry->record->area, $entry->record->legacy_id, $entry->label(), $entry->name_latin ?: null,
            $entry->fields, $entry->tokens,
            $full || $withDescription ? $entry->description?->body : null,
            $full ? $entry->photos->map(fn ($p) => ['url' => $p->url, 'author' => $p->author, 'licence' => $p->licence])->all() : [],
        );
    }
}
