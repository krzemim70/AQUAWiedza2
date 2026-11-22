# v2 — propozycja stacku i architektury (DRAFT do recenzji)

**Status:** propozycja z poprawkami po niezależnej recenzji (§4a), 2026-11-22. Czeka na decyzję właściciela.

## 1. Z czego wynika wybór

Wymagania wyciągnięte z analizy obecnego PoC (`docs/01-analiza/`):

| Cecha systemu | Konsekwencja |
|---|---|
| Aplikacja głównie panelowa: panel admina, panel sklepu, panel akwarysty, a do tego publiczny doradca i ministrony | Potrzebny framework z bardzo szybkim budowaniem paneli CRUD, formularzy, tabel i uprawnień. |
| Dużo konfigurowalnych reguł biznesowych (próg 50/40/3, plany S/M/L/XL, trial 30/90, prowizje, kampanie) | Ustawienia trzymane w bazie, edytowalne w panelu admina, z historią zmian. Żadnych stałych w kodzie. |
| Silnik rekomendacji: deterministyczna punktacja ok. 600 rekordów | Mała moc obliczeniowa. Liczy się czysta, testowalna logika domenowa i testy zgodności z PoC. |
| Zadania cykliczne: próbka dzienna, rollup miesięczny, przypomnienia, kontrola linków, czyszczenie kont | Wbudowany scheduler i kolejka z ponowieniami i idempotencją. |
| Publiczne ministrony sklepów i strony wiedzy | Renderowanie po stronie serwera (SEO), trwałe adresy, przekierowania 301. |
| Mały zespół: właściciel i agenci AI piszący kod | Popularny, stabilny, silnie konwencyjny framework, dobrze znany modelom. Mało zależności. |
| Asystent AI (v3) | Wywołania API modelu językowego plus wyszukiwanie wektorowe po bazie wiedzy i historii zbiornika. Nie wymaga Pythona w rdzeniu. |
| Błędy PoC (patrz §4) | Transakcje, klucze obce, migracje, testy, CI, staging. |

## 2. Rekomendacja: Laravel 12 + Filament 4 (modularny monolit)

| Warstwa | Wybór | Dlaczego |
|---|---|---|
| Język i framework | **PHP 8.4 + Laravel 12** | Wbudowane: auth, kolejki, scheduler, mail, polityki uprawnień, walidacja, migracje, testy. Algorytm z PoC (PHP) przenosimy bez tłumaczenia między językami. |
| Panele | **Filament 4**, trzy osobne panele: `/admin`, `/panel-sklepu`, `/moje-akwarium` | Gotowe tabele, formularze, filtry, akcje, dashboardy, multi-tenancy (sklep jako tenant), uprawnienia. Najmniej własnego kodu UI. |
| Strona publiczna | **Blade + Livewire 3 + Alpine + Tailwind 4** | Renderowanie serwerowe (SEO). Interaktywny kreator doradcy w Livewire bez osobnego SPA. Tailwind daje tokeny brandingu na etap 3. |
| Baza danych | **PostgreSQL 17** (dopuszczalny MySQL 8, jeśli zostaje obecny hosting) | Klucze obce, transakcje, ograniczenia CHECK i UNIQUE, JSONB na atrybuty bazy wiedzy, `pgvector` pod AI v3. |
| Uprawnienia | Laravel Policies + `spatie/laravel-permission` | Role i uprawnienia faktycznie sprawdzane w jednym miejscu. |
| Ustawienia biznesowe | Tabela `settings` z typami i historią (np. `spatie/laravel-settings`) + panel admina | Progi, plany, trial, prowizje, kampanie: wszystko edytowalne, wersjonowane, audytowane. |
| Audyt | `spatie/laravel-activitylog` | Kto, co, kiedy: zmiany statusów, ustawień, stanu sklepu, decyzje automatu. |
| Kolejki i cron | Laravel Queue (baza lub Redis) + Scheduler + `withoutOverlapping`/`onOneServer` | Idempotentne zadania z ponowieniami. Zastępuje WP-Cron. |
| Mail | Laravel Mail + SMTP (dpoczta) lub Postmark/Brevo | Szablony Markdown, kolejka, log dostarczenia. |
| Testy | Pest + testy „golden” silnika (wynik v2 = wynik PoC) + Larastan (poziom max) + Pint | Bezpieczny refaktor algorytmu. |
| CI/CD | GitHub Actions → staging → produkcja (Ploi/Forge lub Deployer) | Koniec z wgrywaniem plików przez edytor WP Admin. |
| Hosting | VPS w UE (np. Hetzner lub OVH), PHP-FPM + Nginx/Caddy + Postgres + backup offsite | Kontrola nad PHP, cronem i kolejką. Opcja awaryjna: obecny dhosting (PHP 8.4 + MySQL + cron). |
| AI (v3, przygotowanie w v2) | API modelu językowego przez warstwę `AdvisorAssistant` + `pgvector` | W v2 tylko zbieramy dane w ustrukturyzowanej formie: obsada, pomiary, zdarzenia. |

### Rozważone alternatywy

| Opcja | Plusy | Dlaczego nie jako pierwszy wybór |
|---|---|---|
| Next.js + Payload CMS 3 (TypeScript, Postgres) | Jeden język front i back, panel admina z Payload, dobre SEO | Więcej ruchomych części (Node runtime, osobny worker do zadań). Panele sklepu i akwarysty i tak trzeba budować ręcznie. Algorytm wymaga przepisania z PHP na TS. |
| Django + Django Admin / Unfold | Python ułatwia przyszłe ML i AI | Admin mniej elastyczny dla paneli dla klientów. AI v3 to wywołania API, więc Python nie jest konieczny. Przepisanie algorytmu. |
| Zostać na WordPressie i poprawić wtyczki | Najmniej pracy na start | Wbrew celowi: podatności, uzależnienie od wtyczek, brak kluczy obcych i transakcji, `user_meta` jako baza stanu. |

## 3. Architektura: modularny monolit

Jedna aplikacja, jedna baza, moduły z jasnymi granicami (katalogi `app/Domain/*`) i komunikacją przez serwisy i zdarzenia. Żadnego bezpośredniego SQL do tabel innego modułu.

| Moduł | Odpowiada za | Źródło w PoC |
|---|---|---|
| **Identity** | Konta, rejestracja z aktywacją e-mail, ochrona antybotowa, logowanie, 2FA, role | aquasmart-auth |
| **Access / Subscriptions** | Stan dostępu (trial/active/expired/suspended) jako jawny automat z historią przejść; czas trwania z ustawień | aquasmart-auth |
| **Knowledge Base** | Fauna, flora, sprzęt/biotop, pielęgnacja: edycja w adminie, import/eksport TSV/CSV, wersjonowanie, stabilne ID | aquasmart_v103/data |
| **Advisor** | Silnik K1/K2/K3 jako czysta usługa domenowa z testami golden; kreator; „poleć do mojego zbiornika” | aquasmart_v103 |
| **Aquarium** | Zbiorniki, obsada, pomiary, dziennik, przypomnienia (+ e-mail), Redfield liczony na serwerze | aquasmart-user |
| **Shops** | Profil sklepu, katalog ofert, bufor szkiców, atomowa publikacja, automat stanu publikacji (progi z ustawień), ministrony | aquasmart-shop |
| **Tracking** | Zdarzenia kliknięć (jedno źródło prawdy), deduplikacja, limity, oba typy linków | aquasmart-shop |
| **Metrics & Billing** | Próbka dzienna, rollup miesięczny (idempotentny po `(shop, period)`), wyliczenie planu i prowizji wg reguł z admina. Bez faktur na tym etapie. | aquasmart-shop billing |
| **Campaigns** | Kampanie z datami i segmentem: wydłużony trial, obniżony próg, darmowa publikacja, kody | nowe |
| **Notifications** | Szablony, kolejka, log dostarczenia, preferencje użytkownika | rozproszone |
| **Privacy (RODO)** | Eksport danych, usunięcie i anonimizacja kaskadowo, wersje zgód, retencja | brak w PoC |
| **Admin** | Panel operatora: użytkownicy, sklepy, ustawienia biznesowe, kampanie, baza wiedzy, raporty, audyt | aquasmart-auth/shop admin |

## 4. Błędy PoC, których nie powtarzamy

| Błąd w PoC | Reguła w v2 |
|---|---|
| Brak transakcji: publikacja, aktywacja, kaskady | Każda operacja wielozapisowa w transakcji; ograniczenia UNIQUE chronią przed duplikatami. |
| Brak kluczy obcych i osierocone dane po usunięciu konta | FK z `ON DELETE` + jawna ścieżka RODO dla każdego modułu. |
| Reguły biznesowe zaszyte w kodzie (50/40/3, 5%/2%, 30/90) | Wszystko w ustawieniach z panelem i historią. |
| Stan i zegar w `user_meta` bez historii; ten sam miesiąc liczony wielokrotnie | Encje stanu + tabela przejść; ocena okresu zapisana i idempotentna. |
| Uprawnienia zdefiniowane, ale niesprawdzane | Policy na każdej akcji; testy autoryzacji. |
| Ta sama logika w kilku miejscach (klasyfikacja linku, liczniki kliknięć) | Jedna usługa, jedno źródło prawdy. |
| Kalkulator liczony tylko w JS | Logika domenowa na serwerze; UI ją tylko wyświetla. |
| Brak testów; dokumentacja odpływa od kodu | Testy golden i feature w CI; dokumentacja ADR w repo. |
| Deploy edytorem WP, brak stagingu i rollbacku | CI → staging → produkcja, migracje, rollback. |
| Bazy wiedzy jako ręcznie edytowane pliki TSV | Baza z walidacją, panel edycji, import i eksport. |
| Publiczny tracker bez limitów; SSRF przy sprawdzaniu linków | Rate limit, deduplikacja, walidacja celów przekierowań, izolacja zadań HTTP. |

## 4a. Zmiany po niezależnej recenzji (`02-recenzja-stacku.md`)

Recenzja potwierdza kierunek: Laravel 41/45, Django 38 jako rezerwa, Next.js + Payload 33. Przyjmuję poprawki:

| # | Zmiana | Powód |
|---|---|---|
| R1 | Wersje: **Laravel 13 + Filament 5 + Livewire 4**, PHP 8.4 | Laravel 12 ma poprawki bezpieczeństwa tylko do 02.2027, a Laravel 13 do 03.2028. Filament 5 jest powiązany z Livewire 4. |
| R2 | Filament dla **admina i sklepu**. Panel akwarysty i strona publiczna w **Blade + Livewire** | Akwarysta to klient końcowy: potrzebuje własnego UX i brandingu (etap 3), nie wyglądu panelu administracyjnego. |
| R3 | Identity + Access w jednym module, ale z osobnymi stanami: konto (`pending`), abonament (`trial/active/expired/suspended`), publikacja sklepu (`aktywny/nieaktywny`) | Nie mieszać uprawnień z widocznością sklepu. |
| R4 | `spatie/laravel-permission` dopiero wtedy, gdy operator ma sam tworzyć role. Na start Policies i jawne członkostwo w sklepie | YAGNI. |
| R5 | Reguły handlowe jako **wersjonowane** ustawienia: data obowiązywania, autor, walidacja przedziałów, kwoty w groszach. Okres rozliczeniowy zapamiętuje, której wersji reguł użył | Odtwarzalność rozliczeń. |
| R6 | Kolejka na PostgreSQL. Klucze `UNIQUE(shop_id, sample_date)` i `UNIQUE(shop_id, period)`. Ocena okresu, przejście stanu i audyt w jednej transakcji. Alarm przy brakującym przebiegu | Idempotencja cronów. |
| R7 | `pgvector`, embeddingi i LLM dopiero przy pierwszym przypadku użycia w v3. W v2 tylko porządne dane: rewizje wiedzy, historia zbiornika z jednostkami | YAGNI. |
| R8 | Migracja haseł: import hashy WP (`$P$`, `$2y$`, `$wp$2y$`), weryfikator przejściowy, przehashowanie przy logowaniu | Użytkownicy nie muszą resetować haseł. |
| R9 | Zachować adresy `/sklep/{slug}/…` i `/go/shop/{id}/` (przekierowania 301/302) | SEO i linki w obiegu. |
| R10 | Hosting: **Ploi Cloud UE + zarządzany Postgres (ok. €35/mies.)** albo **Hetzner VPS (ok. €6/mies. + własna administracja)**. Domena i poczta mogą zostać na dhostingu | Decyzja właściciela. |
| R11 | Doradca: najpierw testy zgodności portu z PHP PoC (G01–G16), dopiero potem zmiany reguł zatwierdzone przez właściciela | Nie utrwalać błędów pod hasłem „1:1” i nie zmieniać reguł po cichu. |

Korekta faktu: raport prowizji admina **nie liczy prowizji**. `decorate_rows()` nie jest wywoływana (`class-aqs-shop-report.php:46,67,70`), więc kolumna prowizji i CSV są puste.

## 5. Otwarte decyzje dla właściciela

1. Hosting: Ploi Cloud UE (wygoda), Hetzner VPS (najtaniej, ale ktoś musi administrować) czy dhosting?
2. PostgreSQL (rekomendacja; także kolejka) czy MySQL?
4. Czy gość może przejść kreator doradcy bez konta (marketing i SEO), czy tylko zalogowani?
5. Co zawieszenie lub wygaśnięcie abonamentu sklepu wyłącza: panel, publikację, doradcę?
3. Migracja danych z produkcji czy start od zera (zależy od odpowiedzi twórcy na pytanie A3)?
