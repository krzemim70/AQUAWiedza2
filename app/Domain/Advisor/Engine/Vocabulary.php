<?php

namespace App\Domain\Advisor\Engine;

/** Słowniki i plan pytań kreatora (kolejność, treści, skale ocen). Etykiety muszą być zgodne z plikami bazy wiedzy. */
final class Vocabulary
{
    public const UNKNOWN = 'Nie wiem';

    public const ANY = 'Bez znaczenia';

    public const FIT = 'Dopasuj do zbiornika (zalecane)';

    public const WILDCARD = 'Wszystkie';

    public const NANO_TYPE = 'Nano / Krewetkarium';

    public const KINDS = ['Ryba', 'Skorupiak', 'Ślimak'];

    public const VERIFY_QUESTIONS = [
        'WielkośćAkwarium' => ['volume', 'Jaki litraż ma Twój zbiornik?'],
        'TypAkwarium' => ['type', 'Jaki typ akwarium chcesz potwierdzić dla tego rekordu?'],
        'pH' => ['ph', 'Jaki odczyn wody ma lub ma mieć Twój zbiornik?'],
        'Temperatura' => ['temperature', 'Jaki zakres temperatury chcesz utrzymać?'],
        'CO2' => ['co2', 'Jakie podejście do CO2 bierzesz pod uwagę?'],
    ];

    /**
     * Kolumny pytane przy weryfikacji rekordu danego obszaru, w kolejności.
     *
     * @return list<string>
     */
    public static function verifyPlan(GuideArea $area): array
    {
        return match ($area) {
            GuideArea::Fauna => ['WielkośćAkwarium', 'TypAkwarium', 'pH', 'Temperatura'],
            GuideArea::Flora => ['WielkośćAkwarium', 'TypAkwarium', 'CO2'],
            GuideArea::Equipment => ['WielkośćAkwarium', 'TypAkwarium'],
            GuideArea::Care => [],
        };
    }

    /** @return list<string> */
    public static function scale(string $column): array
    {
        return match ($column) {
            'pH' => array_map(fn (PhBand $b) => $b->label(), PhBand::cases()),
            'Temperatura' => array_map(fn (TemperatureBand $b) => $b->label(), TemperatureBand::cases()),
            'CO2' => ['Zbędny', 'Wskazany', 'Niezbędny'],
            default => [],
        };
    }

    /**
     * Kolumny obszaru w kolejności planu: klucz krótki, etykieta, pytanie, rodzaj oceny (`ordinal` 3/2/1/0 po skali `order`,
     * `exact` 3/0, `binary` 3/2/0), kolejność opcji, czy ma „Nie wiem”, czy lista jest stała, czy „Nie wiem” brzmi „Bez znaczenia”.
     *
     * @return array<string, array{key: string, label: string, question: string, score: string, order: list<string>, optional: bool, fixed: bool, any: bool}>
     */
    public static function columns(GuideArea $area): array
    {
        $c = fn (string $key, string $label, string $question, string $score = 'exact', array $order = [], bool $optional = false, bool $fixed = false, bool $any = false) => compact('key', 'label', 'question', 'score', 'order', 'optional', 'fixed', 'any');
        $marka = $c('brand', 'Marka', 'Czy interesuje Cię określony poziom marki?', 'binary', ['Standard', 'Premium'], true);
        $maker = $c('maker', 'Producent', 'Czy chcesz zawęzić wybór do konkretnego producenta?', 'binary', [], true);
        $faunaRegions = ['Afryka', 'Ameryka Płn. i Środk.', 'Ameryka Południowa', 'Azja', 'Inne'];
        $floraRegions = ['Afryka', 'Ameryka Południowa', 'Ameryka Płn. i Środk.', 'Azja', 'Inne'];

        return match ($area) {
            GuideArea::Fauna => [
                'Typ' => $c('kind', 'Typ', 'Czego szukasz: ryb, krewetek czy ślimaków?', 'exact', self::KINDS),
                'Wielkość' => $c('size', 'Wielkość', 'Jakiej wielkości mają być dorosłe osobniki?', 'ordinal', array_map(fn (FishBand $b) => $b->label(), FishBand::cases())),
                'Strefa' => $c('zone', 'Strefa', 'W której części akwarium mają przebywać najczęściej?', 'exact', ['Dolna', 'Środkowa', 'Górna'], true),
                'Temperament' => $c('temperament', 'Temperament', 'Jaki temperament ma mieć obsada?', 'ordinal', ['Spokojny', 'Umiarkowany', 'Agresywny/Terytorialny'], true),
                'Temperatura' => $c('temperature', 'Temperatura', 'Jaki zakres temperatury bierzesz pod uwagę?', 'ordinal', self::scale('Temperatura'), true),
                'pH' => $c('ph', 'pH', 'Jaki odczyn wody bierzesz pod uwagę?', 'ordinal', self::scale('pH'), true),
                'Region' => $c('region', 'Region', 'Z jakiego regionu świata mają pochodzić?', 'exact', $faunaRegions, true, false, true),
            ],
            GuideArea::Flora => [
                'Wielkość' => $c('size', 'Wielkość', 'Jakiej docelowej wielkości roślin szukasz?', 'ordinal', ['B. mała (< 5 cm)', 'Niska (5-10 cm)', 'Średnia (10-30 cm)', 'Wysoka (>30 cm)'], false, true),
                'Strefa' => $c('zone', 'Strefa', 'Gdzie roślina ma być umieszczona w aranżacji?', 'exact', ['Przód', 'Środek', 'Tło', 'Na dekoracje', 'Pływające'], true, true),
                'CO2' => $c('co2', 'CO2', 'Jakie podejście do CO2 bierzesz pod uwagę?', 'ordinal', self::scale('CO2'), true, true),
                'Kolor' => $c('color', 'Kolor', 'Jaka kolorystyka roślin jest Ci bliższa?', 'exact', ['Ciemnozielony', 'Jasnozielony', 'Czerwony/Brązowy'], true, true),
                'Region' => $c('region', 'Region', 'Z jakiego regionu świata mają pochodzić rośliny?', 'exact', $floraRegions, true, true, true),
            ],
            GuideArea::Equipment => [
                'kategoria_glowna' => $c('category', 'Kategoria', 'Której grupy sprzętu szukasz?', 'exact', ['Filtracja', 'Oświetlenie LED', 'Ogrzewanie', 'Dozowanie CO2']),
                'rodzaj' => $c('kind', 'Rodzaj', 'Jakiego rodzaju sprzętu szukasz w tej grupie?', 'exact', ['Filtr zewnętrzny', 'Filtr wewnętrzny', 'Filtr kaskadowy', 'Grzałka wewnętrzna', 'Grzałka przepływowa', 'Belka LED', 'Zestaw LED', 'Zestaw CO2']),
                'Marka' => $marka,
                'producent' => $maker,
            ],
            GuideArea::Care => [
                'kategoria_zadania' => $c('category', 'Kategoria', 'Jakiej grupy pielęgnacji szukasz?', 'exact', ['Start / przygotowanie', 'Pielęgnacja na co dzień', 'Zapobieganie i kontrola']),
                'rodzaj_zadania' => $c('action', 'Rodzaj zadania', 'Jaki rodzaj działania chcesz doprecyzować?'),
                'obszar' => $c('use', 'Obszar', 'Którego zastosowania szukasz w tej grupie?'),
                'Marka' => $marka,
                'producent' => $maker,
            ],
        };
    }

    /**
     * Kolumny planu dla stanu odpowiedzi (fauna: ścieżka zależy od wybranego typu).
     *
     * @return list<string>
     */
    public static function plan(GuideArea $area, ?string $kind): array
    {
        if ($area !== GuideArea::Fauna) {
            return array_keys(self::columns($area));
        }

        return match ($kind) {
            'Ryba' => ['Wielkość', 'Strefa', 'Temperament', 'Temperatura', 'pH', 'Region'],
            'Skorupiak', 'Ślimak' => ['Wielkość', 'pH', 'Region'],
            default => ['Typ'],
        };
    }

    /**
     * Kolumny, których odpowiedź zawęża pulę (reszta tylko punktuje).
     *
     * @return list<string>
     */
    public static function hard(GuideArea $area): array
    {
        return match ($area) {
            GuideArea::Fauna => ['Typ'],
            GuideArea::Equipment => ['kategoria_glowna', 'rodzaj'],
            GuideArea::Care => ['kategoria_zadania', 'rodzaj_zadania', 'obszar'],
            GuideArea::Flora => [],
        };
    }

    /** Kolumna katalogu dla klucza pytania (`fauna.zone`, `verify.ph`). */
    public static function column(string $key): ?string
    {
        [$scope, $short] = array_pad(explode('.', $key, 2), 2, '');
        if ($scope === 'verify') {
            return array_find_key(self::VERIFY_QUESTIONS, fn (array $q) => $q[0] === $short);
        }
        $area = GuideArea::tryFrom($scope);

        return $area === null ? null : array_find_key(self::columns($area), fn (array $spec) => $spec['key'] === $short);
    }

    /** Stabilny kod opcji z etykiety (małe litery, bez polskich znaków, myślniki). */
    public static function code(string $label): string
    {
        $ascii = strtr(mb_strtolower($label), ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);

        return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    }

    /**
     * Opcje z etykiet (kody unikalne w liście: powtórzony kod dostaje numer).
     *
     * @param  list<string>  $labels
     * @return list<Option>
     */
    public static function options(array $labels): array
    {
        $special = [self::UNKNOWN => 'unknown', self::ANY => 'unknown', self::FIT => 'fit'];
        $options = [];
        $used = [];
        foreach ($labels as $label) {
            $code = $special[$label] ?? self::code($label);
            $base = $code;
            for ($n = 2; isset($used[$code]); $n++) {
                $code = "$base-$n";
            }
            $used[$code] = true;
            $options[] = new Option($code, $label);
        }

        return $options;
    }

    /**
     * Wartości w naturalnej kolejności, z wartościami z `$preferred` na początku (w jego kolejności).
     *
     * @param  list<string>  $values
     * @param  list<string>  $preferred
     * @return list<string>
     */
    public static function order(array $values, array $preferred): array
    {
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);
        $rank = fn (string $v) => ($i = array_search($v, $preferred, true)) === false ? PHP_INT_MAX : $i;
        usort($values, fn (string $a, string $b) => $rank($a) <=> $rank($b));

        return $values;
    }
}
