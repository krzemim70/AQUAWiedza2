<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Data\Package;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\RegistryEntry;
use JsonException;

/**
 * Czyta katalog z plikami (`baza_*.txt.php`, `opisy_*.json.php`, `rejestr_id.json.php`, `rekordy_przyszle.json.php`)
 * i sprawdza całą paczkę, zanim cokolwiek trafi do bazy. Błędy mają postać `plik: opis`.
 */
class ValidatePackage
{
    public const REGISTRY_FILE = 'rejestr_id.json.php';

    public const FUTURE_FILE = 'rekordy_przyszle.json.php';

    private const STATUSES = [
        'aktywny' => RegistryEntry::ACTIVE,
        'przyszły' => RegistryEntry::FUTURE,
        'zarezerwowany-bez-danych' => RegistryEntry::FUTURE,
        'wstrzymany' => RegistryEntry::RETIRED,
    ];

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, array{sha256: string, rows: int|null}> */
    private array $files = [];

    public function handle(string $path): Package
    {
        $this->errors = [];
        $this->files = [];

        $rows = [];
        foreach (Area::cases() as $area) {
            $rows[$area->value] = $this->tsv($path, $area);
        }

        $descriptions = [];
        foreach (Area::cases() as $area) {
            if ($area->descriptionsFile() !== null) {
                $descriptions[$area->value] = $this->descriptions($path, $area, $rows[$area->value]);
            }
        }

        $registry = $this->registry($path, $rows);

        return new Package($this->files, $this->checksum(), $rows, $descriptions, $registry, $this->errors);
    }

    /** Skrót paczki = skróty plików w stałej kolejności; tylko dla poprawnej paczki. */
    private function checksum(): ?string
    {
        if ($this->errors !== []) {
            return null;
        }
        ksort($this->files);

        return hash('sha256', implode("\n", array_map(fn ($name, $f) => "$name:{$f['sha256']}", array_keys($this->files), $this->files)));
    }

    /** @return list<array<string, string>> */
    private function tsv(string $path, Area $area): array
    {
        $name = $area->file();
        $content = $this->read($path, $name);
        if ($content === null) {
            return [];
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $headers = fgetcsv($stream, 0, "\t", '"', '\\') ?: [];
        $headers = array_map(fn ($h) => trim((string) $h), $headers);
        if ($headers !== $area->headers()) {
            $this->fail($name, 'nagłówki nie zgadzają się z oczekiwanymi: '.implode(' | ', $area->headers()));
            fclose($stream);

            return [];
        }

        $rows = [];
        $seen = [];
        $line = 1;
        while (($data = fgetcsv($stream, 0, "\t", '"', '\\')) !== false) {
            $line++;
            if (count($data) === 1 && trim((string) $data[0]) === '') {
                continue;
            }
            if (count($data) !== count($headers)) {
                $this->fail($name, 'wiersz '.$line.': '.count($data).' pól zamiast '.count($headers));

                continue;
            }
            $row = array_combine($headers, array_map(fn ($v) => trim((string) $v), $data));
            $id = $row['id'];
            if ($id === '') {
                $this->fail($name, "wiersz $line: pusty identyfikator");
            } elseif (! str_starts_with($id, $area->prefix())) {
                $this->fail($name, "wiersz $line: identyfikator $id bez prefiksu {$area->prefix()}");
            } elseif (isset($seen[$id])) {
                $this->fail($name, "wiersz $line: zduplikowany identyfikator $id");
            } else {
                $seen[$id] = true;
                $rows[] = $row;
            }
        }
        fclose($stream);

        if ($rows === []) {
            $this->fail($name, 'brak rekordów');
        }
        $this->files[$name]['rows'] = count($rows);

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, array<string, string>>
     */
    private function descriptions(string $path, Area $area, array $rows): array
    {
        $name = (string) $area->descriptionsFile();
        $items = $this->json($path, $name);
        $known = array_flip(array_column($rows, 'id'));
        $result = [];
        foreach ($items ?? [] as $i => $item) {
            $id = is_array($item) ? trim((string) ($item['id'] ?? '')) : '';
            if (! isset($known[$id])) {
                $this->fail($name, 'pozycja '.($i + 1).": opis osieroconego rekordu '$id'");
            } elseif (isset($result[$id])) {
                $this->fail($name, "zduplikowany opis $id");
            } elseif (! is_string($item['opis'] ?? null)) {
                $this->fail($name, "opis $id: brak treści");
            } else {
                $result[$id] = array_map(fn ($v) => is_string($v) ? trim($v) : '', $item);
            }
        }
        if ($items !== null) {
            $this->files[$name]['rows'] = count($result);
        }

        return $result;
    }

    /**
     * @param  array<string, list<array<string, string>>>  $rows
     * @return list<array{area: string, id: string, status: string, source_status: string, name: string|null}>
     */
    private function registry(string $path, array $rows): array
    {
        $data = $this->json($path, self::REGISTRY_FILE);
        $future = $this->json($path, self::FUTURE_FILE);

        $registry = [];
        foreach ($data['rekordy'] ?? [] as $i => $item) {
            $id = trim((string) ($item['id'] ?? ''));
            $source = (string) ($item['status'] ?? '');
            $area = collect([Area::Fauna, Area::Flora])->first(fn (Area $a) => str_starts_with($id, $a->prefix()));
            if ($area === null) {
                $this->fail(self::REGISTRY_FILE, 'pozycja '.($i + 1).": nieznany obszar identyfikatora '$id'");
            } elseif (! isset(self::STATUSES[$source])) {
                $this->fail(self::REGISTRY_FILE, "$id: nieznany status '$source'");
            } elseif (isset($registry[$id])) {
                $this->fail(self::REGISTRY_FILE, "zduplikowany identyfikator $id");
            } else {
                $registry[$id] = ['area' => $area->value, 'id' => $id, 'status' => self::STATUSES[$source], 'source_status' => $source, 'name' => null];
            }
        }
        if (is_array($data)) {
            $this->files[self::REGISTRY_FILE]['rows'] = count($registry);
        }

        foreach ($future ?? [] as $i => $item) {
            $id = is_array($item) ? trim((string) ($item['id'] ?? '')) : '';
            $entry = $registry[$id] ?? null;
            if ($entry === null) {
                $this->fail(self::FUTURE_FILE, 'pozycja '.($i + 1).": '$id' nie ma w rejestrze");
            } elseif ($entry['status'] === RegistryEntry::ACTIVE) {
                $this->fail(self::FUTURE_FILE, "$id jest w rejestrze jako aktywny");
            } elseif (($item['obszar'] ?? null) !== $entry['area']) {
                $this->fail(self::FUTURE_FILE, "$id: obszar niezgodny z rejestrem");
            } else {
                $registry[$id]['name'] = trim((string) ($item['nazwa'] ?? '')) ?: null;
            }
        }
        if ($future !== null) {
            $this->files[self::FUTURE_FILE]['rows'] = count($future);
        }

        $retired = RegistryEntry::query()->where('status', RegistryEntry::RETIRED)->pluck('legacy_id')->flip();
        foreach (Area::cases() as $area) {
            $ids = array_column($rows[$area->value], 'id');
            foreach ($ids as $id) {
                $status = $registry[$id]['status'] ?? null;
                if ($retired->has($id)) {
                    $this->fail($area->file(), "identyfikator $id został wycofany i nie wraca do puli");
                } elseif ($area->descriptionsFile() !== null && $status !== RegistryEntry::ACTIVE) {
                    $this->fail($area->file(), "identyfikator $id nie jest aktywny w rejestrze");
                }
            }
            if ($area->descriptionsFile() !== null && $ids !== []) {
                $active = count(array_filter($registry, fn ($r) => $r['area'] === $area->value && $r['status'] === RegistryEntry::ACTIVE));
                if ($active !== count($ids)) {
                    $this->fail($area->file(), 'liczba rekordów ('.count($ids).") różni się od aktywnych w rejestrze ($active)");
                }
            }
        }

        // sprzęt i pielęgnacja nie mają rejestru w plikach: klucze z TSV rejestrujemy przy zapisie
        foreach ([Area::Equipment, Area::Care] as $area) {
            foreach ($rows[$area->value] as $row) {
                $registry[$row['id']] = ['area' => $area->value, 'id' => $row['id'], 'status' => RegistryEntry::ACTIVE, 'source_status' => 'tsv', 'name' => null];
            }
        }

        return array_values($registry);
    }

    private function read(string $path, string $name): ?string
    {
        $file = rtrim($path, '/\\').DIRECTORY_SEPARATOR.$name;
        $raw = is_file($file) ? @file_get_contents($file) : false;
        if ($raw === false) {
            $this->fail($name, 'brak pliku lub brak odczytu');

            return null;
        }
        $this->files[$name] = ['sha256' => hash('sha256', $raw), 'rows' => null];
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $this->fail($name, 'plik nie jest poprawnym UTF-8');

            return null;
        }

        // opcjonalny BOM, strażnik (exit w znacznikach PHP) i ewentualny BOM przy pierwszej linii danych
        $raw = preg_replace('/^(?:\xEF\xBB\xBF)?(?:<\?php exit; \?>\R)?(?:\xEF\xBB\xBF)?/', '', $raw);

        return (string) $raw;
    }

    /** @return array<mixed>|null */
    private function json(string $path, string $name): ?array
    {
        $content = $this->read($path, $name);
        if ($content === null) {
            return null;
        }
        try {
            $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->fail($name, 'niepoprawny JSON: '.$e->getMessage());

            return null;
        }
        if (! is_array($data)) {
            $this->fail($name, 'oczekiwano tablicy lub obiektu');

            return null;
        }

        return $data;
    }

    private function fail(string $file, string $message): void
    {
        $this->errors[] = "$file: $message";
    }
}
