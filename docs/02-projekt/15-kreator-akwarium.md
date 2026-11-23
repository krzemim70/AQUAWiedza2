# Kreator akwarium w przeglądarce: projekt

Stan: projekt do decyzji właściciela (pytania w §7). Powiązane: `14-etap-teraz.md`, `13-kierunki-rozwoju.md`.

## 1. Architektura po stronie przeglądarki

### Podział odpowiedzialności klient – serwer

| Obszar | Gdzie działa | Zakres i uzasadnienie |
|---|---|---|
| **Krok 1: Zbiornik** | Przeglądarka | Przeliczenie wymiarów `dł. × szer. × wys.` na litraż z jawnym zaokrągleniem oraz dobór pasma wg progów z `app/Domain/Advisor/Engine/TankBand.php:28` (`<40`, `40–100`, `101–250`, `251–500`, `>500 l`). |
| **Kroki 2–3: Fauna i flora** | Przeglądarka | Wyszukiwanie, filtry, punktacja dopasowania wagami `4/3/2/1` (`app/Domain/Advisor/Engine/TankEngine.php:8`) oraz pełne porównanie katalogowe obsady z dowodami „Dlaczego?” (`app/Domain/Advisor/Engine/Compatibility/CompatibilityEngine.php:66`). |
| **Kroki 4–5: Sprzęt i pielęgnacja** | Przeglądarka | Kwalifikacja sprzętu po pasmie/typie (`app/Domain/Aquarium/Actions/BuildPlanReport.php:61`), wskazówki z dowodami dla ogrzewania i `Dozowania CO2`, grupowanie zadań pielęgnacji i pokarmów (`docs/02-projekt/12-nowe-funkcje.md:236`). |
| **Szkic roboczy gościa** | Przeglądarka | Zapis po każdej zmianie w `localStorage` (`aikwarium.creator.v1`); zero wywołań sieciowych podczas układania zestawu. |
| **Krok 6: Oferty w okolicy** | Serwer (1 lekkie żądanie) | Stany `freeQty`, ceny efektywne, promocje z historią 30 dni i aktywność placówek zmieniają się na bieżąco; jedno zapytanie grupowe przez `PublicOffers::forRecords` i akcję `SplitShoppingList`. |
| **Zapis planu, koszyk, obserwacje, konwersja** | Serwer (akcje domenowe) | Transakcyjny zapis planu (`auth`, `consents`, `access:tank_plan`), dodanie do koszyka przez `ChangeCart`, obserwacje `WatchRecord` i zamiana w zbiornik `ConvertPlanToTank`. |

### Stos technologiczny (ponytail)

- **Wybór:** powłoka Blade + **Alpine.js** (już dostarczany z Livewire 4) + czysty moduł ES budowany przez Vite (`resources/js/creator/`), stylizowany w Tailwind 4 na tokenach z `resources/css/app.css:13` + 3 cienkie punkty końcowe JSON/POST delegujące do akcji domenowych.
- **Dlaczego wystarczy Alpine (bez React/Vue i bez Livewire w krokach 1–5):**
  - Stan kreatora to płaski słownik (`profil zbiornika` + mapa `pozycje[obszar:id] = ilość` + aktywny krok).
  - Przeliczenie rankingu dla 200 rekordów fauny i porównań par dla ~15 wybranych gatunków to < 200 prostych operacji w pamięci (< 1 ms w JS).
  - Rezygnacja z Livewire w krokach 1–5 eliminuje żądania `POST /livewire/update` przy każdym filtrze i kliknięciu `+ / −`, a cienkie trasy HTTP korzystające z DTO są od razu gotowe pod Sanctum i przyszłą aplikację mobilną (`docs/02-projekt/14-etap-teraz.md:62`).

### Pakiet danych katalogu: rozmiar, kompresja, wersjonowanie i unieważnianie

- **Zawartość (`catalog-{batch}-{hash}.json`):** aktywne rekordy 4 obszarów (obecnie **612 rekordów**: 200 fauna, 194 flora, 108 sprzęt, 110 pielęgnacja — `docs/02-projekt/12-nowe-funkcje.md:26`), słownik etykiet na początku pliku (zamiast powtarzania długich napisów pasm w każdym rekordzie), zatwierdzone miniatury zdjęć, zatwierdzone pola `catalog_record_specs` (`status = verified`, `docs/02-projekt/14-etap-teraz.md:24`) oraz sekcja `rules`. Długie opisy artykułowe pozostają poza pakietem głównym (dociągane wyłącznie na żądanie w podglądzie szczegółów gatunku).
- **Szacunek rozmiaru:**
  - Dziś (612 rekordów × ~260 B po słownikowaniu): **~160 KB surowy JSON** → **~32–38 KB Brotli** (~48 KB Gzip).
  - Przy 5-krotnym rozroście katalogu (~3 000 rekordów + zakresy KH/GH/NO₃/PO₄): ~780 KB surowy → **~120–140 KB Brotli** (mniej niż pojedyncze zdjęcie na stronie głównej).
- **Wersjonowanie i unieważnianie:**
  - Plik budowany statycznie na dysku (`storage/app/public/creator/`) po `ActivateBatch`, zatwierdzeniu zdjęcia/specyfikacji rekordu oraz po zdarzeniu `SettingChanged` dla ustawień doradcy i zgodności.
  - Serwowany bezpośrednio przez serwer WWW / CDN z nagłówkiem `Cache-Control: public, max-age=31536000, immutable` oraz `<link rel="preload" as="fetch" crossorigin>` w nagłówku strony kreatora; wsparty w `sw.js` po wdrożeniu PWA (`docs/02-projekt/14-etap-teraz.md:40`).
  - Zmiana partii lub ustawienia zmienia `{batch}-{hash}` w HTML — przeglądarka natychmiast pobiera nowy plik. Jeśli szkic w `localStorage` ma starszy `batch`, klient przelicza go na nowej partii i oznacza ewentualnie wycofane pozycje jako „spoza aktualnego katalogu” (identycznie jak `app/Domain/Advisor/Contracts/Compatibility.php:107`).

---

## 2. Spójność logiki PHP ↔ JS i twarda zasada

1. **Jedno źródło prawdy w PHP (eksport reguł i sparsowanych struktur):**
   - JS nie parsuje surowych kolumn TSV ani wyrażeń regularnych na polskich opisach. Akcja `ExportCreatorBundle` przepuszcza rekordy przez istniejące `FaunaPool` / `FloraPool` oraz wykrywacz rozbieżności tabela–opis (np. `FN0001` pH 6,8–7,5 vs 7,0–8,5; `FN0165` <18 °C vs 15–22 °C — zaległość z `docs/02-projekt/12-nowe-funkcje.md:886`) i zapisuje w JSON gotowe indeksy pasm, flagi `anyTankSize`, `hasUnknownPhBand` oraz strukturę `discrepancies`.
   - Sekcja `rules` w tym samym pliku JSON jest generowana z klas PHP i wersjonowanych ustawień `SettingKey`: progi `TankBand`, przedziały `Range::forBand` i przerwy `21,5–22` / `25,5–26 °C` (`app/Domain/Advisor/Engine/WaterHints/Range.php:17`), wagi `TankEngine` (`[4, 3, 2, 1]`, `MAX_FISH_GAP = 3`), aktywne wagi `SettingKey::CompatSeverity`, limity z `aquarium.calculators.limits` i mapowania kroków 4–5.
2. **Ponowna weryfikacja na serwerze:** przy zapisie planu na koncie i konwersji w zbiornik serwer zawsze uruchamia `Compatibility::check` i walidację katalogu — wynik przeglądarki jest podglądem dla użytkownika, nie ominięciem reguł domenowych.
3. **Złote testy zgodności (`tests/Golden/CreatorParityTest.php`):** test Pest buduje pakiet na danych referencyjnych `tests/Fixtures/KnowledgeBase`, uruchamia dla zestawu złotych przypadków (`FN0001`+`FN0165`, `FN0117`+`FN0080`, `FN0090`+`FN0153`, `FN0078`, `FN0150`, `FL0011` z i bez `Dozowania CO2`, `FN0151`, ślimak bez pokarmu w katalogu, pełny ranking `TankProfile`) kolejno klasy PHP (`TankEngine`, `CompatibilityEngine`) oraz moduł `resources/js/creator/engine.js` przez przenośne Node z `E:\workspace\aqua\tools`. Rozbieżność punktacji, ważności, liczników „z danymi / brak danych” albo treści `Evidence` zatrzymuje `composer check`.

---

## 3. Przepływ, ekrany, zapis i dostępność (WCAG 2.2 AA)

### Kroki kreatora (`GET /kreator-akwarium`, publiczne)

| Krok | Co robi użytkownik | Reguły z bazy wiedzy, dowody i stany „brak danych” |
|---|---|---|
| **1. Zbiornik** | Wybiera kartę pasma **lub** podaje wymiary / dokładny litraż; wybiera `TypAkwarium`; opcjonalnie pasmo temp. i pH wody. | Jawne zaokrąglenie litrażu przed wyznaczeniem pasma. Limity wejść z `SettingKey`. |
| **2. Ryby i bezkręgowce** | Filtruje po strefie (`Górna`/`Środkowa`/`Dolna`), rodzaju i zgodności z wybraną obsadą; ustawia ilości. | Karty z `%` dopasowania (`TankEngine`). Przy różnicy pasm lub niższym pasmie zbiornika porównanie katalogowe z `<details class="why">` (`baza: Kolumna = Wartość (ID, partia)`). Temperament wyłącznie jako etykieta gatunku + *„Relacja pary: brak danych”*. Rozbieżność tabela–opis → oba źródła i *„brak danych do porównania”*. |
| **3. Rośliny** | Filtruje florę pasującą do pasma (`Wszystkie` wliczone) i typu po strefie sadzenia, trudności i `CO2`. | Brak wymagań pH/temp./KH/GH roślin w bazie komunikowany wprost jako brak danych katalogowych (oceniane jest `WielkośćAkwarium`, `TypAkwarium` i `CO2`). |
| **4. Sprzęt** | Przegląda 4 kategorie (`Filtracja`, `Oświetlenie LED`, `Ogrzewanie`, `Dozowanie CO2`) i dodaje wybrane produkty. | Zero słów „potrzebujesz / obowiązkowy” (`docs/02-projekt/12-nowe-funkcje.md:856`). Ogrzewanie: wskazówka z pola `Temperatura` wybranej fauny z dowodem. `Dozowanie CO2`: wskazanie dla roślin z `CO2 = Niezbędny`/`Wskazany`. Jawna informacja: *„Moc i przepływ: brak danych w bazie — sprawdź na opakowaniu”*. |
| **5. Pielęgnacja** | Opcjonalnie wybiera produkty startowe (28 poz.), pokarmy, nawozy i testy wody. | `Ryba` → `Pokarmy dla ryb`, `Skorupiak` → `Pokarmy dla krewetek`, przy wybranym `Ślimaku` jawnie: *„Pokarmy dla ślimaków: brak danych w bazie”* (`docs/02-projekt/12-nowe-funkcje.md:240`). Brak narzucanych dawek. |
| **6. Lista zakupów i zapis** | Wybiera miasto (z konta lub ręcznie), przegląda podział na placówki, zapisuje plan lub dodaje do koszyka. | Placówki malejąco wg liczby pokrytych pozycji, potem rosnąco wg ceny efektywnej; niepełne stany opisane jako *„dostępne N z M”*. Sekcja braków z przyciskiem „Daj znać, gdy będzie dostępne” (`WatchRecord`). Po odbiorze możliwość „Utwórz akwarium z planu”. |

### Nawigacja, zapis postępu i przejście gość → konto

- **Swobodna nawigacja:** oś kroków pozwala cofać się i pomijać kroki opcjonalne (np. zbiornik biotopowy bez roślin lub zakup samego życia do posiadanego szkła).
- **Trwałość lokalna i konto:** `localStorage` z zapisem co `250 ms` i przyciskiem „Wyczyść szkic”. Kliknięcie „Zapisz plan” lub „Do koszyka” przez gościa kieruje do logowania/rejestracji z powrotem na `/kreator-akwarium?akcja=zapisz`; po przejściu bramki `/zgody` kreator przesyła szkic jednym `POST` do `SavePlanDraft` (szanując `aquarium.plan.limit`: w Tank 1 szkic — przy zajętym limicie wybór zastąpienia szkicu albo przejścia do listy planów) i czyści pamięć lokalną dopiero po zatwierdzeniu w bazie.

### Dostępność (WCAG 2.2 AA) i telefon

- **Klawiatura i czytniki:** zmiana kroku przenosi fokus na nagłówek `h2` nowego kroku (`tabindex="-1"`), oś ma `aria-current="step"`, podsumowanie zgodności i licznik używają `role="status" aria-live="polite"`, mobilna szuflada zestawu jest natywnym `<dialog>` z pułapką fokusu i `Escape`.
- **Cele dotyku i kontrast:** wszystkie przyciski, przełączniki kroków, `− / +` i `<summary>Dlaczego?</summary>` mają minimum `44 × 44 px` (`2.75rem`, powyżej wymogu 24 px z WCAG 2.2 SC 2.5.8); obrys `:focus-visible` `3px solid var(--color-focus)` i `scroll-margin-top: 7rem` chronią przed zasłonięciem fokusu przez przyklejony nagłówek (`resources/css/app.css:49`); pełna czytelność od `320 px` szerokości.

---

## 4. Wygląd i kierunek wizualny

Kierunek łączy estetykę konfiguratora Apple i kart studyjnych ElevenLabs z istniejącym systemem wizualnym Aikwarium (`resources/css/app.css:13`, `resources/views/public/home.blade.php:29`) — bez wprowadzania obcej palety:

- **Przestrzeń i układ („dużo powietrza”):** na ekranach `≥ 960 px` układ dwukolumnowy `1.35fr / 0.85fr` z szerokim odstępem (`gap: 3rem`) na tle `--color-canvas` (`#fafbfa`). Lewa kolumna to spokojna przestrzeń bieżącego kroku; prawa to przyklejony **„Podgląd zbiornika na żywo”** na ciemnej karcie `--color-dark` (`#0b2523`, tekst `--color-dark-text`, akcent `--color-dark-accent: #6fd3c6`) z subtelną poświatą `.hero-glow` w tle nagłówka. Na telefonie podgląd zwija się do pływającego paska dolnego z sumą pozycji i przyciskiem otwierającym `<dialog>`.
- **Dynamiczny schemat SVG zbiornika:** w karcie podglądu lekki wektorowy przekrój akwarium płynnie zmienia proporcje przy wpisywaniu wymiarów i subtelnie zaznacza obsadzone strefy wody (`Górna`, `Środkowa`, `Dolna`) oraz plany sadzenia roślin — czysto wizualne podsumowanie danych katalogowych.
- **Typografia:** krój `Inter` z ujemnym światłem literowym `-0.04em`, nagłówki kroków `var(--text-h2)` (`clamp(1.75rem, 3.4vw, 2.6rem)`), nienachalny `.eyebrow` z numerem kroku oraz `font-variant-numeric: tabular-nums` na litrażu, procentach, sztukach i cenach, aby liczby nie drgały podczas interakcji.
- **Karty i mikrointerakcje:**
  - Karty wyboru w Kroku 1 i karty katalogu w Krokach 2–5 z promieniem `var(--radius-card)` (`1.25rem`), obrysem `1px solid var(--color-line)` i miękkim uniesieniem `--shadow-lift` przy najechaniu; stan wybrany wyróżniony obrysem `2px solid var(--color-aqua)` i tłem `--color-aqua-50` + ikoną zaznaczenia (nie sam kolor).
  - Przycisk `+ Dodaj` po kliknięciu przechodzi w zwartą kapsułkę ilości `− 6 szt. +`.
  - Sekcja „Dlaczego?” rozwija cytat i dowód płynnym przejściem `grid-template-rows: 0fr → 1fr`, bez skoków układu.
- **Ruch i animacje:** przejścia między krokami przez natywne `document.startViewTransition` (zanikanie i przesunięcie `10 px` w `180 ms cubic-bezier(0.22, 1, 0.36, 1)`, wyłącznie `opacity` i `transform`), zamknięte w `@media (prefers-reduced-motion: no-preference)` (`resources/css/app.css:260`); przy `prefers-reduced-motion: reduce` przejścia są natychmiastowe (`0 ms`).

---

## 5. Wydajność i koszt serwera (10 000 użytkowników dziennie)

| Zdarzenie w sesji | Liczba zapytań HTTP | Obciążenie PHP / MySQL | Cache |
|---|---|---|---|
| Wejście na `/kreator-akwarium` | 1 × GET (~7 KB br) | Minimalne (widok Blade bez zapytań do katalogu; dla gościa możliwe buforowanie na brzegu) | CDN / krótki cache publiczny |
| Pobranie pakietu katalogu (`612` poz.) | 1 × GET (~35 KB br) przy 1. wizycie; **0** przy kolejnych | **0 PHP, 0 SQL** (statyczny plik) | `max-age=31536000, immutable` (CDN + przeglądarka) |
| Praca w krokach 1–5 (np. 60 kliknięć, filtry, suwaki, zgodność) | **0** | **0 PHP, 0 SQL** | Pamięć przeglądarki + `localStorage` |
| Krok 6: sprawdzenie ofert w okolicy | 1 × GET/POST (~3 KB) | 1 wywołanie `PublicOffers::forRecords` po indeksach (< 5 ms SQL) | Krótki cache serwerowy `30–60 s` na klucz `(miasto, lista_id)` |
| Zapis planu / dodanie placówki do koszyka | 1–2 × POST | 1–2 krótkie transakcje | Brak (zapis) |

- **Rachunek dla 10 000 sesji dziennie** (założenie: 100% otwiera kreator, 40% wchodzi w Krok 6, 10% zapisuje plan lub dodaje do koszyka, 30% ma już pakiet w pamięci przeglądarki):
  - Transfer statycznego pakietu katalogu: `7 000 × 35 KB ≈ 245 MB/dobę` z CDN / Nginx — pomijalny koszt pasma.
  - Żądania do PHP + MySQL: ~10 000 wyświetleń powłoki (lub mniej z CDN) + 4 000 odczytów ofert + ~1 500 zapisów ≈ **15 500 żądań/dobę**, czyli średnio **0,18 żądania/s** (w szczycie wieczornym **< 1 żądanie/s**).
  - W porównaniu z formularzem serwerowym odpytującym bazę przy każdym filtrze i zmianie ilości (~40 akcji × 10 000 = 400 000 żądań/dobę) obciążenie serwera spada o **ponad 95%** i mieści się z dużym zapasem na pojedynczej maszynie.

---

## 6. Podział na pakiety wdrożeniowe

> Uwaga techniczna wykryta przy analizie kodu: obecnie `app/Domain/Aquarium/Actions/ChangePlanItem.php:34` i `app/Domain/Aquarium/Actions/BuildShoppingList.php:31` obsługują wyłącznie `Fauna` i `Flora`, natomiast `app/Domain/Aquarium/Actions/ConvertPlanToTank.php:40` kieruje wszystko poza `Equipment` do `livestock()`. Dopisanie `Equipment` i `Care` do planu musi jednocześnie rozdzielić `Care` w `ConvertPlanToTank`, aby preparaty nie trafiły do tabeli obsady.

| Pakiet | Rozmiar | Zależności | Zakres prac | Charakter pracy |
|---|---|---|---|---|
| **1. Rozszerzenie domeny planu i podziału listy zakupów** | **M** | Brak (idzie równolegle z Pakietem 2) | Obsługa 4 obszarów (`Fauna`, `Flora`, `Equipment`, `Care`) w zapisie planu (`SavePlanDraft` / `ChangePlanItem`) i bezpieczne pominięcie `Care` przy zapisie do `livestock` w `ConvertPlanToTank`; akcja `SplitShoppingList` (pokrycie + cena efektywna + braki z czterech obszarów); akcja `AddPlanLocationToCart` delegująca do `ChangeCart`; ewentualne klucze `SettingKey`. | złożona logika |
| **2. Pakiet katalogu JSON, silnik JS i złote testy zgodności** | **M** | Brak (idzie równolegle z Pakietem 1) | Akcja `ExportCreatorBundle` (katalog + sekcja `rules` + flagi rozbieżności tabela–opis `FN0001`/`FN0165`) z przebudową po `ActivateBatch` i `SettingChanged`; czysty moduł `resources/js/creator/engine.js` (litraż, ranking, zgodność z dowodami, wskazówki sprzętu i pielęgnacji); złote testy parytetu PHP ↔ JS (`CreatorParityTest`). | złożona logika |
| **3. Interfejs kreatora w przeglądarce (kroki 1–5)** | **L** | Pakiet 2 | Publiczna trasa `GET /kreator-akwarium`, widoki Blade, komponent Alpine + Vite, układ dwukolumnowy ze schematem SVG zbiornika i szufladą mobilną `<dialog>`, View Transitions z obsługą `prefers-reduced-motion`, zapis w `localStorage`, pełna dostępność klawiatury i czytników (WCAG 2.2 AA). | interfejs |
| **4. Krok 6: oferty z okolicy, zapis konta, koszyk i obserwacje** | **M** | Pakiet 1, Pakiet 3 | Lekkie trasy ofert okolicy (gość z wyborem miasta i zalogowany z `users.city`), przeniesienie szkicu z `localStorage` po logowaniu/rejestracji i akceptacji `/zgody`, zapis do `tank_plans`, dodawanie pozycji placówki do koszyka, przycisk „Daj znać, gdy będzie dostępne” (`WatchRecord`) i konwersja w zbiornik; testy Feature uprawnień i limitów. | złożona logika |
| **5. Punkty wejścia, SEO, dokumentacja i bramka jakości** | **S** | Pakiet 4 | Odnośniki do kreatora na stronie głównej, `/doradca`, `/moje-akwarium` i `/moje-akwarium/plany`, metadane SEO i `sitemap.xml`, aktualizacja `docs/03-dokumentacja/03-funkcje.md`, kontrola układu `320 px` oraz pełne przejście `composer check` i `npm run build`. | proste prace |

---

## 7. Pytania do właściciela

1. **Widoczność ofert sklepów w Kroku 6 dla gościa:** czy gość (bez konta) po ręcznym wyborze miasta ma od razu widzieć pełne rozbicie listy zakupów na lokalne placówki z cenami (a konto jest wymagane dopiero przy „Zapisz plan”, „Do koszyka” i „Daj znać”), czy widok konkretnych ofert otwieramy dopiero po założeniu bezpłatnego konta Tank? *(Rekomendacja: pokazać oferty już gościowi — zwiększa to ruch do sklepów partnerskich i wartość strony z wyszukiwarek).*
2. **Relacja do obecnego ekranu `/moje-akwarium/plany/{plan}` i „Szybkiego doboru” (`/dobor`):** czy nowy kreator ma stać się jednocześnie ekranem edycji zapisanych szkiców planów na koncie (zastępując obecny prosty formularz planu), podczas gdy jednoekranowy `/dobor` zostaje bez zmian?
3. **Pozycje pielęgnacji (`Care`) przy zamianie planu w zbiornik (`ConvertPlanToTank`):** czy wybrane w planie pokarmy i preparaty służą wyłącznie do listy zakupów, czy (po wdrożeniu „Wirtualnych zapasów” z `docs/02-projekt/14-etap-teraz.md:30`) mają od razu tworzyć pozycje startowe w zakładce „Zapasy” nowego zbiornika?
4. **Dodawanie placówki do koszyka przy częściowej dostępności (np. dostępne 3 z 5 szt.):** czy przycisk „Dodaj dostępne pozycje z tej placówki do koszyka” ma od razu dodać dostępne 3 szt. z czytelnym komunikatem podsumowującym różnicę, czy wymagać osobnego potwierdzenia przed dodaniem niepełnej ilości?
