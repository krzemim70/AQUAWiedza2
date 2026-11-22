# AquaSmart v2 — analiza modułu A `aquasmart_v103`

## 1. Cel i zakres

Raport odtwarza zachowanie doradcy Aikwarium, portalu, zarządzania bazami i integracji z pozostałymi wtyczkami. Nagłówek WordPress i stała wersji wskazują **1.1.6**, zgodnie z lokalną historią Git (`501dc78`) i mapowaniem paczki produkcyjnej. Interfejs pokazuje **`Aikwarium V1.0.3 build 20260603-003`**, a komunikat aktywacyjny **`Aikwarium V1.0.4`**. Przegląd objął 52 pliki, w tym **4 657 fizycznych linii PHP** w czterech plikach wykonawczych: bootstrap i widoki 1 061, silnik 2 696, dane 683, portal 217. Dodatkowo skrypt wyszukiwania ma 131 linii, trzy arkusze CSS łącznie 1 010, trzy pliki testowe 85, a cztery bazy 603 rekordy. Liczby LOC obejmują komentarze i puste linie. Wnioski pochodzą z analizy lokalnej i obliczeń na bazach `bundled`. Wyników regresyjnych nie uruchomiono w PHP ani w WordPressie. **[HIPOTEZA]** Bieżąca instancja produkcyjna używa tych samych danych `bundled`; lokalna historia potwierdza wersję paczki, a aktywne źródło jest opcją instancji. (`akwasmart-k1k2-r27-final-result.php:3–32`, `:73–78`, `:1057–1061`; `includes/class-akwasmart-data.php:102–110`, `:257–282`; `BUILD.txt:1–6`; `AQUAWiedza/audyty/T0_MAPOWANIE_PRODUKCJA_COMMIT.md:8–19`, `:82–104`)

Ścieżki bez prefiksu repozytorium są względne wobec `AquaSmart-plugins/aquasmart_v103/`. W tabeli „działa” oznacza osiągalną ścieżkę potwierdzoną w kodzie. Numery w odwołaniach do TSV obejmują nagłówek w linii 1.

## 2. Inwentarz funkcji

| ID | Funkcja (z perspektywy użytkownika) | Rola (gość/user/sklep/admin) | Gdzie (path:line) | Status | Uwagi |
|---|---|---|---|---|---|
| A01 | Otwórz Doradcę Aikwarium i odpowiadaj po jednym pytaniu | user/sklep; admin z dostępem Auth | `akwasmart-k1k2-r27-final-result.php:122–178`, `:346–374`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:105–114` | działa | Postęp, aktualne pytanie, opcje jako przyciski, wersja widoczna na ekranie. |
| A02 | Dobierz faunę | user/sklep | `includes/class-akwasmart-engine.php:355–447`, `:665–734`, `:811–854` | działa | Trzy ścieżki `Ryba`, `Skorupiak`, `Ślimak`. Typ jest wymagany przy niepustej puli K1. |
| A03 | Dobierz florę | user/sklep | `includes/class-akwasmart-engine.php:422–429`, `:557–592`, `:682–695` | częściowo | Działa ranking roślin. Ograniczenie `Wysoka (>30 cm)` dla nano jest nadpisywane przez pełny słownik opcji. |
| A04 | Dobierz sprzęt | user/sklep | `includes/class-akwasmart-engine.php:422–429`, `:522–538`, `:758–764`, `:883–908`; `data/baza_biotop_end2.txt:1–109` | działa | Filtry, grzałki, LED i CO₂. Marka i producent wpływają na ranking. |
| A05 | Dobierz produkty pielęgnacyjne | user/sklep | `includes/class-akwasmart-engine.php:422–429`, `:540–555`, `:758–764`, `:856–870`; `data/baza_pielegnacja_end1.txt:1–111` | działa | Pytania o zbiornik są wyświetlane, choć bieżąca baza pielęgnacji nie ma odpowiadających im pól. |
| A06 | Wyszukaj produkt lub organizm po nazwie, ID lub producencie | user/sklep; endpoint obsługuje również gościa z nonce | `akwasmart-k1k2-r27-final-result.php:126–135`, `:269–305`, `:376–405`; `assets/js/search.js:43–122`; `includes/class-akwasmart-data.php:585–681` | działa | Sugestie od 3 znaków, do 20 wyników, ręczny przycisk „Szukaj” działa przez POST. |
| A07 | Sprawdź konkretny rekord i jego warunki optymalne | user/sklep | `includes/class-akwasmart-engine.php:1225–1471`, `:1646–1687`; `akwasmart-k1k2-r27-final-result.php:562–667` | częściowo | Fauna, flora i sprzęt mają pytania kontrolne. Pielęgnacja automatycznie dostaje `100%` i `Zgodny` przy zerze warunków. |
| A08 | Zobacz alternatywę, produkt korygujący i produkty współistniejące | user/sklep | `includes/class-akwasmart-engine.php:1323–1359`, `:1496–1643`; `akwasmart-k1k2-r27-final-result.php:648–676` | częściowo | Alternatywa i korekta dopiero poniżej 70%. Korekta opiera się na nazwie/tekście lub pierwszej pasującej grzałce. |
| A09 | Przeczytaj rekomendacje, remisy, uzasadnienia i ograniczenia | user/sklep | `includes/class-akwasmart-engine.php:923–1083`, `:2329–2386`; `akwasmart-k1k2-r27-final-result.php:414–560`, `:715–859` | działa | Wynik główny, alternatywy, procent, powody, ostrzeżenia i podstawa oceny. |
| A10 | Otrzymaj informację o pustej puli | user/sklep | `includes/class-akwasmart-engine.php:911–921`; `akwasmart-k1k2-r27-final-result.php:438–452`, `:784–821` | błąd | Silnik zapisuje `Brak dopasowań`. Widok renderuje pustą sekcję z plakietką `Gotowe dopasowanie`. |
| A11 | Przejrzyj ustalenia i zacznij od nowa | user/sklep | `akwasmart-k1k2-r27-final-result.php:174–203`, `:217–235`, `:308–314`; `includes/class-akwasmart-engine.php:2660–2694` | działa | Reset daje nowe UUID. UI nie usuwa poprzedniego stanu. Brak przycisku wstecz i edycji odpowiedzi. |
| A12 | Przejdź z rekomendacji do oferty sklepu | user/sklep | `akwasmart-k1k2-r27-final-result.php:663–676`, `:775–780`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:56–133` | częściowo | Blok dodaje Shop. Produkt korygujący z innej bazy dostaje obszar głównej karty. |
| A13 | Zobacz wizytówkę portalu i przejdź do logowania/rejestracji | gość; admin bez roli portalowej widzi tę samą wizytówkę | `includes/class-aqs-portal.php:115–135`, `:159–194` | działa | Cztery kafelki prowadzą na `/logowanie/`; konto wyłączone z wizytówki. |
| A14 | Zobacz kafelki właściwe dla roli | user/sklep | `includes/class-aqs-portal.php:68–112`, `:123–135`, `:196–215` | działa | Role łączą się przez sumę dostępnych kafelków. Przy obu rolach podtytuł jest sklepowy. |
| A15 | Wejdź bezpośrednio na stronę modułu | gość/user/sklep/admin | `includes/class-aqs-portal.php:37–58`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:105–114` | działa | Portal przekierowuje gościa dla slugów z rejestru. Widoczność kafelka i uprawnienia modułu są odrębnymi warunkami. |
| A16 | Obejrzyj źródła danych i manifest w Narzędziach | admin | `akwasmart-k1k2-r27-final-result.php:864–921`, `:952–1003` | działa | Tryb, kompletność, katalog, ostatni import, pliki, liczby rekordów, ścieżki i SHA1. |
| A17 | Importuj najnowsze pliki z Mediateki | admin | `akwasmart-k1k2-r27-final-result.php:932–936`, `:1018–1031`; `includes/class-akwasmart-data.php:147–192`, `:285–321` | częściowo | Import kopiuje pliki pojedynczo i zgłasza brakujące. Może pozostawić częściowo nadpisany pakiet. |
| A18 | Przełącz `bundled` / `imported` albo usuń import | admin | `akwasmart-k1k2-r27-final-result.php:923–949`, `:1005–1044`; `includes/class-akwasmart-data.php:102–125`, `:195–210` | częściowo | Kompletność sprawdza istnienie czterech plików. Usuwanie ignoruje błędy `unlink`. |
| A19 | Zobacz komunikat po aktywacji i wygląd portalu | admin / wszystkie role | `akwasmart-k1k2-r27-final-result.php:73–119`; `includes/class-akwasmart-data.php:45–52`; `assets/css/aqs-design.css:273–345`; `assets/css/aqs-skin.css:16–150`; `assets/css/frontend.css:97–188` | działa | Notice ma 30 s. Wspólna skórka działa globalnie; widok Doradcy i portal mają własne arkusze i układy responsywne. |
| A20 | Zobacz diagnostykę silnika w Doradcy | admin | `includes/class-akwasmart-engine.php:2627–2657`; `akwasmart-k1k2-r27-final-result.php:137–209`, `:414–445` | martwy kod | Payload oblicza `debug` dla `manage_options`. Shortcode go nie renderuje. Widoczna diagnostyka źródeł jest w A16. |
| A21 | Dobierz kompletny plan, poziom doświadczenia lub zdalne sterowanie | user/sklep | `includes/class-akwasmart-engine.php:250–263`, `:422–429`, `:476–478`, `:1139–1149`, `:1742–1747`, `:2600–2611` | martwy kod | Brak tych opcji w normalnej ścieżce. Część planu nadal osiągalna wewnętrznie przez niewalidowany cel K1. |
| A22 | Pobierz chronione dane wyłącznie przez aplikację | admin / wszystkie role | `includes/class-akwasmart-data.php:55–99`; `data/.htaccess:1–7`; `data/web.config:1–9`; `akwasmart-k1k2-r27-final-result.php:126–135` | częściowo | Pliki mają reguły Apache/IIS. Przeglądarka otrzymuje osobny, pełny indeks nazw i ID. |
| A23 | Utwórz parę biologiczną lub obsadę zgodną wzajemnie | user/sklep | `includes/class-akwasmart-engine.php:300–313`, `:665–734`, `:1625–1643`, `:2600–2611` | niepewne | Brak silnika kompatybilności między rekordami w przejrzanym kodzie. **[HIPOTEZA]** Taką usługę może świadczyć inny element produkcji poza tymi repozytoriami. |

## 3. Model danych

### 3.1 Dane WordPressa i pliki

| Obiekt / typ danych | Zapis | Odczyt i zachowanie |
|---|---|---|
| Własne tabele, kolumny generowane i indeksy | Brak operacji instalacji schematu w module. | Bootstrap rejestruje hardening. Operacje SQL dotyczą istniejących tabel WP. `akwasmart-k1k2-r27-final-result.php:28–55`; `includes/class-akwasmart-data.php:285–307`. |
| `wp_options.aqs_k1k2_dataset_source` | `bundled` lub `imported`; zapis przy aktywacji, zmianie źródła, usunięciu aktywnego importu, zmianie wersji i wymuszonym resecie. | Odczyt w `current_source_mode`, domyślnie `bundled`, obca wartość też `bundled`. `includes/class-akwasmart-data.php:45–52`, `:102–110`, `:195–210`, `:257–282`; `akwasmart-k1k2-r27-final-result.php:34–55`. |
| `wp_options.aqs_k1k2_import_manifest` | Tablica `imported_at` w czasie WP i `files[area]` z `basename`, `path`, `source_path`, `sha1`, `rows`, `modified_gmt`. Import zapisuje także manifest częściowy, usunięcie pakietu kasuje opcję. | Panel i `source_overview`. Aktywacja źródła używa istnienia plików, a nie zgodności manifestu. `includes/class-akwasmart-data.php:113–125`, `:147–210`, `:220–227`; `akwasmart-k1k2-r27-final-result.php:976–998`. |
| `wp_options.aqs_k1k2_bundled_version` | `plugins_loaded` zapisuje `1.1.6` przy różnicy wersji, FORCEFIX usuwa poprzedni znacznik. | Porównanie z `AQUASMART_K1K2_VERSION`; różnica wymusza `bundled` i wyzerowanie cache w żądaniu. `akwasmart-k1k2-r27-final-result.php:11`, `:34–55`. |
| `wp_options.aqs_k1k2_force_reset_build` | Znacznik `20260603-003`, zapisywany przed czyszczeniem. | Bootstrap porównuje wartość, kasuje opcję wersji, ustawia `bundled`, czyści cache i wykonuje zbiorczy `DELETE` transientów w `wp_options`. `akwasmart-k1k2-r27-final-result.php:34–55`. |
| Transient `aqs_k1k2_{UUID}` | Silnik tworzy, nadpisuje i odświeża z `DAY_IN_SECONDS` = 86 400 s. Gałąź `submit_answer(reset)` usuwa klucz, a reset formularza tworzy nową sesję. | Odczyt przez `get_payload`, `submit_answer`, zakres wyszukiwania i nieużywane `autocomplete`. Stan: `session_id`, `revision`, `stage`, `answered_steps`, `goal`, `specific_record`, `tank_size`, `aquarium_type`, `experience_level`, `summary`, `active_units`, `current_unit_index`, `completed_units`, `done_message`, `specific_mode`. Jednostka ma `id`, `area`, `label`, `answers`, `shortlist`, `zero_result`, `started`; tryb szczegółowy dodaje `mode`, `record`, ranking może dodać `ranking_fallback_note`. Bez zewnętrznego object cache klucze WP mają postać `_transient_…` i `_transient_timeout_…`. `includes/class-akwasmart-engine.php:5–102`, `:300–313`, `:923–940`, `:1240–1254`; `akwasmart-k1k2-r27-final-result.php:41–43`, `:226–235`. |
| Transient `aqs_k1k2_activated` | Aktywacja zapisuje `true`, TTL `30` s. | `admin_notices` odczytuje i usuwa po pierwszym wyświetleniu adminowi. `includes/class-akwasmart-data.php:45–52`; `akwasmart-k1k2-r27-final-result.php:73–78`. |
| `post_meta._wp_attached_file`, rekord `attachment` w `wp_posts`; `user_meta`, CPT i taksonomie | Mediateka jest właścicielem załącznika i ścieżki. Moduł nie rejestruje CPT ani taksonomii i nie zapisuje `user_meta` ani `post_meta`. | Import czyta ścieżkę z `wp_postmeta` przez JOIN po `post_id`, `post_type='attachment'`, `LIKE '%basename'`, sort `post_date_gmt DESC, ID DESC`, `LIMIT 1`. Awaryjnie pierwszy obecny wynik `uploads/*/*/{basename}`. Role czyta z `wp_get_current_user()->roles`. `includes/class-akwasmart-data.php:285–321`; `includes/class-aqs-portal.php:123–135`; `akwasmart-k1k2-r27-final-result.php:58–71`, `:864–883`. |
| `data/*.txt`, `uploads/aquasmart-k1k2-import/*.txt` | `bundled` pochodzi z paczki; admin kopiuje pliki z Mediateki do katalogu importu i może je usuwać. | `AquaSmart_Data::all()` czyta cztery pliki, utrzymuje tablice rekordów i metadane źródeł w statycznym cache żądania. W `imported` każdy obszar oddzielnie wybiera plik importowany lub `imported_missing_fallback` z paczki. `includes/class-akwasmart-data.php:20–43`, `:127–211`, `:230–282`. |
| `BUILD.txt`, osłony katalogów i fonty | `BUILD.txt` i assety dostarczane w paczce. Kod tworzy brakujące `index.php`, `.htaccess`, a przy `deny_all` również `web.config`, tylko w zapisywalnych katalogach. | Bootstrap czyta pierwszą niepustą linię i `Build:`. Serwer stosuje dostępne reguły katalogowe. CSS wskazuje lokalne Inter WOFF2, `latin` i `latin-ext`, wagi 400/500/600/700. `akwasmart-k1k2-r27-final-result.php:12–23`; `includes/class-akwasmart-data.php:55–99`; `data/.htaccess:1–7`; `data/web.config:1–9`; `assets/css/aqs-skin.css:16–83`. |

### 3.2 Cztery bazy `bundled`

Parser używa `fgetcsv` z tabulatorem, `"` i `\` jako escape. Pierwszy wiersz to nagłówki, każda komórka przechodzi przez `trim` i wyrażenie `^ï»¿`. Brakujące pola stają się pustym tekstem, nadmiar poza liczbą nagłówków jest ignorowany. Pusty wiersz lub pusty `id` jest pomijany. Kolejność rekordów jest kolejnością pliku. `split_multi` rozdziela po średniku, przycina i usuwa puste tokeny, zachowując kolejność i powtórzenia. (`includes/class-akwasmart-data.php:324–375`)

| Plik | Rekordy × kolumny | Jakość zmierzona na pliku |
|---|---:|---|
| `data/baza_fauna_end1.txt:1–185` | **184 × 12** | 172 ryby, 5 skorupiaków, 7 ślimaków. Zero pustych komórek, rozjechanych wierszy i powtórzeń ID, nazwy PL lub łacińskiej. UTF‑8 bez BOM; nagłówek ma LF, 184 rekordy CRLF. W `FN0159` jest NBSP w `Pomacea sp.ampularia`; zwykła spacja w zapytaniu nie dopasowuje tej nazwy. `data/baza_fauna_end1.txt:160`; `includes/class-akwasmart-data.php:585–612`. |
| `data/baza_flora_end1.txt:1–202` | **201 × 11** | Zero pustych komórek, rozjechanych wierszy i powtórzeń ID, nazwy PL lub łacińskiej. UTF‑8 bez BOM, CRLF. Nazwa PL bywa kopią łacińskiej, np. `FL0003`. Karta skleja obie, więc etykieta bywa powtórzona. `data/baza_flora_end1.txt:4`; `includes/class-akwasmart-data.php:674–679`. |
| `data/baza_biotop_end2.txt:1–109` | **108 × 11** | Zero pustych komórek, rozjechanych wierszy i powtórzeń ID lub całej treści rekordu. UTF‑8 bez BOM, CRLF. `Zdalne_sterowanie`: `nie` 95, `tak` 12, `Nie` 1 (`SP0108`). Powtórzenia modelu i producenta: `16W Plant` `SP0089`/`SP0095`, `16W Sunny` `SP0090`/`SP0096`, każdorazowo `Zestaw LED` i `Belka LED`. `data/baza_biotop_end2.txt:90–97`, `:109`. |
| `data/baza_pielegnacja_end1.txt:1–111` | **110 × 8** | Zero pustych komórek, rozjechanych wierszy i powtórzeń ID lub całej treści rekordu. UTF‑8 bez BOM, CRLF. Cztery powtórzenia produktu i producenta w różnych zastosowaniach: Equilibrium `CH0041`/`CH0090`, PhosGuard `CH0065`/`CH0086`, Purigen `CH0066`/`CH0091`, Fishtamin `CH0081`/`CH0093`. `data/baza_pielegnacja_end1.txt:42`, `:66–67`, `:82`, `:87`, `:91–94`. |

Podane liczby **184 / 201** potwierdzają przybliżenia z `AQUAWiedza/README.md:100–101`. `BUILD.txt:3–6` podaje poprawne liczby dla wszystkich czterech plików.

#### Słowniki współdzielone

| Pole | Dokładne wartości i znaczenie | Źródło |
|---|---|---|
| `WielkośćAkwarium` | W kolejności K1: `Nano (< 40L)`, `Małe (40L - 100L)`, `Średnie (101L - 250L)`, `Duże (251L - 500L)`, `Bardzo duże (> 500L)`. Techniczne `Wszystkie` występuje w faunie i florze. Przykład listy: `Średnie (101L - 250L);Duże (251L - 500L)`. | `includes/class-akwasmart-data.php:405–436`; `data/baza_fauna_end1.txt:151`; `data/baza_flora_end1.txt:27`; `data/baza_biotop_end2.txt:2`. |
| `TypAkwarium` | W kolejności preferowanej: `Nano / Krewetkarium`, `Ogólne / Towarzyskie`, `Roślinne`, `Biotop Twardowodny (Malawi/Tanganika)`. Fauna ma również `Specjalistyczne`, dopisywane po preferowanych. W polu można łączyć typy średnikiem. | `includes/class-akwasmart-data.php:439–473`; `data/baza_fauna_end1.txt:169`; `data/baza_biotop_end2.txt:2`, `:109`. |
| `Region` | `Afryka`, `Ameryka Południowa`, `Ameryka Płn. i Środk.`, `Azja`, `Inne`. Pojedyncza kategoria geograficzna; `Inne` jest zwykłą wartością. | `includes/class-akwasmart-engine.php:578–585`, `:1986–1996`; `data/baza_fauna_end1.txt:2–185`; `data/baza_flora_end1.txt:2–202`. |
| `Marka` | `Standard`, `Premium`. Segment marki, producent jest osobną kolumną. | `includes/class-akwasmart-engine.php:511–512`, `:1942–1945`; `data/baza_biotop_end2.txt:2`, `:6`; `data/baza_pielegnacja_end1.txt:2`, `:6`. |

#### Fauna i flora — wszystkie kolumny

| Baza | Kolumna | Znaczenie, dziedzina i przykład | Źródło |
|---|---|---|---|
| Fauna | `id` | Stabilny klucz `FN0001`–`FN0184`. | `data/baza_fauna_end1.txt:1–185`. |
| Fauna | `Nazwa polska` | Tekst, np. `Gupik Pawie Oczko`. | `data/baza_fauna_end1.txt:2`. |
| Fauna | `Nazwa łacińska` | Tekst, np. `Poecilia reticulata`. | `data/baza_fauna_end1.txt:2`. |
| Fauna | `Region` | Słownik regionu, np. `Ameryka Południowa`. | `data/baza_fauna_end1.txt:2`. |
| Fauna | `Wielkość` | Rozmiar organizmu, skala: `Nano (do 3 cm)`, `Mała (3-5 cm)`, `Średnia (5-10 cm)`, `Duża (10-15 cm)`, `B. duża (>15 cm)`. Przykład `Mała (3-5 cm)`. | `includes/class-akwasmart-engine.php:1927–1934`; `data/baza_fauna_end1.txt:2`. |
| Fauna | `Temperatura` | Skala: `Zimnolubne (poniżej 18)`, `Grzałka sporadycznie (18-21,5)`, `Grzałka często (22-25,5)`, `Grzałka b. często (26-30)`. Tylko `FN0165` ma pierwszą wartość. | `includes/class-akwasmart-engine.php:1938`; `data/baza_fauna_end1.txt:166`. |
| Fauna | `pH` | `Woda kwaśna (poniżej 6.8)`, `Woda obojętna (6.8 - 7.5)`, `Woda zasadowa (powyżej 7.5)`. Przykład `Woda obojętna (6.8 - 7.5)`. | `includes/class-akwasmart-engine.php:1939`; `data/baza_fauna_end1.txt:2`. |
| Fauna | `Temperament` | `Spokojny`, `Umiarkowany`, `Agresywny/Terytorialny`. Pole występuje u wszystkich typów, aktywny ranking pyta o nie dla ryb. | `includes/class-akwasmart-engine.php:442–445`, `:2026–2028`; `data/baza_fauna_end1.txt:2`, `:160`. |
| Fauna | `Strefa` | Strefa pływania `Dolna`, `Środkowa`, `Górna`; np. `Górna`. | `includes/class-akwasmart-engine.php:508`; `data/baza_fauna_end1.txt:2`. |
| Fauna | `Typ` | `Ryba`, `Skorupiak`, `Ślimak`; np. `Skorupiak` dla `FN0150`. | `includes/class-akwasmart-engine.php:450–452`; `data/baza_fauna_end1.txt:151`. |
| Fauna | `WielkośćAkwarium` | Słownik litrażu, 11 surowych kombinacji, `Wszystkie` w 10 rekordach. Przykład `Małe (40L - 100L)`. | `data/baza_fauna_end1.txt:1–185`. |
| Fauna | `TypAkwarium` | Wszystkie 5 typów ze słownika, 7 surowych kombinacji; np. `Specjalistyczne`. | `data/baza_fauna_end1.txt:1–185`, `:169`. |
| Flora | `id` | Stabilny klucz `FL0001`–`FL0201`. | `data/baza_flora_end1.txt:1–202`. |
| Flora | `Nazwa łacińska` | Tekst gatunku lub odmiany, np. `Rotala rotundifolia`. | `data/baza_flora_end1.txt:2`. |
| Flora | `Nazwa polska` | Tekst, np. `Rotala okrągłolistna`. | `data/baza_flora_end1.txt:2`. |
| Flora | `Region` | Słownik regionu, np. `Azja`. | `data/baza_flora_end1.txt:2`. |
| Flora | `Wielkość` | Rozmiar rośliny: `B. mała (< 5 cm)`, `Niska (5-10 cm)`, `Średnia (10-30 cm)`, `Wysoka (>30 cm)`. Przykład `Wysoka (>30 cm)`. | `includes/class-akwasmart-engine.php:1917–1925`; `data/baza_flora_end1.txt:2`. |
| Flora | `Trudność` | `Początkujący`, `Średni`, `Ekspert`. W aktywnej weryfikacji to opis. | `data/baza_flora_end1.txt:2–14`; `includes/class-akwasmart-engine.php:1659–1667`, `:2241–2245`, `:2540–2546`. |
| Flora | `Strefa` | `Przód`, `Środek`, `Tło`, `Na dekoracje`, `Pływające`; np. `Środek`. | `includes/class-akwasmart-engine.php:581`; `data/baza_flora_end1.txt:2`. |
| Flora | `CO2` | `Zbędny`, `Wskazany`, `Niezbędny`; np. `Wskazany`. | `includes/class-akwasmart-engine.php:582`; `data/baza_flora_end1.txt:2`. |
| Flora | `Kolor` | `Ciemnozielony`, `Jasnozielony`, `Czerwony/Brązowy`; np. `Czerwony/Brązowy`. | `includes/class-akwasmart-engine.php:583`; `data/baza_flora_end1.txt:2`. |
| Flora | `WielkośćAkwarium` | Słownik litrażu, 12 surowych kombinacji, `Wszystkie` w 68 rekordach. Przykład `Wszystkie`. | `data/baza_flora_end1.txt:1–202`, `:27`. |
| Flora | `TypAkwarium` | Cztery preferowane typy, 7 surowych kombinacji; np. `Nano / Krewetkarium;Ogólne / Towarzyskie`. | `data/baza_flora_end1.txt:1–202`, `:202`. |

#### Sprzęt i pielęgnacja — wszystkie kolumny

| Baza | Kolumna | Znaczenie, dziedzina i przykład | Źródło |
|---|---|---|---|
| Sprzęt | `id` | `SP0001`–`SP0108`. Nazwa pliku `baza_biotop_end2.txt` jest historyczna. | `data/baza_biotop_end2.txt:1–109`; `includes/class-akwasmart-data.php:230–236`. |
| Sprzęt | `kategoria_glowna` | `Filtracja` (45), `Oświetlenie LED` (29), `Ogrzewanie` (28), `Dozowanie CO2` (6). | `data/baza_biotop_end2.txt:1–109`; `includes/class-akwasmart-data.php:476–484`. |
| Sprzęt | `rodzaj` | `Filtr zewnętrzny`, `Filtr wewnętrzny`, `Filtr kaskadowy`, `Grzałka wewnętrzna`, `Grzałka przepływowa`, `Belka LED`, `Zestaw LED`, `Zestaw CO2`. | `includes/class-akwasmart-data.php:502–521`; `data/baza_biotop_end2.txt:2`. |
| Sprzęt | `producent` | `Aquael`, `Eheim`, `Oase`, `Fluval`, `Chihiros`, `Skylight`, `Aquario`, `Tropica`, `JBL`, `Hydor`. | `data/baza_biotop_end2.txt:1–109`. |
| Sprzęt | `Marka` | Segment `Standard` (45) lub `Premium` (63). | `data/baza_biotop_end2.txt:1–109`. |
| Sprzęt | `produkt` | Model lub nazwa handlowa, 106 różnych etykiet; np. `Ultramax 1000`. | `data/baza_biotop_end2.txt:1–109`. |
| Sprzęt | `Poziom_doświadczenia` | `Początkujący` (60), `Średniozaawansowany` (31), `Zaawansowany` (17). | `data/baza_biotop_end2.txt:1–109`; `includes/class-akwasmart-engine.php:1669–1677`. |
| Sprzęt | `Zdalne_sterowanie` | `tak`, `nie`, wariant `Nie`; widoczne w szczegółach weryfikacji sprzętu. | `data/baza_biotop_end2.txt:2`, `:75`, `:109`; `includes/class-akwasmart-engine.php:2540–2545`. |
| Sprzęt | `WielkośćAkwarium` | Pięć etykiet litrażu, 12 kombinacji, zero `Wszystkie`; np. `Średnie (101L - 250L);Duże (251L - 500L)`. | `data/baza_biotop_end2.txt:1–109`. |
| Sprzęt | `TypAkwarium` | Cztery preferowane typy, 10 kombinacji; np. `Roślinne;Ogólne / Towarzyskie`. | `data/baza_biotop_end2.txt:1–109`, `:100`. |
| Sprzęt | `opis_krotki` | Tekst opisowy, 61 różnych opisów; np. opis zastosowania `Fluval Micro`. | `data/baza_biotop_end2.txt:1–109`; `includes/class-akwasmart-engine.php:1156–1163`, `:2578–2580`. |
| Pielęgnacja | `id` | `CH0001`–`CH0110`. | `data/baza_pielegnacja_end1.txt:1–111`. |
| Pielęgnacja | `kategoria_zadania` | `Start / przygotowanie` (28), `Pielęgnacja na co dzień` (48), `Zapobieganie i kontrola` (34). | `data/baza_pielegnacja_end1.txt:1–111`; `includes/class-akwasmart-data.php:539–546`. |
| Pielęgnacja | `rodzaj_zadania` | `Cięcie i pielęgnacja`, `Karmienie fauny`, `Nawożenie flory`, `Przygotowanie wody`, `Przygotowanie środowiska`, `Leczenie fauny`, `Przeciwdziałanie glonom i niedoborom`, `Testowanie wody`. | `data/baza_pielegnacja_end1.txt:2–108`. |
| Pielęgnacja | `obszar` | `Czyszczenie szyb`, `Narzędzia do cięcia`, `Pokarmy dla krewetek`, `Pokarmy dla ryb`, `Karmniki`, `Nawozy`, `Preparaty wzbogacające`, `Uzdatniacze wody`, `Biostartery`, `Kamienie i skały`, `Media filtracyjne`, `Podłoża`, `Leki`, `Witaminy i minerały`, `Antyglony`, `Suplementy`, `Elektroniczne`, `Kropelkowe`, `Paskowe`. | `data/baza_pielegnacja_end1.txt:2–108`. |
| Pielęgnacja | `producent` | `JBL`, `Tetra`, `Tropica`, `Dennerle`, `Tropical`, `Hikari`, `Sera`, `Aquael`, `Eheim`, `Easy-Life`, `Seachem`, `WIO`, `Fluval`. | `data/baza_pielegnacja_end1.txt:1–111`. |
| Pielęgnacja | `Marka` | `Premium` (72) lub `Standard` (38). | `data/baza_pielegnacja_end1.txt:1–111`. |
| Pielęgnacja | `produkt` | Nazwa handlowa, 106 różnych etykiet; np. `AquaSafe`. | `data/baza_pielegnacja_end1.txt:1–111`, `:51`. |
| Pielęgnacja | `opis` | Tekst zastosowania, 110 różnych wartości; np. opis `AquaSafe`. Czytany przez heurystykę korekt, pomijany w zwykłych kartach. | `data/baza_pielegnacja_end1.txt:51`; `includes/class-akwasmart-engine.php:1580–1593`, `:2582–2584`. |

## 4. Punkty styku z WordPressem

| Punkt styku | Autoryzacja / nonce / efekt | Odpowiednik w v2 |
|---|---|---|
| `register_activation_hook`; bootstrap FORCEFIX; `plugins_loaded` | Aktywacja ustawia źródło i zabezpieczenia. Bootstrap resetuje dane sesji według znacznika buildu; `plugins_loaded` porównuje wersję. `akwasmart-k1k2-r27-final-result.php:32–55`; `includes/class-akwasmart-data.php:45–73`. | Jawna migracja wersji algorytmu i danych, wersjonowanie sesji; osobna aktywacja pakietu. |
| `[akwa_filtr_r37]`, GET i POST tej samej strony | Klucz shortcode bez atrybutów wpływających na wynik. Sam renderer bez kontroli roli. Auth przechwytuje go filtrem `pre_do_shortcode_tag`. POST ma nonce `aqs_form_nonce` dla `aqs_k1k2_form_action`. `akwasmart-k1k2-r27-final-result.php:59–69`, `:122–137`, `:212–287`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:14–17`, `:105–114`. | Trasa doradcy i endpoint odpowiedzi z jedną polityką dostępu, CSRF i właścicielem sesji. |
| `[aqs_portal_menu]`, `template_redirect` | Portal publiczny. `is_page()` + slug + brak logowania powodują `wp_safe_redirect` do logowania z parametrem `redirect_to`. Filtry `aqs_portal_guard_enabled` (domyślnie `true`), `aqs_portal_modules`, `aqs_portal_login_slug`, `aqs_portal_register_slug`. Kod rejestruje shortcode i strażnika dla stron WP, bez własnego szablonu strony. `includes/class-aqs-portal.php:23–58`, `:68–121`. | Router aplikacji, rejestr modułów, polityka dostępu per trasa i bezpieczny powrót po logowaniu. |
| `wp_enqueue_scripts` priorytety `5` i `100` | `5` rejestruje `aqs-design` (`1.1.0`), CSS Doradcy i JS wyszukiwania (`1.1.6`); `100` globalnie kolejkuje `aqs-skin` (`1.1.6`). Shortcody kolejkują potrzebne bundle. `aqs-skin` stylizuje też Astrę, panele użytkownika, sklepu i Auth. `akwasmart-k1k2-r27-final-result.php:64–67`, `:82–135`; `includes/class-aqs-portal.php:159–160`; `assets/css/aqs-skin.css:101–150`, `:274–477`. | Wspólne tokeny i komponenty, osobny CSS Doradcy, lokalne fonty. |
| `wp_ajax_aqs_k1k2_search_records` i `wp_ajax_nopriv_aqs_k1k2_search_records` | `admin-ajax.php`, POST `action`, `nonce`, `session_id`, `query`. Nonce **tak**, logowanie/rola/trial **nie** w handlerze. Zły nonce: HTTP 403 i `Nie udało się potwierdzić żądania.`. Fraza krótka: sukces z `items: []`. `akwasmart-k1k2-r27-final-result.php:68–69`, `:289–305`; `assets/js/search.js:52–72`. | Endpoint katalogu o jawnej polityce dostępu; ten sam kontrakt wyszukiwania dla JS i formularza. |
| `admin_menu`, `admin_notices`, `tools.php?page=aquasmart-dane` | `manage_options`. Widok GET bez nonce. Trzy akcje `admin_post_aqs_k1k2_switch_source`, `admin_post_aqs_k1k2_import_media`, `admin_post_aqs_k1k2_delete_import` z `check_admin_referer('aqs_k1k2_admin_action', 'aqs_admin_nonce')` i `manage_options`. Komunikaty po redirect przez `aqs_notice`, `aqs_message`. `akwasmart-k1k2-r27-final-result.php:73–78`, `:864–949`, `:1005–1053`. | Panel katalogu z autoryzowanymi, transakcyjnymi operacjami importu, aktywacji i wycofania. |
| `aqs_recommendation_output` | Filtr synchroniczny z `($html, $aqs_db, $record_id)` dla kart rankingu, weryfikacji i kart dalszych rekomendacji; bez własnego nonce, w toku renderowania. `akwasmart-k1k2-r27-final-result.php:663–676`, `:775–780`. | Przekazywanie strukturalnych odwołań `(obszar, id)` do usługi ofert, najlepiej w jednym zapytaniu na zestaw wyników. |
| Role/capabilities, Mediateka, options, transients | Portal czyta `aqs_user`, `aqs_shop`; admin źródeł i debug używają `manage_options`; zapis sesji i danych opisany w §3. `includes/class-aqs-portal.php:68–135`; `includes/class-akwasmart-engine.php:96–102`, `:2627–2643`; `includes/class-akwasmart-data.php:127–179`, `:285–307`. | Tożsamość, role i uprawnienia aplikacyjne; magazyn sesji; repozytorium pakietów danych. |
| REST, WP-Cron, poczta, WooCommerce i HTTP do usług zewnętrznych | Brak rejestracji REST, zadań WP-Cron, wywołań `wp_mail`, WooCommerce lub backendowych zapytań HTTP w tym module. JS wykonuje wyłącznie POST do zlokalizowanego `admin-ajax.php` z `credentials: 'same-origin'`; przekierowanie ofert należy do Shop. `akwasmart-k1k2-r27-final-result.php:28–71`, `:122–135`, `:864–873`; `includes/class-aqs-portal.php:23–25`; `assets/js/search.js:52–72`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:136–192`. | Moduł doradcy potrzebuje polityki dostępu i własnego API. Tracker i powiadomienia pozostają kontraktami modułu ofert. |

## 5. Zależności od innych pluginów AquaSmart

| Kierunek | Kontrakt i aktualne zachowanie | Źródło |
|---|---|---|
| Auth → v103 | `[akwa_filtr_r37]` jest blokowany dla gościa i zalogowanego bez dostępu. Najnowsza subskrypcja wg `id DESC`; `active` przepuszcza, `trial` przepuszcza wyłącznie dla `trial_end > teraz`; przy równym lub wcześniejszym końcu Auth ustawia `expired`. Trial tworzony jest na **30 dni** dla `aqs_user`, **90 dni** dla `aqs_shop`. Dozwolone stany Auth: `trial`, `active`, `expired`, `suspended`. Guard nie ma wyjątku dla administratora. | `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:14–58`, `:105–114`; `AquaSmart-plugins/aquasmart-auth/aquasmart-auth.php:130–141`. |
| v103 → portalowe trasy Auth, User i Shop | `/doradca/` → Doradca AquaSmart, user/sklep. `/moje-akwarium/` → Moje zbiorniki, user. `/redfield/` → Kalkulator Redfielda, user. `/panel-sklepu/` → Panel sklepu, sklep. `/dashboard/` → Moje konto, user/sklep. Brak roli portalowej prowadzi do wersji wizytówkowej również po zalogowaniu. Linki Auth `/logowanie/`, `/rejestracja/` są filtrowalne. | `includes/class-aqs-portal.php:68–135`, `:159–215`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-shortcodes.php:19–20`; `AquaSmart-plugins/aquasmart-auth/aquasmart-auth.php:41`; `AquaSmart-plugins/aquasmart-user/includes/class-aqs-user-panel.php:11`, `:106`; `AquaSmart-plugins/aquasmart-user/includes/class-aqs-redfield.php:11`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-panel.php:9`, `:612`. |
| Shop → v103 i pliki | Katalog Shop najpierw pobiera `AquaSmart_Data::all()`. Gdy obszar jest pusty lub brak klasy, próbuje bezpośrednio plików w katalogach wtyczek i uploads, następnie swoich źródeł tabelowych. Sortuje rekordy `strnatcasecmp(id)` i domyślnie ogranicza do **2 000**. Źródło zastępcze może rozjechać listę rekordów Shop i Doradcy. | `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-db.php:503–524`, `:787–877`. |
| v103 → Shop przy prezentacji | `fauna` i `flora` zachowują nazwę. `sprzet` / `biotop` → `equipment`; `pielegnacja` / `chemistry` / `chemia` → `maintenance`. Shop wyszukuje oferty po parze baza i ID. Kolejność: cena niepusta przed pustą, cena rosnąco, `record_name`, `id`. Pokazuje wyłącznie sklep w stanie `aktywny`. Brak ceny: `sprawdź cenę`, cena: dwie cyfry po przecinku i ` zł`. Link `/go/shop/{id}/` otwiera nową kartę, Shop rejestruje klik i przekierowuje do oferty zewnętrznej lub ministrony. | `akwasmart-k1k2-r27-final-result.php:663–676`, `:775–780`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-db.php:374–386`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:56–133`, `:136–199`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-state.php:27`, `:71–82`. |
| v103 → cały frontend | Auth, Shop i User używają `aqs-design`; globalny `aqs-skin` zawiera selektory ich ekranów. Moduł nie pobiera zbiorników, dziennika ani pomiarów z User. | `akwasmart-k1k2-r27-final-result.php:82–119`; `assets/css/aqs-skin.css:274–477`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-shortcodes.php:33–36`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-panel.php:53–55`; `AquaSmart-plugins/aquasmart-user/aquasmart-user.php:33–38`; `includes/class-akwasmart-engine.php:75–93`, `:665–734`, `:856–870`. |

## 6. Zgodność z dokumentacją

W tabeli `Logika` oznacza `AQUAWiedza/dokumentacja-techniczna/logika_scoringu.md`; `Techniczna` i `Changelog` to `dokumentacja_techniczna_pluginu.md` oraz `changelog.md` w `AQUAWiedza/dokumentacja-techniczna/dokumentacja-techniczna/`. `Techniczna ekosystemu` oznacza `AquaSmart-plugins/dokumentacja-techniczna-aquasmart.md`. `SPDI` i `Mapa` to odpowiednio `AUDYT_SPDI_aquasmart_v103.md` oraz `MAPA_INTEGRACJI_CROSS_PLUGIN.md` w `AQUAWiedza/audyty/`.

| Twierdzenie | Źródło (plik dokumentacji + sekcja) | Stan w kodzie (path:line) | Werdykt |
|---|---|---|---|
| Cztery obszary; ok. 184 fauny, 201 flory, 108 sprzętu. | `AQUAWiedza/README.md:19–23`, `:99–123`; Changelog §1.0.3, §1.0.0; `BUILD.txt:3–6`. | Wszystkie cztery aktywne. Faktyczne liczby 184/201/108/110. `includes/class-akwasmart-engine.php:250–263`, `:422–429`, `:1698–1710`; pliki z §3.2. | zgodne |
| Aktualna wersja to 1.0.3 lub 1.0.4; numer buildu w notatce PKT5 to `20260603-001`. | Techniczna nagłówek; SPDI nagłówek; Techniczna ekosystemu §1; `README.txt:1–10`; `dokumentacja/02_OPIS_WERSJI.txt:3–16`; `BUILD.txt:1–13`. | Header i stała `1.1.6`, etykieta z pierwszych dwóch linii `BUILD.txt`, tj. `1.0.3` i `20260603-003`. `akwasmart-k1k2-r27-final-result.php:3–23`. | niezgodne |
| Shortcode, nazwy i układ plików z dokumentacji. | Techniczna §§2–4; Techniczna ekosystemu §§2, 6, 9; `dokumentacja/01_INSTRUKCJA_INSTALACJI.txt:17–24`. | Shortcode istnieje. Rejestruje metodę `render_shortcode`. Bazy mają nazwy `*_end*`; `baza_biotop_end2.txt` zawiera sprzęt. `akwasmart-k1k2-r27-final-result.php:28–30`, `:61–69`; `includes/class-akwasmart-data.php:230–236`; `data/baza_biotop_end2.txt:1–2`. | niezgodne |
| Kompletny przepływ AJAX, odrębne `engine.php`, `scoring.php`, `results.php` i teksty PL w JSON. | Techniczna §§2, 4, 6.2; Logika §8. | Pytanie i odpowiedź renderuje shortcode przez zwykły HTTP POST. AJAX jest wyłącznie dla wyszukiwania. Teksty są w PHP i JS. `akwasmart-k1k2-r27-final-result.php:212–305`, `:346–411`; `includes/class-akwasmart-engine.php:1724–1758`; `assets/js/search.js:52–72`. | niezgodne |
| PHP działa od 7.4, bo `str_starts_with` zastąpiono `strncmp`. | Techniczna §1; Logika §8. | `strncmp` jest używane, lecz parser wymaga PHP 8.0+ z powodu `match`; w nagłówku brak `Requires PHP`. `akwasmart-k1k2-r27-final-result.php:2–7`, `:18`, `:784–799`; `includes/class-akwasmart-engine.php:422–429`. | niezgodne |
| K1 twarde tylko dla fauny; osobne skale `WielkośćAkwarium`. | Logika §§2, 4; Techniczna §4; Changelog §1.0.3; `AQUAWiedza/specyfikacja/specyfikacja_v2.0.md:41–67`. | Fauna i flora używają tej samej skali K1 litrażu i obu filtrów globalnych; sprzęt też przechodzi K1. Osobne są skale pola `Wielkość` organizmu/rośliny. `includes/class-akwasmart-engine.php:772–808`, `:856–870`, `:1913–1934`. | niezgodne |
| Aktywna skala 0–3 bez wag; dawne wagi pozostają w źródle. | Logika §§1, 3; Techniczna ekosystemu §10; `dokumentacja/09_V1_0_AUDITFIX_REGION_TYP_K3.txt:20–21`, `:46–47`; `AUDIT_FAUNA_REGIONFIX.txt:28–30`. | Sumowanie 0–3 aktywne. Metody wag są historyczne. `includes/class-akwasmart-engine.php:1799–1887`, `:2422–2450`, `:2509–2522`. | zgodne |
| K2 zawsze miękkie, K3 informacyjne; maksima `21/12/12/15/6/6`. | Logika §§2, 5; Techniczna §4; Changelog §1.0.0; Techniczna ekosystemu §10; Specyfikacja §§3.1–3.4. | K2 obejmuje filtry twarde i lejek fauny. K3 liczy ranking; maksima przy aktywnych pytaniach `18/9/9/15/12/15`. `includes/class-akwasmart-engine.php:422–447`, `:758–764`, `:811–854`, `:1819–1887`. | niezgodne |
| REGIONFIX i AUDITFIX: `3/2`, nigdy `1`; brak danych `0`; `2` jako ostrzeżenie; Typ pierwszy, obowiązkowy także przy jednej opcji; walidacja opcji K2. | `REGIONFIX_FAUNA_FLORA.txt:11–22`; `REGIONFIX_FAUNA_FLORA_FULLDOC.txt:3–13`; `AUDIT_FAUNA_REGIONFIX.txt:8–19`; `dokumentacja/03_ZASADY_FAUNA_FLORA.txt:3–53`; `dokumentacja/07_HISTORIA_WERSJI.txt:7–22`; `dokumentacja/08_NOTATKA_DLA_UZYTKOWNIKA.txt:6–19`; `dokumentacja/09_V1_0_AUDITFIX_REGION_TYP_K3.txt:7–44`. | Reguły aktywne na prawidłowej ścieżce. Pusta pula kończy się przed Typem; niepoprawny zapisany Typ może wyzerować pulę przed ponownym pytaniem. `includes/class-akwasmart-engine.php:174–183`, `:316–331`, `:360–407`, `:432–451`, `:736–764`, `:1829–1845`, `:1986–1996`. | zgodne |
| Pięć opcji celu, w tym konfiguracja nowego akwarium; test dwumodułowy „Fauna + flora”. | Logika §6; `dokumentacja/01_INSTRUKCJA_INSTALACJI.txt:26–38`; `dokumentacja/06_CHECKLISTA_WDROZENIA.txt:14–26`. | Widoczne pięć opcji z `Znajdź produkt`; brak opcji dwumodułowej. Dla celu spoza menu backend ma domyślnie cztery jednostki. `includes/class-akwasmart-engine.php:104–111`, `:250–272`, `:1698–1710`. | niezgodne |
| `Wszystkie` ukryte; `SPRZETNIEWIEM`; Marka/Producent `3/2`; `SPRZETSCORINGONLY` ma ukrywać zaznaczone rekordy. | Logika §4; Changelog §1.0.3; `BUILD.txt:9–18`. | `Wszystkie` ukryte, `Nie wiem` wyłącza kryterium, binarne punkty aktywne. `SPRZETSCORINGONLY` to komentarz o pominięciu filtra marki/producenta; brak flagi rekordu ukrywającej kartę. `includes/class-akwasmart-data.php:405–436`; `includes/class-akwasmart-engine.php:634–641`, `:883–908`, `:1889–1900`, `:1942–1965`, `:944–961`. | niezgodne |
| Encje dekodowane, diagnostyka warunkowa, jeden komunikat aktywacji. | Logika §8; Changelog §§1.0.1–1.0.3; `BUILD.txt:8–14`. | K1 dekoduje litraż i typ; K2 dekoduje odpowiedź. `error_log` tylko pod `WP_DEBUG`. Notice po aktywacji ma TTL 30 s. `includes/class-akwasmart-engine.php:134–156`, `:492–499`, `:599–615`; `includes/class-akwasmart-data.php:45–52`; `akwasmart-k1k2-r27-final-result.php:73–78`. | zgodne |
| Brak własnych tabel; SPDI Z1 zapis transientu bez obsługi błędu i Z2 zbiorczy DELETE bez sprawdzenia. | SPDI §§2, 3, 6, 8; Mapa §5. | Schemat należy do WP. **Z1 i Z2 nieusunięte**. `includes/class-akwasmart-engine.php:96–102`; `akwasmart-k1k2-r27-final-result.php:32–55`. | zgodne |
| Wspólna klasa `AQS_Assistant_Messages` dla warstwy komunikatów. | SPDI Z3, §§8–10; Mapa V103-Z3. | **Z3 nieusunięte**. Bootstrap ładuje trzy klasy. Pytania, ostrzeżenia i teksty widoków pozostają w silniku i rendererze. `akwasmart-k1k2-r27-final-result.php:28–30`, `:784–859`; `includes/class-akwasmart-engine.php:1724–1758`, `:2045–2087`. | brak w kodzie |
| Brak zależności poza filtrem Shop, inne wtyczki nie czytają plików. | SPDI Z5 i §10; Mapa PS‑03, PS‑05. | Brak bezpośredniego zapisu do tabel innych wtyczek. Shop czyta klasę i pliki awaryjnie, Auth chroni shortcode, portal współdzieli role i nawigację, frontend używa wspólnych assetów. `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-db.php:503–515`, `:787–877`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:14–17`, `:105–114`; `includes/class-aqs-portal.php:68–135`. | niezgodne |
| Jedno źródło danych dla całego renderu, brak mieszania trybów. | SPDI §4 i Z6. | Wybór źródła następuje dla każdego obszaru; brak jednego pliku importu tworzy mieszany zestaw. `imported_missing_fallback` to metadana, nie wpis w logu. `includes/class-akwasmart-data.php:20–37`, `:251–282`. | niezgodne |
| Shop wzbogaca kartę przez filtr; wyświetlenie zależy od aktywnej subskrypcji sklepu. | Mapa PS‑02, PS‑03; Techniczna ekosystemu §5. | Filtr i normalizacja działają synchronicznie. Opisany odczyt subskrypcji w ścieżce wyświetlenia/przekierowania zastąpiono warunkiem stanu `aktywny`. `akwasmart-k1k2-r27-final-result.php:663–665`, `:775–780`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:56–90`, `:149–155`. | niezgodne |
| Fauna uwzględnia GH/KH, wymagania stadne i typ słodkowodny/morski/brackish; flora światło i trudność w punktacji; pielęgnacja zgodność z fauną/florą. | `AQUAWiedza/specyfikacja/specyfikacja_v2.0.md:41–86`; `AQUAWiedza/testy/CHECKLISTA_E2E_PILOT.md:504–517`. | Wdrożone pola i skale z §3 oraz §8. GH/KH, wymagania stadne, reżim zasolenia i światło nie występują w schemacie wejściowym. Trudność jest informacją. Współistnienie to reguły kategorii i kolejności pliku. `data/baza_fauna_end1.txt:1`, `data/baza_flora_end1.txt:1`, `data/baza_pielegnacja_end1.txt:1`; `includes/class-akwasmart-engine.php:422–447`, `:1625–1643`, `:2241–2273`. | brak w kodzie |
| Dziennik i parametry zbiornika staną się kontekstem rekomendacji w v3; do pilota tracker bez webhooka, wyłączenia LiteSpeed w backlogu. | `AQUAWiedza/zakres-prac-v2.0.md:41–55`, `:74–106`, `:161–170`; specyfikacja §§1–2 opisuje webhook. | Doradca ma własne odpowiedzi; tracker Shop liczy klik i przekierowuje. W v103 brak konfiguracji wyłączeń LiteSpeed. **[HIPOTEZA]** Wyłączenia skonfigurowano poza repozytorium. `includes/class-akwasmart-engine.php:75–93`, `:104–148`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:136–192`; `akwasmart-k1k2-r27-final-result.php:58–71`, `:864–873`. | zgodne |
| Dwie ścieżki oceny, import per obszar, dogrywka z regułą 0–2 kryteriów, portal ról i globalna skórka. | Brak aktualnego kontraktu w Logice §§2, 5–7, Technicznej §§2–4 i SPDI §§4–5; wzmianki o skórce w mapowaniu produkcji. | Mechanizmy istnieją i wpływają na kolejność, procent, dostęp i UX. `includes/class-akwasmart-engine.php:983–1083`, `:1225–1471`; `includes/class-akwasmart-data.php:257–282`; `includes/class-aqs-portal.php:68–135`, `:159–215`; `akwasmart-k1k2-r27-final-result.php:110–119`. | brak w dokumentacji |
| Raport sprawdza składnię i źródło; historyczne arkusze `37/37` i `90/90` są dowodem aktualnej regresji. | `dokumentacja/04_AUDYT_ZGODNOSCI.txt:35–45`; `dokumentacja/05_RAPORT_TESTOW_TECHNICZNYCH.txt:20–53`; Logika §9. | Repo zawiera lint trzech plików i dwie asercje zbiorcze oparte o fragmenty tekstu źródłowego. Skrypt shell uruchamia tylko test REGIONFIX; portal nie jest w lincie. Brak testu zachowania lub zapisanego wyniku E2E. `testy/test_skladni_php.sh:1–7`; `testy/test_regionfix_source.php:20–51`; `testy/test_v1_0_auditfix_source.php:3–27`; `AQUAWiedza/testy/CHECKLISTA_E2E_PILOT.md:475–533`. | niezgodne |

## 7. Problemy

| Severity | Opis | path:line | Wpływ |
|---|---|---|---|
| wysoki | Import nadpisuje aktywne pliki po kolei. Niekompletny import może nadpisać część starszego pakietu, a przy błędzie `copy` nie dochodzi do aktualizacji manifestu. Przełącznik weryfikuje istnienie plików, bez walidacji zawartości. | `includes/class-akwasmart-data.php:118–125`, `:147–192`, `:257–275`; `akwasmart-k1k2-r27-final-result.php:1005–1028` | Różne wersje obszarów w jednym wyniku, pusty obszar może zostać aktywowany. |
| wysoki | Parser nie sprawdza schematu, unikatowości ID ani enumów. Regex dopasowuje zapis mojibake `ï»¿`, więc prawdziwy UTF‑8 BOM przed `id` pozostaje w nagłówku; wszystkie wiersze mogą zostać pominięte. | `includes/class-akwasmart-data.php:335–360`, `:575–582` | Niewidoczny ubytek danych, pierwsze wyszukanie powtórzonego ID może wskazać inny rekord niż ranking. |
| wysoki | „Produkt poprawiający dopasowanie” jest wybierany po fragmencie tekstu lub pierwszej pasującej grzałce; procent jest stały `90`. Kod nie sprawdza kierunku zmiany temperatury ani potrzeby dodania CO₂. Dopasowanie tekstu `ph control` może wskazać urządzenie do pomiaru/sterowania. | `includes/class-akwasmart-engine.php:1561–1622`; `data/baza_pielegnacja_end1.txt:100` | Sugestia korekty może być nieadekwatna do warunków. Karta daje jej pozór obliczonej zgodności. |
| średni | K1 nie sprawdza zgodności celu, litrażu i typu z podanymi opcjami. K2 sprawdza opcję, ale formularz nie przekazuje wersji pytania. | `includes/class-akwasmart-engine.php:104–148`, `:166–193`, `:1698–1710`; `akwasmart-k1k2-r27-final-result.php:238–255`, `:354–369` | Nieznany cel rozgałęzia się na cztery obszary. Stare żądanie może zostać przypisane kolejnemu pytaniu, jeżeli wartość jest wspólna, np. `Nie wiem`. |
| średni | Publiczny wariant autocomplete sprawdza nonce, bez kontroli uprawnień Auth. Brak sesji daje zakres wszystkich czterech baz. Shortcode udostępnia pełny indeks nazw, ID i producentów w HTML/JS. | `akwasmart-k1k2-r27-final-result.php:68–69`, `:126–135`, `:289–305`; `includes/class-akwasmart-engine.php:58–64`; `includes/class-akwasmart-data.php:630–661` | Polityka ochrony katalogu różni się od polityki doradcy. Produkcyjnej dostępności nonce dla gościa nie potwierdzono. |
| średni | Sesja identyfikowana samym UUID, bez właściciela i wersji odpowiedzi. Zapis wyniku jest bezwarunkowy. UI resetuje przez nową sesję, pozostawiając starą do TTL. | `includes/class-akwasmart-engine.php:15–55`, `:75–102`; `akwasmart-k1k2-r27-final-result.php:226–235` | Przy równoległym POST ostatni zapis może nadpisać wcześniejszy. Stary formularz pozostaje używalny do wygaśnięcia stanu i nonce. |
| średni | K1 akceptuje większy zbiornik w faunie/florze, ale weryfikacja daje `1` za sąsiedni większy i `0` za dalszy. Flora nano dostaje z powrotem opcję wysokiej rośliny. | `includes/class-akwasmart-engine.php:559–592`, `:776–808`, `:1415–1435`; `data/baza_flora_end1.txt:157` | Dwa tryby mogą dać różne oceny temu samemu rekordowi. `FL0156` może wygrać dobór `Wysoka (>30 cm)` dla nano. |
| średni | Błędny Typ zapisany w sesji może odfiltrować pulę przed gałęzią ponownego pytania. Nieznany Typ z importu może pojawić się w opcjach, zostać zapisany i powtarzać pytanie Typ. | `includes/class-akwasmart-engine.php:316–339`, `:360–407`, `:432–451`, `:595–643`, `:736–764` | Niepełna ochrona AUDITFIX dla zmian danych i uszkodzonych sesji. |
| średni | Karcie produktu korygującego renderer przekazuje bazę ocenianego rekordu. | `includes/class-akwasmart-engine.php:1580–1620`; `akwasmart-k1k2-r27-final-result.php:563–567`, `:670–676`, `:775–780` | Np. `SP0057` z korekty fauny jest szukany przez Shop w `fauna`, przez co poprawna oferta sprzętu nie trafia na kartę. |
| średni | Pusta pula dostaje `Gotowe dopasowanie`; weryfikacja bez pytań daje `100%`; `count` paczki weryfikacji to zawsze `1`. | `includes/class-akwasmart-engine.php:911–921`, `:1283–1289`, `:1338–1355`, `:1395–1397`; `akwasmart-k1k2-r27-final-result.php:452–496`, `:793–821` | Komunikat pustego wyniku i komunikat pełnej zgodności mogą przeczyć faktycznemu zakresowi sprawdzenia. `count` odstaje od tablicy kart przy alternatywie lub korekcie. |
| średni | `set_transient` oraz `DELETE` nie sprawdzają wyniku. FORCEFIX ustawia znacznik przed czyszczeniem. Raw SQL działa wyłącznie na `wp_options`; `_` w `LIKE` jest jokerem. | `includes/class-akwasmart-engine.php:100–102`; `akwasmart-k1k2-r27-final-result.php:34–55` | Możliwe błędy SPDI Z1/Z2, stare sesje po nieudanym resecie lub w odrębnym cache. Wzorzec SQL jest szerszy niż literalny prefiks. |
| średni | Autocomplete oblicza etykiety, sortuje i wysyła indeks **603** rekordów na każdym renderze Doradcy. Generowanie opcji wielokrotnie przelicza kandydatów i lejek; dogrywka liczy profile wewnątrz porównań sortowania. Shop może wykonać osobne zapytanie na każdą kartę, a liczba kart rośnie z remisem. | `akwasmart-k1k2-r27-final-result.php:122–135`, `:775–780`; `includes/class-akwasmart-data.php:20–37`, `:630–661`; `includes/class-akwasmart-engine.php:492–500`, `:923–944`, `:1805–1807`, `:2111–2116`, `:2147–2174`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:56–73` | Koszt każdego kroku rośnie z katalogiem. Konkretny czas odpowiedzi i obciążenie produkcyjne wymagają pomiaru. |
| niski | Po skróceniu pola poniżej 3 znaków JS czyści listę, lecz nie zwiększa `requestCounter`. Trwający AJAX może ponownie wstawić starą listę. | `assets/js/search.js:90–120` | Nieaktualne sugestie po skasowaniu frazy. |
| niski | `test_regionfix_source.php` używa `\n` w dopasowaniu wieloliniowym. W lokalnym checkout silnik ma CRLF. Drugi test szuka także komentarzy i jego literał wieloliniowy dzieli zakończenia linii z checkoutem. | `testy/test_regionfix_source.php:20–35`; `testy/test_v1_0_auditfix_source.php:3–20`; `testy/test_skladni_php.sh:4–7` | Porównanie bajtowe pierwszego testu daje lokalnie 7/8. Drugie daje 8/8. Wynik zależy od reprezentacji źródła; oba sprawdzają tekst, a nie zachowanie. |
| niski | Różne wersje w nagłówku, UI i notice; brak deklaracji minimalnego PHP. | `akwasmart-k1k2-r27-final-result.php:3–23`, `:73–78`, `:784–799`; `BUILD.txt:1–13` | Trudniej powiązać zgłoszenie z algorytmem i pakietem danych. Parser PHP 7.4 zatrzyma się na `match`. |

## 8. Reguły biznesowe do zachowania w v2

### 8.1 Protokół sesji i K1

- **Wejście formularza:** `aqs_form_nonce`, `aqs_session_id`; jedna akcja z priorytetem `aqs_reset` → para `aqs_pick_area` / `aqs_pick_record_id` → `aqs_answer` → `aqs_search` i `aqs_search_query` → odczyt stanu. GET, POST bez ID albo nieprawidłowy nonce inicjuje stan `k1`; zły nonce pokazuje „Nie udało się potwierdzić formularza. Rozpocząłem nową sesję.”. Wygasła sesja zwraca `missing_session`, „Sesja wygasła. Zacznij od nowa.”, a `safe_payload` tworzy nową. (`akwasmart-k1k2-r27-final-result.php:212–287`, `:308–314`; `includes/class-akwasmart-engine.php:8–55`)
- **Stany:** `k1` → `k2` → `done`; po wybraniu rekordu `k1` przechodzi bezpośrednio do `k2` z jednostką `specific_{area}`. `done` zwraca zapisany wynik przy kolejnej odpowiedzi. Po zakończeniu każdej jednostki zwiększany jest `current_unit_index`. Postęp K1 `min(42, 10 + 10×answered_steps)`; K2 `42 + round(50×liczba_ukończonych/max(1, liczba_jednostek))`; `done` = `100`. Stan `answered_steps` zwiększa się po przyjęciu odpowiedzi K1, także po wybraniu rekordu. (`includes/class-akwasmart-engine.php:15–45`, `:217–245`, `:911–921`, `:969–980`, `:1225–1254`, `:1350–1359`, `:2615–2625`)
- **K1 w UI:** „W czym mam Ci teraz pomóc?” → `Fauna`, `Flora`, `Sprzęt`, `Pielęgnacja`, `Znajdź produkt`. Cztery cele doboru zadają kolejno „Jaką wielkość akwarium bierzemy pod uwagę?” i „Jaki typ akwarium bierzemy pod uwagę?”. Litraże to unia fauny, flory i sprzętu, najpierw kolejność z §3.2, potem dodatkowe wartości w kolejności napotkania; `Wszystkie` jest usuwane. Typy pochodzą z obszaru celu, dla pielęgnacji z sumy fauny, flory i sprzętu, z preferencjami z §3.2. Typy nie są ograniczane wybranym litrażem. `Jeszcze nie wiem` występuje w filtrze, bez takiej opcji w bazach `bundled`. (`includes/class-akwasmart-engine.php:250–297`, `:856–870`, `:1690–1710`; `includes/class-akwasmart-data.php:405–473`; `data/baza_fauna_end1.txt:1–185`; `data/baza_flora_end1.txt:1–202`; `data/baza_biotop_end2.txt:1–109`)
- **Walidacja:** K1 oczyszcza tekst i dekoduje encje HTML5 w litrażu i typie. K2 oczyszcza, dekoduje encje HTML5 i wymaga ścisłej obecności w opcjach bieżącego pytania `select`. Pusty lub niedozwolony wybór: „Wybierz jedną z dostępnych odpowiedzi.”. Wybrany rekord wymaga niepustych `area` i `record_id`, a następnie ścisłego wyszukania po ID. (`includes/class-akwasmart-engine.php:104–183`; `includes/class-akwasmart-data.php:575–582`)

### 8.2 Pule i pytania K2

Źródła pseudokodu: `includes/class-akwasmart-engine.php:300–447`, `:492–663`, `:665–908`, `:1690–1718`; `includes/class-akwasmart-data.php:367–374`, `:383–402`, `:476–572`.

```text
JEDNOSTKI(cel):
    Fauna -> [fauna]; Flora -> [flora]; Sprzęt -> [sprzet]
    Pielęgnacja -> [pielegnacja]
    Znajdź produkt z wybranym rekordem -> jeden obszar rekordu
    pozostałe przypadki -> [fauna, flora, sprzet, pielegnacja]

ZGODNY_K1(rekord, obszar, litraż, typ):
    jeśli istnieje nie-null pole WielkośćAkwarium i litraż != "":
        L = split_multi(pole)
        jeśli L jest puste: odrzuć
        jeśli "Wszystkie" w L: zalicz warunek litrażu
        inaczej jeśli fauna/flora i litraż występuje na pięciostopniowej skali:
            wymagaj przynajmniej jednego znanego tokenu z indeksem <= indeks litrażu
        inaczej:
            wymagaj dokładnej obecności litrażu w L
    jeśli istnieje nie-null pole TypAkwarium i typ nie jest "" ani "Jeszcze nie wiem":
        wymagaj dokładnej obecności typu w split_multi(TypAkwarium)
    zaakceptuj

PLAN(aktywna_jednostka):
    fauna bez zaakceptowanego Typ -> [Typ]
    fauna Ryba -> [Wielkość, Strefa, Temperament, Temperatura, pH, Region]
    fauna Skorupiak albo Ślimak -> [Wielkość, pH, Region]
    fauna z obcą wartością Typ -> [Typ]
    flora -> [Wielkość, Strefa, CO2, Kolor, Region]
    sprzet -> [kategoria_glowna, rodzaj, Marka, producent]
    pielegnacja -> [kategoria_zadania, rodzaj_zadania, obszar, Marka, producent]

PULA(jednostka, warstwa):
    jeśli mode == specific: zwróć [utrwalony rekord] albo []
    C = rekordy obszaru, w kolejności pliku, zgodne z K1
    jeżeli specific_record jest z tego obszaru: ogranicz po ID
    zastosuj ścisłą zgodność trim dla udzielonych odpowiedzi twardych:
        fauna: Typ
        flora: brak
        sprzet: kategoria_glowna, rodzaj
        pielegnacja: kategoria_zadania, rodzaj_zadania, obszar
    # Pula rankingu jawnie bierze tylko kolumny twarde.
    # Pula pytań sprzętu/pielęgnacji pomija Marka, producent i __experience.
    jeśli fauna:
        dla kryterium PLAN z wyjątkiem Typ, w podanej kolejności:
            jeśli odpowiedź pusta albo "Nie wiem": kontynuuj
            P = punkty kryterium dla rekordów bieżącego C
            jeśli jakikolwiek rekord ma >= 2:
                zachowaj rekordy z P != 0
            # Bez żadnego wyniku >= 2 pozostaw C.
    zwróć C

NASTĘPNE_PYTANIE(jednostka):
    C = PULA(jednostka, pytania)
    jeśli zwykły dobór i C puste:
        jeśli PULA(jednostka, ranking) niepusta:
            zakończ ranking na tej puli z notą o szerszym dopasowaniu
        inaczej: zakończ jednostkę jako Brak dopasowań
    dla kolumny z PLAN:
        pomiń już zapisaną odpowiedź
        wyjątek: zapisany fauna.Typ spoza {Ryba, Skorupiak, Ślimak}
        zbuduj opcje wg reguł poniżej
        jeśli fauna.Typ ma dokładnie 1 opcję: pokaż ją obowiązkowo
        jeśli liczba opcji w [2, 8]: pokaż pytanie
        inaczej: pomiń, bez automatycznego zapisywania odpowiedzi
    gdy brak następnego pytania:
        ranking na PULA(jednostka, ranking), awaryjnie na C
        w trybie specific zakończ weryfikację rekordu

PRZYJMIJ_K2(opcja):
    odtwórz aktualne pytanie; zaakceptuj tylko opcję z jego listy
    zapisz w answers i summary; przelicz pulę
    jeśli pula pusta: cofnij zapis i zgłoś
        "To zawężenie nie daje żadnych wyników. Wybierz inną odpowiedź."
    wyjątek: flora Strefa, CO2, Kolor, Region zachowują odpowiedź mimo pustej puli
```

Opcje w szczegółach:

| Obszar | Źródło i kolejność opcji | Źródło kodu |
|---|---|---|
| Fauna | Różne niepuste wartości z bieżącej puli, sort PHP `SORT_NATURAL \| SORT_FLAG_CASE`, potem próba opcji i zachowanie tylko tych z niepustą pulą. Kolejność preferowana: `Typ` = `Ryba`, `Skorupiak`, `Ślimak`; `Wielkość`/`Temperatura`/`pH` według skali z §3.2; `Strefa` = `Dolna`, `Środkowa`, `Górna`. Pozostałe wartości są dopisywane po preferowanych. `Temperament` i `Region` pozostają naturalnie posortowane. Dodaj `Nie wiem` dla `Strefa`, `Temperatura`, `pH`, `Temperament`, `Region`, gdy istnieje choć jedna rzeczywista opcja. | `includes/class-akwasmart-engine.php:492–520`, `:557–627`, `:647–663`; `includes/class-akwasmart-data.php:383–402`. |
| Flora | Zawsze pełny słownik z §3.2 w podanej tam kolejności. `Wielkość` bez `Nie wiem`, pozostałe pola z `Nie wiem` na końcu. Wcześniejsza próba wycięcia `Wysoka (>30 cm)` dla typu nano lub litrażu nano jest nadpisywana. Wcześniejsze odpowiedzi flory nie ograniczają puli K2. | `includes/class-akwasmart-engine.php:557–592`, `:682–695`. |
| Sprzęt | Preferowane kategorie i rodzaje w kolejności z §3.2; rodzaj jest brany z kategorii, jeżeli wybrano kategorię. Zostają wartości obecne w aktualnej puli i z niepustym wynikiem próbnym. `Marka`: `Standard`, `Premium`, pozostałe za nimi; producent sortowany naturalnie. Dla obu `Nie wiem` po co najmniej jednej rzeczywistej opcji. | `includes/class-akwasmart-data.php:476–537`; `includes/class-akwasmart-engine.php:502–538`, `:595–643`. |
| Pielęgnacja | Kategorie: `Start / przygotowanie`, `Pielęgnacja na co dzień`, `Zapobieganie i kontrola`, potem reszta; typ zadania z kategorii, gdy wybrana. Rodzaj zadania, obszar, marka i producent sortowane naturalnie. Próba wartości ma pozostawić niepustą pulę. Marka i producent z `Nie wiem` po co najmniej jednej wartości. | `includes/class-akwasmart-data.php:383–402`, `:539–572`; `includes/class-akwasmart-engine.php:540–555`, `:595–643`. |

**REGIONFIX i AUDITFIX.** `Region` traktuje zgodny region jako 3 pkt, inny jako 2 pkt, brak jako 0, bez możliwości 1. Dla fauny odpowiedź dokładna wśród kandydatów daje `hasStrong`, ale wszystkie inne niepuste regiony nadal przechodzą lejek. `Region=2` trafia do ostrzeżeń. AUDITFIX zaczyna od `Typ` bez inferencji, wymusza pojedynczą dostępną opcję, rozgałęzia ścieżkę dopiero po odpowiedzi i waliduje listę K2. `Typ` jest filtrem, bez udziału w liczniku punktów. `SPRZETSCORINGONLY` realizuje tę samą zasadę preferencji dla `Marka` i `producent` w obu obszarach produktowych. (`includes/class-akwasmart-engine.php:174–183`, `:355–447`, `:811–854`, `:883–908`, `:1829–1886`, `:1941–1996`)

### 8.3 K3 — punktacja i kolejność

| Klasa kryterium | Wynik | Kryteria i źródło |
|---|---|---|
| Brak odpowiedzi | Puste i `Nie wiem` usuń z listy punktowanej. Puste pole rekordu dla aktywnego kryterium daje 0. | `includes/class-akwasmart-engine.php:1870–1911`. |
| Po skali | Dokładnie `3`; odległość 1 → `2`; odległość 2 → `1`; większa odległość lub nieznany element → `0`. Dokładna równość jest sprawdzana przed indeksami skali. | `Wielkość` na osobnych skalach fauny/flory, `Temperatura`, `pH`, `CO2`, `Temperament` wg §3.2. `includes/class-akwasmart-engine.php:1913–1941`, `:1999–2029`. |
| Kategoryczne preferencje | Dokładnie `3`, inna niepusta wartość `2`, brak `0`. | `Region`, `Marka`, `producent`. `includes/class-akwasmart-engine.php:1942–1965`, `:1986–1996`. |
| Zgodność ścisła | Dokładnie `3`, różnica `0`. Aktywne odpowiedzi twarde dają 3 wszystkim rekordom, które przez nie przeszły. | `Strefa`, `Kolor`, `kategoria_glowna`, `rodzaj`, `kategoria_zadania`, `rodzaj_zadania`, `obszar` i domyślne pola. `includes/class-akwasmart-engine.php:758–764`, `:1946–1975`. |

```text
K = kryteria z PLAN w jego kolejności, z udzieloną aktywną odpowiedzią, bez Typ
V = wektor punktów kryteriów K
GŁÓWNY = (sum(V) malejąco, liczba 3 malejąco, liczba 1 rosnąco, V leksykograficznie malejąco)

dopasowanie_litrażu:
    0 jeśli brak litrażu użytkownika lub brak tokenów rekordu
    przy dokładnej obecności litrażu:
        n = liczba tokenów rekordu po wyłączeniu "Wszystkie"
        n <= 1 -> 3; n == 2 -> 2; n >= 3 -> 1
    bez dokładnej obecności: "Wszystkie" -> 1; inaczej -> 0

dopasowanie_typu:
    0 jeśli typ niepodany, "Jeszcze nie wiem" lub brak dokładnego tokenu
    dla obecnego tokenu: liczba tokenów 1 -> 3; 2 -> 2; >= 3 -> 1

B = [dopasowanie_litrażu, dopasowanie_typu]
DOGRYWKA = (sum(B) malejąco, liczba 3 malejąco, liczba 1 rosnąco, B leksykograficznie malejąco)
SORTUJ po GŁÓWNY, następnie DOGRYWKA, następnie strcmp(id) rosnąco
procent = 100 dla pustego K, inaczej round(100 * sum(V) / (3 * len(K)))

jeśli liczba rekordów < 2 lub sum(V) lidera != sum(V) drugiego: mode = single
w przeciwnym razie jeśli różnią się dalsze elementy GŁÓWNY: mode = tiebreak
w przeciwnym razie:
    gdy len(K) <= 2:
        n = długość początkowej grupy z równym GŁÓWNY
        dla n > 1: partial_tie jeśli n < liczba rekordów, inaczej full_tie
    gdy len(K) > 2:
        gdy DOGRYWKA lidera i drugiego się różni: mode = tiebreak
        inaczej n = długość początkowej grupy z równym GŁÓWNY i DOGRYWKA
        partial_tie jeśli n < liczba rekordów, inaczej full_tie

limit_wyjścia = 3 dla single/tiebreak, max(3, n) dla partial_tie/full_tie
shortlist_wewnętrzna = pierwsze max(5, limit_wyjścia) wierszy
```

`round` to całkowite zaokrąglenie PHP do najbliższej liczby, z połówkami w górę dla dodatnich wartości. Ostatni `strcmp(id)` porządkuje, ale nie rozstrzyga remisu biznesowego. Przy maksymalnie dwóch kryteriach dogrywka wciąż ustawia kolejność w grupie ex aequo. W dogrywce dokładny litraż ma znaczenie także po przepuszczeniu większego zbiornika przez K1. Pielęgnacja w bieżącym TSV ma oba wyniki dogrywki równe 0. (`includes/class-akwasmart-engine.php:923–961`, `:983–1083`, `:1819–1886`, `:2089–2145`, `:2147–2239`, `:2241–2327`; `data/baza_pielegnacja_end1.txt:1`)

| Element wyniku | Dokładna reguła | Źródło |
|---|---|---|
| Maksimum teoretyczne widocznego score | `Ryba` 18; `Skorupiak` / `Ślimak` 9; `Flora` 15; `Sprzęt` 12; `Pielęgnacja` 15. Maksimum konkretnej sesji `3×liczba aktywnych kryteriów`. | `includes/class-akwasmart-engine.php:422–447`, `:1848–1886`. |
| `fit_label` | ≥85 `Dopasowanie bardzo wysokie`; ≥70 `Dopasowanie wysokie`; ≥50 `Dopasowanie warunkowe`; poniżej 50 `Dopasowanie słabe`. **Brak progu odrzucenia wyniku** na etapie kart K3. | `includes/class-akwasmart-engine.php:944–961`, `:2076–2087`. |
| `reasons`, `warnings` | `3` → powód, `2` → powód z wyjątkiem `Region`, `Marka`, `producent`, które trafiają do uwag; `1` → ostrzeżenie; `0` → bez komunikatu. Maks. 4 powody i 3 ostrzeżenia per rekord; karta alternatywy obcina powody do 3. Szablony: „pełna zgodność”, „zgodność częściowa”, „zgodność słaba”; dla Regionu „zgodny region” / „inny region”; dla marki/producenta „zgodna” / „inna”. | `includes/class-akwasmart-engine.php:1819–1866`, `:2045–2073`; `akwasmart-k1k2-r27-final-result.php:715–724`. |
| `verdict` i status | Pierwszy `Rekomendacja główna`, kolejne `Mocna alternatywa`. Remisujący liderzy `Wynik ex aequo`. Paczka ma `Gotowe dopasowanie`, `Wynik końcowy: ex aequo` dla `full_tie`, albo `Brak dopasowań`. | `includes/class-akwasmart-engine.php:911–921`, `:944–978`. |
| Podstawa oceny | Zawiera aktywne kryteria i dwa kryteria dogrywki, opis procentu jako zgodności z daną ścieżką, ostrzeżenie o niskiej rozdzielczości dla 1–3 kryteriów oraz uwagi o remisie. Przy 0 kryteriów powód informuje, że brakowało sensownego pytania różnicującego. | `includes/class-akwasmart-engine.php:1848–1855`, `:2329–2355`; `akwasmart-k1k2-r27-final-result.php:824–859`. |
| Szersza pula | Jeżeli pula pytań jest pusta, a pula rankingu istnieje: „Brak pełnego exact-match po odpowiedziach. Ranking został policzony na szerszej puli kandydatów zgodnych z filtrami twardymi.”. Obecna implementacja stosuje te same ograniczenia do obu pul w zwykłej ścieżce `bundled`, więc ta gałąź jest rezerwowa. | `includes/class-akwasmart-engine.php:316–350`, `:665–734`, `:883–908`, `:936–940`. |

### 8.4 „Znajdź produkt” — osobny algorytm

1. **Wyszukaj.** „Wpisz co najmniej 3 znaki i wybierz konkretny rekord z listy.”. Backend sprawdza substring po `trim` i `mb_strtolower`/`mb_strlen`, z fallbackiem `strtolower`/`strlen`. Fauna/flora: ID, nazwy PL i łacińska. Sprzęt/pielęgnacja: ID, produkt, producent. Wynik `area`, `record_id`, `label`, `area_label`; sort `strcmp(label)`, limit **20**. W JS odczekaj **150 ms**; najpierw `trim().toLowerCase().includes()` na pełnym indeksie zawierającym także etykietę, `slice(0,20)`. Brak wyników lokalnych uruchamia AJAX; ręczny POST używa wyłącznie backendu. Brak polskiej transliteracji, tokenizacji i normalizacji NBSP. `Znajdź produkt` przeszukuje wszystkie cztery obszary. (`includes/class-akwasmart-engine.php:266–272`, `:1713–1718`; `includes/class-akwasmart-data.php:9–17`, `:585–681`; `assets/js/search.js:43–72`, `:90–122`; `akwasmart-k1k2-r27-final-result.php:269–305`)
2. **Zamroź wybrany wiersz w sesji.** Pokaż `Cel`, `Wybrany rekord`, `Obszar` i niepuste „Parametr optymalny — …” z mapy weryfikacji. Fauna pyta o `WielkośćAkwarium`, `TypAkwarium`, `pH`, `Temperatura`; flora o `WielkośćAkwarium`, `TypAkwarium`, `CO2`; sprzęt o `WielkośćAkwarium`, `TypAkwarium`; pielęgnacja o nic. Litraż to unia K1, typy z obszaru, pH/temperatura z całej fauny, CO₂ z całej flory. Pytanie pomijane przy ≤1 opcji. Pytanie „Optymalnie dla tego rekordu: …” cytuje wartość wiersza, jeśli obecna. (`includes/class-akwasmart-engine.php:1225–1321`, `:1646–1687`)
3. **Policz warunki:** za każde niepuste pole zwiększ mianownik o 3. Dla litrażu dokładny token albo `Wszystkie` → `3`; minimalny dystans do tokenu równy 1 → `1`; inaczej `0`. Typ dokładny → `3`, inny → `0`. `pH`, `Temperatura`, `CO2`: dokładnie `3`, sąsiad na skali `2`, przy większym dystansie lub poza skalą `0`. Pozostałe pola domyślnie `3/0` według równości. `3` = `zgodne`; `2` = `częściowo zgodne`; `1` = `wymaga uwagi`; `0` = `niezgodne`. Punkty ≥2 tworzą powód; 1/0 tworzy ostrzeżenie i niedopasowanie. Procent `round(100×punkty/maksimum)`, przy zerze warunków `100`. Werdykt ≥90 `Zgodny`, ≥70 `Zgodny warunkowo`, poniżej 70 `Słabe dopasowanie`. (`includes/class-akwasmart-engine.php:1363–1490`)
4. **Poniżej 70% szukaj najwyżej jednej alternatywy i jednej korekty.** Alternatywa z tej samej bazy, innego ID, z wynikiem **≥90%**. Klasa semantyczna: fauna ten sam niepusty `Typ`; flora ta sama niepusta `Strefa` **lub** `Wielkość`; sprzęt ta sama niepusta kategoria i rodzaj; pielęgnacja ta sama niepusta kategoria i rodzaj zadania, a jeśli rekord ma obszar, także ten sam obszar. Wygrywa największy procent, przy równości pierwszy rekord pliku. Etykieta `Lepsza alternatywa`. (`includes/class-akwasmart-engine.php:1338–1347`, `:1496–1558`)
5. **Korekta, w kolejności pH → temperatura → CO₂.** Niedopasowanie pH: dla optimum zawierającego `kwaśna` szukaj kolejno `acid buffer`, `ph control`, `easybalance`, w przeciwnym razie `easybalance`, `ph control`, `equilibrium`. Przy niedopasowaniu temperatury zwróć pierwsze `Ogrzewanie` ze zgodnym tokenem litrażu, `Wszystkie` lub pustą listą litrażu. Przy niedopasowaniu CO₂ szukaj `co2 system`, `co2 plus`. Frazy są szukane od początku pliku dla każdej frazy, bez uwzględnienia wielkości liter w `produkt`, `obszar`, `rodzaj_zadania`, `opis`. Karta ma `Produkt poprawiający dopasowanie` i stałe `90%`. (`includes/class-akwasmart-engine.php:1561–1622`)
6. **Produkty współistniejące.** Maksymalnie pierwsze 2 z pliku pielęgnacji. Dla `Skorupiak`: `Pokarmy dla krewetek`; dla pozostałej fauny, także ślimaków: `Pokarmy dla ryb`; dla flory: `Nawozy` lub `Preparaty wzbogacające`; dla sprzętu i pielęgnacji pusto. Wyświetlane są ID i nazwa. (`includes/class-akwasmart-engine.php:1625–1643`; `akwasmart-k1k2-r27-final-result.php:648–657`)

### 8.5 Wyjście, identyfikatory i prezentacja

- Payload: `session_id`, `revision`, `stage`, `prompt`, `summary`, `summary_groups`, `progress`, `done_message`, `results`, `plan_overview`, `debug`. Prompt ma `key`, `type`, `question`, `options` lub `placeholder`; K2 dodaje `column`, `context`, `transition`. Podsumowanie pomija puste wartości i grupuje do `Ustalenia główne`, `Fauna`, `Flora`, `Sprzęt`, `Pielęgnacja`. K4 to nazwa warstwy końcowego widoku. (`includes/class-akwasmart-engine.php:250–294`, `:378–417`, `:1257–1277`, `:2615–2694`; `akwasmart-k1k2-r27-final-result.php:414–445`)
- Paczka rankingu ma `count`, `items`, `status`, `tie_notice`, `unit_area`, `ranking_mode`, `ranking_note`, `basis`. Karta zawiera `id`, `label`, `verdict`, `fit_label`, `score`, `match_percent`, `reasons`, `warnings`, `details`. Nazwa fauny i flory to `polska lub łacińska` + ` - ` + łacińska. Sprzęt i pielęgnacja `produkt - producent`. Grupę nazywa się wg obszaru; sprzęt preferuje wybrany `rodzaj`, potem `kategoria_glowna`, a pielęgnacja `rodzaj_zadania`, potem `kategoria_zadania`. (`includes/class-akwasmart-engine.php:923–978`, `:1087–1107`; `includes/class-akwasmart-data.php:664–681`)
- **Pola szczegółów rankingu:** ryba `Typ/Wielkość/Strefa/Temperament/pH`; skorupiak i ślimak `Typ/Wielkość/pH/Region`; inny Typ `Typ/Wielkość/pH`; flora `Wielkość/Strefa/CO2/Kolor/Region`; sprzęt `Kategoria/Rodzaj/Marka/Producent`; pielęgnacja `Kategoria/Rodzaj zadania/Obszar/Producent`. **Pola szczegółów weryfikacji:** fauna `Typ/Wielkość/Strefa/Temperament/pH`; flora `Wielkość/Strefa/CO2/Trudność/Kolor`; sprzęt `Rodzaj/Marka/Producent/Zdalne sterowanie`; pielęgnacja `Rodzaj zadania/Obszar/Marka/Producent`. Puste pola odpadają. Karta rankingu renderuje maksymalnie 5 szczegółów i „Ocena ścieżki: X%”. Karta weryfikacji ma także `compatibility` z `{label,user,optimal,status}` i `supporting_items`. (`includes/class-akwasmart-engine.php:1323–1359`, `:1371–1383`, `:2540–2598`; `akwasmart-k1k2-r27-final-result.php:581–657`, `:715–774`)
- **Granice domeny.** `Biotop Twardowodny (Malawi/Tanganika)` to dosłowny token profilu akwarium, filtr członkostwa i ewentualnie podstawa dogrywki. Nie jest wyprowadzany z pH, regionu ani osobnej bazy definicji biotopów. Pielęgnacja używa klasyfikacji zadań; sprzęt profilu litrażu i akwarium; wszystkie cztery obszary wystawiają samodzielne karty. Silnik nie nalicza cen, abonamentu ani prowizji. Zależne od Auth okna 30/90 dni i sposób wyświetlania cen z Shop opisano w §5. (`includes/class-akwasmart-engine.php:856–870`, `:944–978`, `:2308–2327`; `includes/class-akwasmart-data.php:230–236`, `:439–473`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:195–199`)

### 8.6 Kod historyczny i niedostępne reguły

| Fragment | Wartości zapisane w kodzie | Status |
|---|---|---|
| Wagi `k3_weights_for` | Fauna `Typ/Wielkość/Strefa/Temperament/Temperatura/pH/Region` = `40/34/28/24/18/18/8`; flora `Wielkość/Strefa/CO2/Kolor/Region` = `40/30/22/14/8`; sprzęt `kategoria_glowna/rodzaj/Marka/Zdalne_sterowanie/producent` = `40/36/18/12/10`; pielęgnacja `kategoria_zadania/rodzaj_zadania/obszar/Marka/producent` = `38/32/24/12/10`. Fallback `12` dla doświadczenia, `8` dla zwykłego exact match. | Martwy w publicznym rankingu. `includes/class-akwasmart-engine.php:2422–2450`, `:2509–2522`; aktywny `:1799–1887`. |
| Dawne bonusy | Litraż `18/14/10`, wildcard `8`; zgodność typu `16/12`; doświadczenie `10`; bonus szczegółowości `max(0,6−n_litraży)` i `max(0,5−n_typów)`; roślinny + `CO2=Wskazany` `+3`, sprzęt `tak` `+2`, towarzyski + spokojny `+3`, nano + skorupiak `+6`. | Martwe. `includes/class-akwasmart-engine.php:2390–2419`, `:2452–2507`. |
| Doświadczenie i trudność | `Początkujący` / `Zaawansowany`; ranking doświadczenia: zaawansowany z wymaganiem `3`, zaawansowany bez `2`, początkujący bez `1`, inaczej `0`. Oczekiwanie doświadczenia zależy od `Trudność`, poziomu sprzętu/tekstu `co2`, lub fraz pielęgnacji `dawk`, `nawo`, `lecze`, `buffer`, `parametr`, `co2`. Dogrywka trudności `Początkujący/Średni/Ekspert` = `3/2/1`, inne `0`. | Nie ma pytania na aktywnych planach; `should_ask_experience` zawsze `false`; `difficulty` nie występuje w aktywnej kolejności dogrywek. `includes/class-akwasmart-engine.php:422–447`, `:1139–1186`, `:1283–1302`, `:2031–2043`, `:2241–2273`. |
| Kompletny plan, inferencja typu, pomocnicze API | `Kompletny plan dla zbiornika` uruchamia osobną notę i kod globalnego doświadczenia, ale brak opcji w UI. `inferred_fauna_type_for_unit`, `AquaSmart_Engine::autocomplete`, `AquaSmart_Data::sort_by_id` nie mają wywołań w przejrzanych ścieżkach. | Relikty. UI używa wyboru typu, zakresu sesji i bezpośredniego `search_records`. `includes/class-akwasmart-engine.php:58–73`, `:250–263`, `:454–478`, `:2600–2611`; `includes/class-akwasmart-data.php:377–381`; `akwasmart-k1k2-r27-final-result.php:269–305`. |

### 8.7 Proponowane testy golden dla v2

Oczekiwania policzone na niezmienionych TSV `bundled` w lokalnym modelu odtwarzającym filtry, punktację i klucze kolejności. Liczby `pkt/%` odnoszą się do K3. W testach UI należy podawać wyłącznie pytania rzeczywiście wyświetlone. `Nie wiem` poniżej dotyczy pytań opcjonalnych. Podstawy obliczeń: `includes/class-akwasmart-engine.php:355–447`, `:665–870`, `:923–1083`, `:1363–1471`, `:1799–2030`, `:2089–2327`.

| ID | Wejście | Oczekiwany wynik w kolejności | Rekordy kontrolne |
|---|---|---|---|
| G01 | Fauna, `Małe (40L - 100L)`, `Ogólne / Towarzyskie`, `Ryba`, `Mała (3-5 cm)`, `Górna`, `Spokojny`, `Grzałka często (22-25,5)`, `Woda obojętna (6.8 - 7.5)`, `Ameryka Południowa`. | Pula 23. `FN0001`, `FN0029`, `FN0020` po **18/100%**. `partial_tie`, pierwsze dwa ex aequo; trzeci ma słabszą dogrywkę litrażu. | `data/baza_fauna_end1.txt:2`, `:21`, `:30`. |
| G02 | Fauna, `Nano (< 40L)`, `Nano / Krewetkarium`, `Skorupiak`, `Woda kwaśna (poniżej 6.8)`, `Azja`. | Pytanie `Wielkość` pominięte. `FN0155` **6/100%**, `FN0163` **3/50%**. Drugi ma pH `1` i Region `2`, oba jako ostrzeżenia. | `data/baza_fauna_end1.txt:156`, `:164`. |
| G03 | Fauna, `Małe (40L - 100L)`, `Ogólne / Towarzyskie`, `Ślimak`, `Nano (do 3 cm)`, pH i Region `Nie wiem`. | Pula 7; `partial_tie`, pięć kart ex aequo po **3/100%**: `FN0152`, `FN0153`, `FN0157`, `FN0158`, `FN0159`. | `data/baza_fauna_end1.txt:153–154`, `:158–160`. |
| G04 | Fauna, `Nano (< 40L)`, `Roślinne`. Następnie potwierdź `Ryba`, w kolejnych pytaniach wybierz `Nie wiem`. | Typ trzeba potwierdzić mimo jednej opcji. Rozmiar organizmu pominięty. `FN0162`, **0 pkt, 100%**, pusta lista punktowanych kryteriów. | `data/baza_fauna_end1.txt:163`; `includes/class-akwasmart-engine.php:391–406`, `:1848–1855`. |
| G05 | Flora, `Nano (< 40L)`, `Nano / Krewetkarium`, `Wysoka (>30 cm)`, reszta `Nie wiem`. | Pula 43; `FL0156` **3/100%**, `FL0155` **2/67%**, `FL0052` **1/33%**. Test utrwala obecne zachowanie z błędem opcji nano. | `data/baza_flora_end1.txt:53`, `:156–157`; `includes/class-akwasmart-engine.php:559–592`. |
| G06 | Flora, `Nano (< 40L)`, `Nano / Krewetkarium`, `B. mała (< 5 cm)`, Region `Ameryka Płn. i Środk.`, reszta `Nie wiem`. | `partial_tie`, cztery karty **5/83%**: `FL0102`, `FL0044`, `FL0104`, `FL0103`. `Region=2` jako uwaga. Kolejność w remisie uwzględnia dogrywkę. | `data/baza_flora_end1.txt:45`, `:103–105`. |
| G07 | Flora, `Małe (40L - 100L)`, `Roślinne`, `Średnia (10-30 cm)`, `Środek`, `Wskazany`, `Czerwony/Brązowy`, `Azja`. | `FL0174`, `FL0009`, `FL0168`, wszystkie **15/100%**. `tiebreak`, `FL0174` wygrywa dogrywkę litrażu. | `data/baza_flora_end1.txt:10`, `:169`, `:175`. |
| G08 | Sprzęt, `Średnie (101L - 250L)`, `Ogólne / Towarzyskie`, `Filtracja`, `Filtr zewnętrzny`, `Premium`, `Eheim`. | Pula 11. `SP0005`, `SP0009`, `SP0008` po **12/100%**, `tiebreak`. Lista producentów przed wyborem to `Aquael`, `Eheim`, `Fluval`, `Oase`, `Nie wiem`. | `data/baza_biotop_end2.txt:2–20`; `includes/class-akwasmart-engine.php:634–641`, `:883–908`. |
| G09 | Sprzęt, `Nano (< 40L)`, `Biotop Twardowodny (Malawi/Tanganika)`. | Pusta pula i zero pytań K2. Silnik `count=0`, `items=[]`, `Brak dopasowań`; osobna asercja wykrywa błędne `Gotowe dopasowanie` w legacy UI. | `data/baza_biotop_end2.txt:1–109`; `includes/class-akwasmart-engine.php:316–329`, `:911–921`; `akwasmart-k1k2-r27-final-result.php:793–799`. |
| G10 | Pielęgnacja, `Nano (< 40L)`, `Nano / Krewetkarium`, `Start / przygotowanie`, `Przygotowanie wody`, `Uzdatniacze wody`, `Premium`, `Tetra`. | `CH0050` **15/100%**, `CH0047` i `CH0048` **14/93%**. Powtórzyć z dowolną inną parą K1, kolejność i punkty identyczne. | `data/baza_pielegnacja_end1.txt:1`, `:47–52`. |
| G11 | Pielęgnacja, `Bardzo duże (> 500L)`, `Biotop Twardowodny (Malawi/Tanganika)`, `Pielęgnacja na co dzień`, `Cięcie i pielęgnacja`, `Czyszczenie szyb`, marka i producent `Nie wiem`. | `full_tie`: `CH0001`, `CH0002`, `CH0003` po **9/100%**. | `data/baza_pielegnacja_end1.txt:2–4`. |
| G12 | `Znajdź produkt`, fraza `neon innesa` / `ne`; osobno `equilibrium`. | `neon innesa` → jeden `FN0011`, etykieta `Neon Innesa - Paracheirodon innesi`; `ne` → `[]`. `equilibrium` → obie pozycje `CH0041`, `CH0090`, identyczna etykieta, różne ID. | `data/baza_fauna_end1.txt:12`; `data/baza_pielegnacja_end1.txt:42`, `:91`; `includes/class-akwasmart-data.php:585–627`, `:674–681`. |
| G13 | Weryfikacja `FN0001`, `Nano (< 40L)`, `Nano / Krewetkarium`, `Woda zasadowa (powyżej 7.5)`, `Grzałka często (22-25,5)`. | Wektor `1,0,2,3`, **67%, Słabe dopasowanie**. Jedna alternatywa `FN0002` **100%**. Brak korekty, bo ostrzeżenia dotyczą tylko litrażu i typu. Współistniejące `CH0015`, `CH0016`; paczka `count=1`, `items` ma 2 elementy. | `data/baza_fauna_end1.txt:2–3`; `data/baza_pielegnacja_end1.txt:16–17`; `includes/class-akwasmart-engine.php:1323–1397`, `:1561–1577`. |
| G14 | Weryfikacja `FN0001`, `Bardzo duże (> 500L)`, `Biotop Twardowodny (Malawi/Tanganika)`, `Woda zasadowa (powyżej 7.5)`, `Zimnolubne (poniżej 18)`. | Wektor `0,0,2,0`, **17%**. Brak alternatywy ≥90%. Korekta `SP0057` **90%**; test integracyjny wykrywa obecne przekazanie `fauna` zamiast `sprzet` do Shop. | `data/baza_fauna_end1.txt:2`; `data/baza_biotop_end2.txt:58`; `includes/class-akwasmart-engine.php:1533–1622`; `akwasmart-k1k2-r27-final-result.php:563–567`, `:675`. |
| G15 | Weryfikacja `CH0050` po wyszukaniu `AquaSafe`. | Bez pytań. `CH0050`, **100%, Zgodny**, pusta `compatibility`, tekst „Nie było dodatkowych warunków do sprawdzenia dla tego rekordu.”. | `data/baza_pielegnacja_end1.txt:51`; `includes/class-akwasmart-engine.php:1283–1289`, `:1395–1397`, `:2358–2378`. |
| G16 | Na pytaniu `Typ` w G04 podaj nieoferowane `Ślimak`, następnie `Ryba`. | Pierwsze żądanie `invalid_answer`, stan dalej na `Typ`; drugie przyjęte. W osobnym teście `get_payload` dla nieistniejącej sesji → `missing_session`, a widok tworzy nową. | `includes/class-akwasmart-engine.php:48–55`, `:174–183`, `:391–406`; `akwasmart-k1k2-r27-final-result.php:244–249`, `:308–314`. |

## 9. Pytania otwarte

1. Który pakiet jest źródłem referencyjnym migracji? **[HIPOTEZA]** Produkcja korzysta z `bundled`; potrzebne są aktywny tryb, manifest i identyfikacja wersji danych z instancji. (`includes/class-akwasmart-data.php:102–125`, `:220–227`, `:251–282`)
2. Czy większy litraż ma zawsze przechodzić w faunie i florze? Czy ten sam model ma obowiązywać przy weryfikacji rekordu? Które ścieżki mają dopuścić wysokie rośliny w nano? (`includes/class-akwasmart-engine.php:559–592`, `:776–808`, `:1415–1435`)
3. Czy `Specjalistyczne`, `Inne`, kategorie rozmiaru i przedziały temperatury są kanoniczne, czy trzeba rozdzielić profil zbiornika, zasolenie, biotop i mierzalne parametry? Etykiety progowe nakładają się lub zostawiają luki przy interpretacji liczbowej; kod używa kategorii. (`includes/class-akwasmart-engine.php:502–510`, `:1937–1940`; `data/baza_fauna_end1.txt:169`; `AQUAWiedza/specyfikacja/specyfikacja_v2.0.md:41–65`)
4. Czy nadal obowiązuje prezentowanie całej grupy ex aequo przy 0–2 kryteriach, także przy dużej liczbie równorzędnych wyników? Czy dogrywka według węższego profilu ma dalej wpływać na kolejność grupy? (`includes/class-akwasmart-engine.php:923–944`, `:1033–1061`, `:2089–2116`)
5. Jakie reguły eksperckie zatwierdzają korekty pH, temperatury i CO₂ oraz pokarmy dla ślimaków? Jak prezentować weryfikację produktu, dla którego nie sprawdzano żadnego warunku? (`includes/class-akwasmart-engine.php:1395–1397`, `:1561–1643`)
6. Czy katalog nazw i ID może być publiczny, podczas gdy rekomendacje wymagają subskrypcji? Jak traktować administratora bez roli portalowej i użytkownika z dwiema rolami? (`akwasmart-k1k2-r27-final-result.php:68–69`, `:126–135`, `:289–305`; `includes/class-aqs-portal.php:123–135`, `:159–215`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:105–114`)
7. Czy powtórzone modele i produkty oznaczają wariant, klasyfikację wiele-do-wielu czy duplikat do scalenia? Jak zachować powiązania z ofertami po ewentualnym scaleniu? (`data/baza_biotop_end2.txt:90–97`; `data/baza_pielegnacja_end1.txt:42`, `:66–67`, `:82`, `:87`, `:91–94`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:59–73`)
8. Które zapisy specyfikacji są obowiązkowe dla nowej v2: GH/KH, światło, stadność, kompatybilność obsady, podpięcie zapisanych pomiarów, doświadczenie, kompletny plan? **[HIPOTEZA]** Część wymaga nowego modelu danych, nie prostego przeniesienia algorytmu. (`AQUAWiedza/specyfikacja/specyfikacja_v2.0.md:41–86`; `AQUAWiedza/zakres-prac-v2.0.md:41–55`; `includes/class-akwasmart-engine.php:422–447`; `data/baza_fauna_end1.txt:1`; `data/baza_flora_end1.txt:1`)

## 10. Wnioski dla v2

| Decyzja | Zakres i uzasadnienie |
|---|---|
| **Zachować 1:1 jako punkt odniesienia** | Cztery niezależne ścieżki doboru i weryfikację wskazanego rekordu; poprawki `REGIONFIX`, `AUDITFIX`, preferencje producenta, kolejność kryteriów, sposób liczenia procentu, dogrywki i remisy. Złote testy powinny utrwalać także pełną kolejność ID, wektory punktów, ostrzeżenia i wyświetlone pytania. (`includes/class-akwasmart-engine.php:355–447`, `:811–854`, `:923–1083`, `:1225–1471`, `:1799–2327`) |
| **Uprościć realizację** | Jedna funkcja puli dla normalnego doboru, jawne słowniki, jeden model pytania z wersją, wstępnie liczone klucze rankingu, jeden kontrakt wyszukiwania, zapytanie ofertowe dla wielu rekordów. Zachować semantykę K1/K2/K3, procent i wyjaśnienia, w tym odrębny model oceny konkretnego rekordu. (`includes/class-akwasmart-engine.php:492–500`, `:665–734`, `:883–908`, `:1805–1807`, `:2111–2174`; `assets/js/search.js:43–72`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:56–73`) |
| **Naprawić w sposób widoczny w kryteriach odbioru** | Import z walidacją i atomową aktywacją czterech plików; właściciel i wersja sesji; poprawny widok pustej puli; prawidłowy obszar produktu korygującego. Osobnej decyzji produktowej wymagają 100% bez pytań, wysoka flora w nano i heurystyki korekt. Testy G05, G09, G14 i G15 dokumentują stan wyjściowy przed respecyfikacją. (`includes/class-akwasmart-data.php:118–125`, `:147–192`; `includes/class-akwasmart-engine.php:75–102`, `:559–592`, `:911–921`, `:1395–1397`, `:1561–1622`; `akwasmart-k1k2-r27-final-result.php:670–676`, `:793–799`) |
| **Odrzucić historyczną implementację po zabezpieczeniu zachowania** | Nie przenosić nieużywanych wag, bonusów, inferencji typu, nieaktywnego pytania o doświadczenie ani znacznika jednorazowego resetu z 2026 r. Zachować funkcję zarządzania źródłem danych, kontrolę dostępu, możliwość diagnozy i kompatybilne przekierowania ze starych adresów. (`includes/class-akwasmart-engine.php:454–478`, `:1139–1149`, `:2390–2522`; `akwasmart-k1k2-r27-final-result.php:34–55`, `:864–1044`; `includes/class-aqs-portal.php:68–121`) |
| **Migracja danych** | Wyeksportować faktycznie aktywny pakiet i manifest. Zachować `(obszar, id)` i pierwotną kolejność plików potrzebną alternatywom, korektom i produktom współistniejącym. Przechować surowe teksty i wielowartościowe pola obok wartości znormalizowanych. Powiązać `sprzet`↔`equipment`, `pielegnacja`↔`maintenance`. Nie scalać rekordów po samym `produkt + producent` bez mapy istniejących ID. Transienty są efemeryczne; decyzja o zachowaniu otwartych sesji zależy od wymaganego przebiegu przełączenia. (`includes/class-akwasmart-data.php:102–125`, `:147–192`, `:324–375`; `includes/class-akwasmart-engine.php:5–6`, `:1533–1643`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-db.php:374–386`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:59–73`) |
| **Warunek odbioru** | Uruchomić golden cases przeciw zarchiwizowanemu PHP 1.1.6 i nowej implementacji na tej samej kopii danych. Dodać osobne próby dostępu gościa, roli user/sklep i wygaśnięcia trialu, importu częściowego, utraty sesji oraz linków do sklepu aktywnego. Lokalne obliczenia i kontrola tekstu źródłowego stanowią oczekiwania testów, a pomiar PHP/WP pozostaje do wykonania. (`testy/test_skladni_php.sh:1–7`; `testy/test_regionfix_source.php:20–35`; `AQUAWiedza/testy/CHECKLISTA_E2E_PILOT.md:475–533`; `AquaSmart-plugins/aquasmart-auth/includes/class-aqs-trial.php:46–58`; `AquaSmart-plugins/aquasmart-shop/includes/class-aqs-shop-display.php:79–90`, `:149–155`) |