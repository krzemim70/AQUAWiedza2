# Etap „Teraz”: zakres i projekt

Stan: projekt uzgodniony w niezależnym przeglądzie architektonicznym (dwie rundy), do decyzji właściciela w sprawie pytań z §6. Rozwija §3 „Teraz” z `13-kierunki-rozwoju.md` i przygotowuje etap „Następnie” (API aplikacji, odczyty z urządzeń). Obowiązuje twarda zasada: każda rada z rekordu bazy wiedzy lub jawnie oznaczonego źródła, brak danych → „brak danych”.

## 1. Pakiety

| Fala | Pakiet | Kopia robocza | Rozmiar | Wartość |
|---|---|---|---|---|
| 1 | Fundament: klucze ustawień i funkcje dostępu | główna | S | Odblokowuje równoległą pracę |
| 2 | Pola strukturalne preparatów, zakresy gatunków, panel redakcji | główna | M | Szersze porównanie wody (KH, GH, NO₃, PO₄) i kolejne preparaty z dowodem |
| 2 | Wirtualne zapasy, „Zastosowałem”, „Zamów ponownie” | akwarium | M | Kontrola zapasów; powtarzalny zakup w tym samym sklepie |
| 2 | PWA i powiadomienia push | konta | M | Instalacja na telefonie, przypomnienia i niski stan zapasów jako push |
| 3 | Trendy pomiarów i „Szybki pomiar” | akwarium | M | Wykres zmian, szybki wpis z telefonu |
| 3 | Pełne podpowiedzi wody i publiczne „Czy te ryby mogą być razem?” | główna | S/M | Ruch z wyszukiwarek, ostrożne polecenia z przeciwwskazaniami |

Odkładamy: skaner kodów kreskowych (zostaje tylko pole `ean`), zapis offline z synchronizacją, prognozy odczytów, odczyt pasków ze zdjęcia, prowadzenie przez dojrzewanie zbiornika, poradnik objawów, wspólne zbiorniki rodzinne, kupony, tabele urządzeń.

## 2. Projekt pakietów

### Fundament (fala 1)
Wyłącznie nowe klucze w `SettingKey` i wiersze `Feature` (bez migracji kolumn). Jeśli właściciel zgodzi się na ręczne przypomnienia w planie Tank (pytanie 4) — osobna funkcja dostępu dla przypomnień z limitem aktywnych przypomnień.

### Pola strukturalne i panel redakcji (moduł KnowledgeBase)
- Tabela `catalog_record_specs` przypięta do trwałego `catalog_records.id` (praca redakcji przetrwa import nowej partii): `status` (`draft`/`verified`), `source_kind`, `source_ref`, `evidence_quote` (wymagany cytat), `ean`, `attributes` (JSON walidowany według obszaru), `verified_by`, `verified_at`, audyt.
- Źródła: `catalog_quote` (zdanie z opisu rekordu, sprawdzane przy zapisie i aktywacji partii), `manufacturer_label` (preparaty i sprzęt; producent i nazwa karty + cytat), `literature` (zakresy gatunków; publikacja lub stały odnośnik + cytowany zakres).
- Preparaty: parametr, kierunek, dawka z etykiety, na ile litrów, pojemność opakowania, woda słodka/morska, przeciwwskazania (krewetki, ślimaki, ryby bez łusek, rośliny — każde z cytatem). Gatunki: zakresy pH, temperatury, KH, GH, NO₃, PO₄.
- „Dlaczego?” jawnie rozróżnia: katalog Aikwarium / etykieta producenta / literatura (poza tabelą katalogu). Sprzeczność z pasmem tabeli → oba źródła, bez polecenia preparatu.
- Wpisy `draft` widoczne tylko w panelu admina; kontrakt odczytu zwraca wyłącznie `verified`. Zgubiony cytat po imporcie wyłącza wpis (log, „brak danych”).

### Wirtualne zapasy (moduł Aquarium)
- `tank_supplies`: `tank_id`, `catalog_id` (preparat lub karma z bazy), `unit` (`ml`/`g`/`szt`/`krople`), `package_amount`, `remaining_amount`, `balance_at`, `dose_amount`, `dose_per_liters` (puste = stała porcja, np. karma), `interval_days` (puste = odejmowanie tylko po „Zastosowałem”), `preferred_shop_id`, `low_stock_notified_at`, `archived_at`.
- `tank_supply_events`: `supply_id`, `occurred_at`, `delta_amount` (− zużycie, + uzupełnienie), `source` (`manual`/`device`), `device_sensor_id`, `idempotency_key` (unikalny w ramach pozycji — podwójne kliknięcie i ponowienia nie odejmą dwa razy).
- Prognoza „zostało około N dni” liczona przy odczycie z harmonogramu i zdarzeń, opisana jako szacunek z harmonogramu użytkownika. Dawka na litry bez wpisanego litrażu → „brak danych: uzupełnij litraż zbiornika” (bez zgadywania z pasma). Pola z etykiety podpowiadane z `catalog_record_specs` do potwierdzenia.
- Zakładka zbiornika „Zapasy”, przyciski „Zastosowałem”, „Otwórz nowe opakowanie”, „Zamów ponownie” (preferowany sklep lub oferty z okolicy, istniejące dodawanie do koszyka); „Zamów ponownie” także przy odebranym zamówieniu. Eksport i usunięcie konta obejmują zapasy.

### PWA i powiadomienia push (moduł Notifications)
- Biblioteka `minishlink/web-push` (bez zależności od Laravela) + własny krótki kanał powiadomień; bez własnej kryptografii.
- `push_subscriptions` (endpoint, klucze, ostatnie użycie; usuwanie przy odpowiedzi 404/410), preferencja `push` w ustawieniach powiadomień, `reminder_notices` (jedna wysyłka na wystąpienie przypomnienia).
- `SendDueReminders` raz dziennie o godzinie z ustawienia: przypomnienia i niski stan zapasów.
- `manifest.webmanifest`, `sw.js` buforuje wyłącznie wersjonowane pliki `build/` i stronę offline — nigdy prywatnych stron. Na iOS instrukcja dodania do ekranu początkowego.

### Trendy i „Szybki pomiar” (moduł Aquarium)
- Migracja `water_measurements`: `source` (`manual`/`device`/`import`), `method` (pasek/kropelkowy/miernik/czujnik), `ec_us_cm`, `calibration_status`, `device_sensor_id`, `idempotency_key`, indeks `(tank_id, measured_at)`.
- Czysta klasa `TrendCalculator` (bez frameworka): minimum punktów z ustawienia (domyślnie 3; mniej → „za mało pomiarów”), zmiana i średnie tempo w oknie 7/30/90 dni opisane jako historia (nie prognoza), oznaczenie mieszania metod pomiaru. Tło zakresu obsady tylko przy niesprzecznym wspólnym zakresie.
- Nieskalibrowana sonda → ostrzeżenie odczytu, które już dziś blokuje polecenie preparatu.
- Wykres SVG z dostępną tabelą (zakładka historii), `/moje-akwarium/szybki-pomiar` z dużymi polami i ostatnimi wartościami.

### Pełne podpowiedzi wody i „Czy razem?”
- Podpowiedź z pomiaru korzysta z zatwierdzonych zakresów i preparatów; preparat z przeciwwskazaniem przy niepełnych danych obsady jest ukrywany z wyjaśnieniem.
- Porównanie obsady wykrywa rozbieżności tabela–opis (zaległość z §14.4).
- Publiczna strona `/doradca/czy-razem?gatunki=…` (2–8 gatunków, link do udostępnienia, dowody, „relacja pary: brak danych”, oferty pod wynikami), odnośnik ze stron gatunków.

## 3. Przygotowanie pod „Następnie”

| Zagadnienie | Teraz | Później bez przepisywania |
|---|---|---|
| Źródło odczytu | `source`, `method`, `device_sensor_id` w pomiarach i zdarzeniach zapasów | moduł urządzeń mapuje adres Zigbee / węzeł Matter na zbiornik i czujnik i woła te same akcje; moc gniazdek, poziom i zalanie w osobnej tabeli telemetrii |
| Jednostki | stałe: °C, pH, °dKH, °dGH, mg/l, µS/cm; zapasy ml/g/szt | konwersja tylko na wejściu bramki (np. temperatura Zigbee ÷100, klaster pH, impulsy dozownika → ml); przewodność zamiast TDS |
| Częstotliwość | indeks po czasie, trendy odporne na nierówne odstępy | czujnik co kilka minut; alarmy w locie, do bazy próbki co 15–60 min lub przy skoku |
| Duplikaty | klucze `idempotency_key` | ponowienia z bramki i aplikacji nie dublują odczytów ani dawek |
| Kalibracja pH | `calibration_status` na odczycie | nieskalibrowana sonda nigdy nie uruchomi polecenia preparatu |
| API mobilne | akcje domenowe z DTO, powiadomienia za kontraktem | trasy `auth:sanctum` wołają te same akcje; natywny push obok Web Push |

## 4. Kolejność

Fala 1 (fundament, kilkanaście minut) → fala 2 równolegle w trzech kopiach (redakcja / zapasy / push) → fala 3 równolegle (trendy / podpowiedzi i „Czy razem?”). Pliki wspólne (`SettingKey`, `Feature`) tylko w fali 1; każdy pakiet ma własne pliki tras, zakładek i migracji.

## 5. Wykonanie

Wykonawcy według `_role-i-przeplyw.md`; każdy pakiet ze złotymi testami na prawdziwych rekordach i bramką `composer check`.

## 6. Pytania do właściciela

1. Czy redakcja może dopisywać dane z etykiet producentów (preparaty) i z literatury z odnośnikiem (zakresy gatunków), jawnie oznaczone w „Dlaczego?”, i czy po pomiarze polecamy kolejne preparaty (ukryte przy przeciwwskazaniach i niepełnej obsadzie)?
2. Czy zgadzasz się na jedną nową bibliotekę `minishlink/web-push` (szyfrowanie powiadomień push)?
3. Powiadomienie „kończy się Twój produkt X — zobacz oferty”: funkcja konta z własnym przełącznikiem czy wymaga zgody marketingowej? (do potwierdzenia prawnego)
4. Czy podstawowe ręczne przypomnienia (z limitem) przenosimy do bezpłatnego planu Tank, żeby push o podmianach wody działał także tam, czy w Tank push tylko o zapasach i dostępności ofert?

Ustalone w przeglądzie (bez pytań): zapasy, szybki pomiar i push w planie Tank (limit pozycji z ustawienia), trendy w Fishroom; preparat z przeciwwskazaniem przy niepełnych danych jest ukrywany z wyjaśnieniem.
