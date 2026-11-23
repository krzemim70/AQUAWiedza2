<?php

namespace App\Domain\Settings;

use Closure;

/**
 * Definicje ustawień w kodzie: wartość domyślna (PoC), reguły walidacji (klucz `value` + zagnieżdżone), etykieta i grupa.
 * Pominięte (brak zatwierdzonej wartości): notifications.retry_policy, advisor.ruleset,
 * advisor.session.ttl_seconds.
 */
enum SettingKey: string
{
    case FishroomPrice = 'fishroom.price_grosze';
    case FishroomCurrency = 'fishroom.currency';
    case FishroomPeriod = 'fishroom.period';
    case DropFreeSelections = 'drop.free_selections';
    case DropCookieDays = 'drop.cookie_days';
    case DropIpEnabled = 'drop.ip_enabled';
    case DropIpLimit = 'drop.ip_limit';
    case DropIpDays = 'drop.ip_days';
    case DropAttemptRate = 'drop.attempt_rate';
    case TankLimit = 'aquarium.tank_limit';
    case EmailVerificationRequired = 'accounts.email_verification_required';
    case RegistrationTimer = 'identity.registration.timer_seconds';
    case RegistrationRate = 'identity.registration.rate';
    case ActivationLifetime = 'identity.activation.lifetime_seconds';
    case PendingCleanupBatch = 'identity.pending.cleanup_batch';
    case PendingRetentionDays = 'identity.pending.retention_days';
    case ExportIntervalMinutes = 'identity.export.interval_minutes';
    case PasswordMinLength = 'identity.password.min_length';
    case ConsentVersion = 'identity.consents.version';
    case TankVolume = 'aquarium.tanks.volume_l';
    case ReminderHorizon = 'aquarium.reminders.horizon_days';
    case ReminderInterval = 'aquarium.reminders.interval_days';
    case WarningRanges = 'aquarium.measurements.warning_ranges';
    case RedfieldMaxInput = 'aquarium.redfield.max_input_mg_l';
    case RedfieldZoneEdges = 'aquarium.redfield.zone_edges';
    case SearchMinChars = 'advisor.search.min_chars';
    case SearchMaxHits = 'advisor.search.max_hits';
    case SearchDebounce = 'advisor.search.debounce_ms';
    case FitThresholds = 'advisor.ranking.fit_thresholds';
    case OutputPolicy = 'advisor.ranking.output_policy';
    case VerificationThresholds = 'advisor.verification.thresholds';
    case VerificationCorrections = 'advisor.verification.correction_rules';
    case GuidedAdvisorForTank = 'advisor.guided.tank_access';
    case TrackingDedupWindow = 'tracking.dedup_window_seconds';
    case TrackingRate = 'tracking.rate';
    case MetricsSampleTime = 'metrics.sample_time';
    case MetricsRollupTime = 'metrics.rollup_time';
    case MetricsTimezone = 'metrics.business_timezone';
    case ShopPublication = 'shops.publication.thresholds';
    case ShopAverageRounding = 'shops.publication.average_rounding';
    case ShopTiers = 'shops.plan.tiers';
    case ShopCommission = 'shops.commission.rates';
    case ShopCommissionEnabled = 'shops.commission.enabled';
    case ShopMinPrice = 'shops.offer.min_price_grosze';
    case ShopFreshness = 'shops.offer.freshness';
    case ShopLinkCheck = 'shops.offer.link_check';
    case ShopLeads = 'shops.leads.guest';
    case ShopCsvImport = 'shops.import.csv';
    case ShopLogo = 'shops.profile.logo';
    case ShopViews = 'shops.views.retention';
    case ShopActivation = 'shops.activation';
    case ShopOfferBounds = 'shops.offer.bounds';
    case ShopOrders = 'shops.orders';
    case CompatSeverity = 'aquarium.compat.severity';
    case CompatTemperament = 'aquarium.compat.temperament';
    case StockingCmPerLiter = 'aquarium.stocking.cm_per_liter';
    case CalculatorLimits = 'aquarium.calculators.limits';
    case CalculatorCo2 = 'aquarium.calculators.co2';
    case PlanLimit = 'aquarium.plan.limit';
    case RemindersAuto = 'aquarium.reminders.auto';
    case WaterHintsMap = 'advisor.water_hints.map';
    case WaterHintsChecks = 'advisor.water_hints.checks';
    case WaterHintsTolerance = 'advisor.water_hints.tolerance';
    case ShopWatch = 'shops.watch';
    case ShopReviews = 'shops.reviews';
    case ShopPromo = 'shops.promo';
    case CampaignMinDiscount = 'campaigns.default_min_discount';
    case DemandMetrics = 'metrics.demand';

    public function default(): mixed
    {
        return $this->definition()[0];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->definition()[1];
    }

    public function label(): string
    {
        return $this->definition()[2];
    }

    public function group(): string
    {
        return $this->definition()[3];
    }

    /** @return array{mixed, array<string, mixed>, string, string} */
    private function definition(): array
    {
        $access = 'Konta i dostęp';
        $aquarium = 'Akwarium';
        $advisor = 'Doradca';
        $shops = 'Sklepy';
        $tracking = 'Tracking i metryki';
        $bool = ['value' => ['present', 'boolean']];
        $rate = fn () => self::obj(['max' => self::int(), 'window_seconds' => self::int()]);
        $ranges = ['ph' => [0, 14], 'temp_c' => [10, 40], 'kh' => [0, 50], 'gh' => [0, 50], 'no3' => [0, 500], 'po4' => [0, 10]];

        $warning = ['value' => ['required', 'array']];
        foreach ($ranges as $name => $_) {
            $warning["value.$name.min"] = ['required', 'numeric', 'min:0', 'max:1000'];
            $warning["value.$name.max"] = ['required', 'numeric', 'min:0', 'max:1000', "gte:value.$name.min"];
        }

        return match ($this) {
            self::FishroomPrice => [1900, ['value' => self::int()], 'Fishroom: cena (grosze)', $access],
            self::FishroomCurrency => ['PLN', ['value' => ['required', 'string', 'size:3']], 'Fishroom: waluta', $access],
            self::FishroomPeriod => [
                ['count' => 1, 'unit' => 'month'],
                self::obj(['count' => self::int(), 'unit' => ['required', 'in:day,month']]),
                'Fishroom: domyślny okres nadania (liczba, day/month)', $access,
            ],
            self::DropFreeSelections => [1, ['value' => self::int(0, 1000)], 'Drop: liczba bezpłatnych doborów gościa', $access],
            self::DropCookieDays => [30, ['value' => self::int(1, 3650)], 'Drop: okno bezpłatnych doborów w cookie (dni)', $access],
            self::DropIpLimit => [3, ['value' => self::int(1, 1000)], 'Drop: limit doborów na IP w oknie (gdy licznik IP włączony)', $access],
            self::DropIpDays => [30, ['value' => self::int(1, 3650)], 'Drop: okno licznika IP (dni)', $access],
            self::DropAttemptRate => [['max' => 20, 'window_seconds' => 3600], $rate(), 'Drop: techniczny limit prób na IP', $access],
            self::DropIpEnabled => [false, $bool, 'Drop: długookresowy licznik IP włączony', $access],
            self::TankLimit => [
                ['drop' => 0, 'tank' => 1, 'fishroom' => null],
                self::obj(['drop' => self::int(0), 'tank' => self::int(0), 'fishroom' => ['present', 'nullable', 'integer', 'min:0']]),
                'Limit zbiorników: Drop, Tank, Fishroom (null = bez limitu)', $aquarium,
            ],
            self::EmailVerificationRequired => [true, $bool, 'Wymagaj potwierdzenia adresu e-mail', $access],
            self::RegistrationTimer => [['min' => 3, 'max' => 3600], self::minMax(0, 86400), 'Rejestracja: dopuszczalny wiek formularza (s)', $access],
            self::RegistrationRate => [['max' => 5, 'window_seconds' => 900], $rate(), 'Rejestracja: limit prób na IP', $access],
            self::ActivationLifetime => [604800, ['value' => self::int()], 'Ważność tokenu aktywacji (s)', $access],
            self::PendingCleanupBatch => [100, ['value' => self::int()], 'Czyszczenie nieaktywowanych: rozmiar partii', $access],
            self::PendingRetentionDays => [30, ['value' => self::int(1, 3650)], 'Czyszczenie nieaktywowanych: wiek konta (dni)', $access],
            self::ExportIntervalMinutes => [60, ['value' => self::int(1, 1440)], 'Eksport danych konta: odstęp między pobraniami (minuty)', $access],
            self::ConsentVersion => ['1', ['value' => ['required', 'string', 'max:16']], 'Wersja regulaminu i polityki prywatności (zmiana wymusza ponowną akceptację)', $access],
            self::PasswordMinLength => [8, ['value' => self::int(1, 128)], 'Minimalna długość hasła (bajty)', $access],
            self::TankVolume => [['min' => 1, 'max' => 5000], self::minMax(1, 65535), 'Pojemność zbiornika (l)', $aquarium],
            self::ReminderHorizon => [30, ['value' => self::int()], 'Horyzont przypomnień (dni)', $aquarium],
            self::ReminderInterval => [['min' => 1, 'max' => 365], self::minMax(1, 65535), 'Interwał przypomnienia (dni)', $aquarium],
            self::WarningRanges => [array_map(fn (array $r) => ['min' => $r[0], 'max' => $r[1]], $ranges), $warning, 'Zakresy ostrzeżeń pomiarów', $aquarium],
            self::RedfieldMaxInput => [100, ['value' => ['required', 'numeric', 'gt:0', 'max:100']], 'Redfield: maks. wartość wejściowa (mg/l)', $aquarium],
            self::RedfieldZoneEdges => [[5, 10, 20, 30], self::increasing(4), 'Redfield: granice stref (rosnąco)', $aquarium],
            self::SearchMinChars => [3, ['value' => self::int()], 'Wyszukiwarka: min. liczba znaków', $advisor],
            self::SearchMaxHits => [20, ['value' => self::int()], 'Wyszukiwarka: maks. liczba wyników', $advisor],
            self::SearchDebounce => [150, ['value' => self::int(0, 10000)], 'Wyszukiwarka: debounce (ms)', $advisor],
            self::FitThresholds => [[50, 70, 85], self::list(self::int(0, 100), 'size:3'), 'Progi dopasowania (rosnąco)', $advisor],
            self::OutputPolicy => [
                ['cards' => 3, 'shortlist' => 5, 'include_ties' => true],
                self::obj(['cards' => self::int(), 'shortlist' => [...self::int(), 'gte:value.cards'], 'include_ties' => ['present', 'boolean']]),
                'Polityka wyniku rankingu', $advisor,
            ],
            self::VerificationThresholds => [[70, 90], self::list(self::int(0, 100), 'size:2'), 'Progi weryfikacji (rosnąco)', $advisor],
            self::VerificationCorrections => [
                [
                    'ph_acid' => ['acid buffer', 'ph control', 'easybalance'],
                    'ph_other' => ['easybalance', 'ph control', 'equilibrium'],
                    'co2' => ['co2 system', 'co2 plus'],
                    'heating_category' => 'Ogrzewanie',
                    'percent' => 90,
                ],
                [
                    ...self::obj([
                        'ph_acid' => ['required', 'array', 'min:1'], 'ph_other' => ['required', 'array', 'min:1'], 'co2' => ['required', 'array', 'min:1'],
                        'heating_category' => ['required', 'string'], 'percent' => self::int(0, 100),
                    ]),
                    'value.ph_acid.*' => ['required', 'string'], 'value.ph_other.*' => ['required', 'string'], 'value.co2.*' => ['required', 'string'],
                ],
                'Korekta weryfikacji: frazy szukane w pielęgnacji (pH kwaśne, pH inne, CO2), kategoria sprzętu na temperaturę i procent korekty', $advisor,
            ],
            self::GuidedAdvisorForTank => [true, $bool, 'Kreator doradcy dostępny dla planu Tank (nie tylko Fishroom)', $advisor],
            self::TrackingDedupWindow => [30, ['value' => self::int()], 'Tracker: okno deduplikacji (s)', $tracking],
            self::TrackingRate => [['max' => 60, 'window_seconds' => 60], $rate(), 'Tracker: limit żądań na klienta', $tracking],
            self::MetricsSampleTime => ['03:00', ['value' => ['required', 'date_format:H:i']], 'Godzina próbkowania metryk', $tracking],
            self::MetricsRollupTime => ['04:00', ['value' => ['required', 'date_format:H:i']], 'Godzina zamknięcia okresu', $tracking],
            self::MetricsTimezone => ['Europe/Warsaw', ['value' => ['required', 'timezone:all']], 'Strefa biznesowa', $tracking],
            self::ShopPublication => [
                ['start' => 50, 'keep' => 40, 'weak_periods' => 3],
                self::obj(['start' => self::int(), 'keep' => [...self::int(), 'lte:value.start'], 'weak_periods' => self::int()]),
                'Publikacja: próg startu, próg utrzymania, liczba słabych okresów', $shops,
            ],
            self::ShopAverageRounding => ['ceil', ['value' => ['required', 'in:ceil,floor,round']], 'Zaokrąglenie średniej (ceil/floor/round)', $shops],
            self::ShopTiers => [
                [['code' => 'S', 'min' => 50, 'max' => 100], ['code' => 'M', 'min' => 101, 'max' => 200], ['code' => 'L', 'min' => 201, 'max' => 400], ['code' => 'XL', 'min' => 401, 'max' => null]],
                ['value' => ['required', 'array', 'min:1'], 'value.*.code' => ['required', 'string', 'max:8', 'distinct'], 'value.*.min' => ['required', 'integer', 'min:0'], 'value.*.max' => ['nullable', 'integer', 'min:0']],
                'Pasma planów S/M/L/XL (liczba ofert)', $shops,
            ],
            self::ShopCommission => [
                ['bound_grosze' => 10000, 'low_bps' => 500, 'high_bps' => 200],
                self::obj(['bound_grosze' => self::int(), 'low_bps' => self::int(0, 10000), 'high_bps' => self::int(0, 10000)]),
                'Stawki afiliacyjne: granica (grosze), do granicy włącznie (bps), powyżej (bps)', $shops,
            ],
            self::ShopCommissionEnabled => [false, $bool, 'Prowizje włączone', $shops],
            self::ShopMinPrice => [100, ['value' => self::int(0, 100000000)], 'Minimalna cena oferty (grosze)', $shops],
            self::ShopFreshness => [
                ['warn_days' => 60, 'stale_days' => 90, 'stale_action' => 'warn_only', 'batch' => 200],
                self::obj(['warn_days' => self::int(), 'stale_days' => [...self::int(), 'gt:value.warn_days'], 'stale_action' => ['required', 'in:warn_only,hide'], 'batch' => self::int()]),
                'Aktualność oferty (ostrzeżenie, przeterminowanie, akcja, partia)', $shops,
            ],
            self::ShopLinkCheck => [
                ['interval_days' => 30, 'batch' => 20, 'timeout_seconds' => 4, 'max_redirects' => 3],
                self::obj(['interval_days' => self::int(), 'batch' => self::int(), 'timeout_seconds' => self::int(), 'max_redirects' => self::int(0, 20)]),
                'Kontrola linków ofert', $shops,
            ],
            self::ShopLeads => [
                ['token_hours' => 48, 'max_per_hour' => 5],
                self::obj(['token_hours' => self::int(), 'max_per_hour' => self::int()]),
                'Leady gości: ważność tokenu (h) i limit zgłoszeń na godzinę', $shops,
            ],
            self::ShopCsvImport => [
                ['max_bytes' => 10485760, 'max_rows' => 10000, 'validity_days' => 30, 'retention_hours' => 24],
                self::obj([
                    'max_bytes' => self::int(), 'max_rows' => self::int(),
                    'validity_days' => ['sometimes', ...self::int(1, 3650)],
                    'retention_hours' => ['sometimes', ...self::int(1, 8760)],
                ]),
                'Import CSV: limit pliku (bajty) i wierszy, domyślna ważność (dni) i czas przechowywania (godziny)', $shops,
            ],
            self::ShopLogo => [
                ['max_bytes' => 2097152, 'mimes' => ['image/png', 'image/jpeg', 'image/webp']],
                [
                    ...self::obj(['max_bytes' => self::int(), 'mimes' => ['required', 'array', 'min:1']]),
                    'value.mimes.*' => ['string', 'in:image/png,image/jpeg,image/webp'],
                ],
                'Logo sklepu: limit (bajty) i typy MIME', $shops,
            ],
            self::ShopViews => [
                ['click_buffer_days' => 30, 'view_rows' => 200, 'report_months' => 12, 'dashboard_publications' => 5],
                self::obj(['click_buffer_days' => self::int(), 'view_rows' => self::int(), 'report_months' => self::int(), 'dashboard_publications' => self::int()]),
                'Retencja i widoki sklepu (dni bufora kliknięć, wiersze, miesiące raportu, publikacje na pulpicie)', $shops,
            ],
            self::ShopActivation => [
                ['threshold' => 50, 'offer_limit' => 150],
                self::obj(['threshold' => self::int(), 'offer_limit' => [...self::int(), 'gte:value.threshold']]),
                'Sklepy: próg aktywacji i limit ofert (opublikowane, niewygasłe)', $shops,
            ],
            self::ShopOfferBounds => [
                ['max_price_grosze' => 10000000, 'max_qty' => 1000],
                self::obj(['max_price_grosze' => self::int(1, 4294967295), 'max_qty' => self::int(1, 1000000)]),
                'Oferta: maksymalna cena (grosze) i dostępna ilość', $shops,
            ],
            self::ShopOrders => [
                ['daily_orders' => 5, 'qty_min' => 1, 'qty_max' => 50, 'pseudonymize_days' => 90, 'report_hour' => 10],
                self::obj([
                    'daily_orders' => self::int(), 'qty_min' => self::int(1, 1000), 'qty_max' => [...self::int(1, 1000), 'gte:value.qty_min'],
                    'pseudonymize_days' => self::int(), 'report_hour' => self::int(0, 23),
                ]),
                'Zamówienia: dzienny limit zamówień klienta, ilość w pozycji (min, maks.), dni do pseudonimizacji, godzina raportu dobowego', $shops,
            ],
            self::CompatSeverity => [
                [
                    'tank_too_small' => 'warning', 'fish_too_large' => 'conflict', 'tank_type' => 'warning',
                    'temperature_pair' => 'conflict', 'temperature_tank' => 'warning', 'ph_pair' => 'conflict', 'ph_tank' => 'warning',
                    'temperament' => 'warning', 'co2_missing' => 'info', 'stocking_load' => 'info',
                ],
                self::obj(array_fill_keys(
                    ['tank_too_small', 'fish_too_large', 'tank_type', 'temperature_pair', 'temperature_tank', 'ph_pair', 'ph_tank', 'temperament', 'co2_missing', 'stocking_load'],
                    ['required', 'in:conflict,warning,info,off'],
                )),
                'Zgodność obsady: ważność każdej kontroli (conflict/warning/info/off)', $aquarium,
            ],
            self::CompatTemperament => [
                [['aggressor' => 'aggressive', 'other' => 'calm']],
                [
                    'value' => ['present', 'array'], 'value.*' => ['array'],
                    'value.*.aggressor' => ['required', 'in:calm,moderate,aggressive'], 'value.*.other' => ['required', 'in:calm,moderate,aggressive'],
                ],
                'Zgodność obsady: pary temperamentów (ryba - ryba), które dają uwagę', $aquarium,
            ],
            self::StockingCmPerLiter => [null, ['value' => ['present', 'nullable', 'numeric', 'gt:0', 'max:100']], 'Obciążenie cm ryby na litr: próg oceny (null = bez oceny)', $aquarium],
            self::CalculatorLimits => [
                [
                    'dimension_cm' => ['min' => 1, 'max' => 1000], 'volume_l' => ['min' => 1, 'max' => 65535], 'kh' => ['min' => 0, 'max' => 50],
                    'ph' => ['min' => 0, 'max' => 14], 'dose' => ['min' => 0, 'max' => 100000], 'fish_length_cm' => ['min' => 1, 'max' => 200], 'quantity' => ['min' => 1, 'max' => 10000],
                ],
                self::ranges(['dimension_cm', 'volume_l', 'kh', 'ph', 'dose', 'fish_length_cm', 'quantity']),
                'Kalkulatory: dopuszczalne zakresy wejść (min, maks.)', $aquarium,
            ],
            self::CalculatorCo2 => [
                ['factor' => 3, 'low_mg_l' => null, 'high_mg_l' => null, 'source' => null],
                self::obj([
                    'factor' => ['required', 'numeric', 'gt:0', 'max:100'], 'low_mg_l' => ['present', 'nullable', 'numeric', 'gt:0'],
                    'high_mg_l' => ['present', 'nullable', 'numeric', 'gt:0'], 'source' => ['present', 'nullable', 'string', 'max:500'],
                ]),
                'Kalkulator CO2: współczynnik wzoru, progi oceny (null = bez oceny) i opis źródła progów', $aquarium,
            ],
            self::PlanLimit => [
                ['drop' => 0, 'tank' => 1, 'fishroom' => null],
                self::obj(['drop' => self::int(0), 'tank' => self::int(0), 'fishroom' => ['present', 'nullable', 'integer', 'min:0']]),
                'Limit aktywnych planów nowego akwarium: Drop, Tank, Fishroom (null = bez limitu)', $aquarium,
            ],
            self::RemindersAuto => [
                array_fill_keys(['water_change_days', 'filter_cleaning_days', 'fertilization_days', 'water_test_after_days', 'consumable_lead_days'], null),
                self::obj(array_fill_keys(
                    ['water_change_days', 'filter_cleaning_days', 'fertilization_days', 'water_test_after_days', 'consumable_lead_days'],
                    ['present', 'nullable', 'integer', 'min:1', 'max:3650'],
                )),
                'Przypomnienia podpowiadane: interwały w dniach i wyprzedzenie dla preparatu (null = użytkownik wpisuje własny)', $aquarium,
            ],
            self::WaterHintsMap => [
                [
                    ['problem' => 'ph_high', 'area' => 'care', 'id' => 'CH0089', 'category' => null, 'phrase' => 'pozwala bezpiecznie obniżać pH'],
                    ['problem' => 'temperature_low', 'area' => 'equipment', 'id' => null, 'category' => 'Ogrzewanie', 'phrase' => null],
                ],
                [
                    'value' => ['present', 'array'], 'value.*.problem' => ['required', 'in:ph_high,ph_low,temperature_low,temperature_high'],
                    'value.*.area' => ['required', 'in:fauna,flora,equipment,care'], 'value.*.id' => ['present', 'nullable', 'string', 'max:64'],
                    'value.*.category' => ['present', 'nullable', 'string', 'max:100'], 'value.*.phrase' => ['present', 'nullable', 'string', 'max:500'],
                ],
                'Podpowiedź z pomiaru: mapa problem - produkt (id albo kategoria, fragment opisu z bazy jako dowód)', $advisor,
            ],
            self::WaterHintsChecks => [
                ['ph', 'temp_c'],
                ['value' => ['present', 'array'], 'value.*' => ['in:ph,temp_c', 'distinct']],
                'Podpowiedź z pomiaru: włączone kontrole (ph, temp_c)', $advisor,
            ],
            self::WaterHintsTolerance => [
                ['ph' => 0, 'temp_c' => 0],
                self::obj(['ph' => ['required', 'numeric', 'min:0', 'max:2'], 'temp_c' => ['required', 'numeric', 'min:0', 'max:10']]),
                'Podpowiedź z pomiaru: tolerancja odczytu (pH, °C)', $advisor,
            ],
            self::ShopWatch => [
                ['tank' => 10, 'fishroom' => 100, 'retention_days' => 14],
                self::obj(['tank' => self::int(0), 'fishroom' => self::int(0), 'retention_days' => self::int(0, 3650)]),
                'Obserwowanie rekordów: limit aktywnych obserwacji (Tank, Fishroom) i dni przechowania po powiadomieniu', $shops,
            ],
            self::ShopReviews => [
                ['window_days' => 30, 'min_to_show' => 3, 'max_length' => 500],
                self::obj(['window_days' => self::int(), 'min_to_show' => self::int(), 'max_length' => self::int(1, 5000)]),
                'Opinie o sklepie: okno po odbiorze (dni), próg widoczności średniej, maks. długość komentarza', $shops,
            ],
            self::ShopPromo => [
                ['max_days' => 30, 'lookback_days' => 30, 'show_reference_price' => false],
                self::obj(['max_days' => self::int(), 'lookback_days' => self::int(), 'show_reference_price' => ['present', 'boolean']]),
                'Promocje: maks. długość (dni), okres odniesienia ceny (dni), pokazywanie ceny sprzed obniżki', $shops,
            ],
            self::CampaignMinDiscount => [null, ['value' => ['present', 'nullable', 'integer', 'min:0', 'max:100']], 'Kampanie: domyślna minimalna obniżka w procentach (null = brak)', $shops],
            self::DemandMetrics => [
                ['k' => 5, 'retention_days' => 400, 'region' => 'city'],
                self::obj(['k' => self::int(), 'retention_days' => self::int(), 'region' => ['required', 'in:city']]),
                'Statystyki popytu: próg k, retencja liczników (dni), poziom regionu', $tracking,
            ],
        };
    }

    /** @return list<string> */
    private static function int(int $min = 1, int $max = PHP_INT_MAX): array
    {
        return ['required', 'integer', "min:$min", "max:$max"];
    }

    /**
     * Dokładnie $count dodatnich liczb, ściśle rosnąco.
     *
     * @return array<string, list<mixed>>
     */
    private static function increasing(int $count): array
    {
        $ascending = function (string $attribute, mixed $value, Closure $fail): void {
            $values = array_values((array) $value);
            for ($i = 1; $i < count($values); $i++) {
                if (is_numeric($values[$i]) && is_numeric($values[$i - 1]) && $values[$i] <= $values[$i - 1]) {
                    $fail('Wartości muszą rosnąć.');

                    return;
                }
            }
        };

        return ['value' => ['required', 'array', "size:$count", $ascending], 'value.*' => ['required', 'numeric', 'gt:0']];
    }

    /**
     * @param  list<string>  $item
     * @return array<string, list<string>>
     */
    private static function list(array $item, string ...$extra): array
    {
        return ['value' => ['required', 'array', 'min:1', ...$extra], 'value.*' => [...$item, 'distinct']];
    }

    /**
     * @param  array<string, list<string>>  $fields
     * @return array<string, list<string>>
     */
    private static function obj(array $fields): array
    {
        $rules = ['value' => ['required', 'array']];
        foreach ($fields as $name => $field) {
            $rules["value.$name"] = $field;
        }

        return $rules;
    }

    /**
     * Zakresy {min, maks.} dla podanych nazw (liczby, min nieujemne, maks. nie mniejsze niż min).
     *
     * @param  list<string>  $names
     * @return array<string, list<string>>
     */
    private static function ranges(array $names): array
    {
        $rules = self::obj(array_fill_keys($names, ['required', 'array']));
        foreach ($names as $name) {
            $rules["value.$name.min"] = ['required', 'numeric', 'min:0'];
            $rules["value.$name.max"] = ['required', 'numeric', "gte:value.$name.min"];
        }

        return $rules;
    }

    /** @return array<string, list<string>> */
    private static function minMax(int $lo, int $hi): array
    {
        return self::obj([
            'min' => self::int($lo, $hi),
            'max' => [...self::int($lo, $hi), 'gte:value.min'],
        ]);
    }
}
