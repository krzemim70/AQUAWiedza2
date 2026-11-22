<?php

namespace App\Domain\KnowledgeBase\Contracts;

use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Support\Str;

/** Odczyt aktywnego katalogu dla innych modułów. Bez aktywnej partii zwraca puste wyniki. */
class Catalog
{
    public function __construct(private Settings $settings) {}

    /**
     * Wszystkie rekordy obszaru z aktywnej partii w kolejności pliku (bez opisów i zdjęć).
     *
     * @return list<CatalogEntry>
     */
    public function snapshot(Area $area): array
    {
        return Entry::query()->inActiveBatch()->with('record')
            ->whereHas('record', fn ($q) => $q->where('area', $area->value))
            ->orderBy('position')->get()
            ->map(fn (Entry $e) => $this->dto($e))->all();
    }

    public function find(Area $area, string $id): ?CatalogEntry
    {
        $entry = Entry::query()->inActiveBatch()->with(['record', 'description', 'photos' => fn ($q) => $q->where('verified', true)])
            ->whereHas('record', fn ($q) => $q->where('area', $area->value)->where('legacy_id', $id))
            ->first();

        return $entry === null ? null : $this->dto($entry, true);
    }

    /**
     * Wyszukiwanie po nazwie (polskiej, łacińskiej, produktu), bez rozróżniania wielkości liter i polskich znaków.
     *
     * @param  list<Area>|null  $areas
     * @return list<CatalogEntry>
     */
    public function search(string $phrase, ?array $areas = null): array
    {
        $needle = self::fold($phrase);
        if (mb_strlen($needle) < (int) $this->settings->get(SettingKey::SearchMinChars)) {
            return [];
        }

        return Entry::query()->inActiveBatch()->with('record')
            ->where('search_text', 'like', '%'.addcslashes($needle, '%_\\').'%')
            ->when($areas !== null, fn ($q) => $q->whereHas('record', fn ($r) => $r->whereIn('area', array_map(fn (Area $a) => $a->value, $areas ?? []))))
            ->orderBy('id')->limit((int) $this->settings->get(SettingKey::SearchMaxHits))->get()
            ->map(fn (Entry $e) => $this->dto($e))->all();
    }

    /** Klucz wyszukiwania: małe litery bez znaków diakrytycznych (ł → l). */
    public static function fold(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    private function dto(Entry $entry, bool $full = false): CatalogEntry
    {
        return new CatalogEntry(
            $entry->record->area, $entry->record->legacy_id, $entry->label(), $entry->name_latin ?: null,
            $entry->fields, $entry->tokens,
            $full ? $entry->description?->body : null,
            $full ? $entry->photos->map(fn ($p) => ['url' => $p->url, 'author' => $p->author, 'licence' => $p->licence])->all() : [],
        );
    }
}
