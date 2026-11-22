# Ustalenia projektu Aikwarium v2

Wszystkie ustalenia zapadły 22.11.2026. Przy każdym podano, kto je podjął. Ustalenia oznaczone **(robocze)** przyjęto tymczasowo, do potwierdzenia. Właściciel może je zmienić; każde z nich to parametr albo moduł, który łatwo przestawić.

> **Ważne (22.11.2026, później):** aktualny kod starego systemu jest na aktualnej gałęzi repozytorium AquaSmart-plugins (commit `49b0eca`, kopia robocza `AquaSmart-plugins-aktualne` obok tego repozytorium), a nie na `main`. Wersje: Doradca 1.1.60, auth 2.5.0, user 1.0.14, nowa wtyczka sklepów `aquasmart-shops` 0.4.0. Kierunek opisuje `docs/mapa-funkcjonalnosci.md` z tej gałęzi. Analiza z `docs/01-analiza/` (10–40) dotyczy starszych wersji i jest aktualizowana. Ustalenia „robocze” dla sklepów poniżej są **wycofane**: obowiązuje model z mapy (Shops 1.0: koszyk z wielu sklepów, decyzja sklepu, numer EAN-13, płatność poza platformą, odbiór osobisty). Obowiązują poziomy kont Drop / Tank / Fishroom zamiast okresu próbnego.

## Kierunek

- **Nowa wersja, nie przepisywanie.** Obecny system na WordPressie traktujemy jako prototyp: wzorujemy się na jego funkcjach i algorytmach, nie na kodzie. Główne funkcje zostają (możemy je wzbogacać, ale nie okrajać). Jeśli coś da się zrobić lepiej, robimy lepiej. Zmiany zachowania wymagają zgody i uzasadnienia. *(właściciel)*
- **Odchodzimy od WordPressa** z powodu ciągłych podatności i uzależnienia od wtyczek. *(właściciel)*
- **Etapy:** 1) analiza i projekt; 2) szkielet z kluczowymi funkcjami; 3) wygląd i branding; 4) funkcje zaawansowane, teksty, publikacja. *(właściciel)*
- **Aplikacje mobilne** w kolejnych etapach. Dlatego logikę trzymamy w operacjach domenowych, nie w ekranach, żeby później dodać API bez przepisywania. *(właściciel)*
- **Wygląd:** wzorujemy się na elevenlabs.io (etap 3). *(właściciel)*

## Technologia

- **Laravel 13 + Filament 5 + Livewire 4, PHP 8.4.** *(właściciel, po niezależnej recenzji)*
- **Baza danych MySQL 8.4** (wersja z długim wsparciem; 8.5 jest dopuszczalna). Skutek: wyszukiwanie wektorowe dla asystenta AI rozwiążemy osobno, gdy przyjdzie na nie czas. *(właściciel)*
- **Hosting** później. Zajmie się nim właściciel. Na razie wszystko działa lokalnie w `E:\workspace\aqua\tools`, nic na dysku C:. *(właściciel)*
- **Bez CI na GitHubie.** Bramką jakości jest lokalne `composer check` (styl kodu, analiza statyczna, testy na MySQL), obowiązkowe przed oddaniem każdego kroku. *(właściciel)*
- **Uproszczenia w modelu danych** (szczegóły w `11-model-domenowy.md`, sekcja 9): standardowa tabela `users`; zdarzenia wysyłane dopiero po zatwierdzeniu transakcji, bez własnej kolejki zdarzeń; jedna tabela wersji ustawień; bez pakietu `spatie/laravel-settings`; analiza statyczna od poziomu 6. Zawsze najpierw to, co wbudowane we framework. *(zespół)*
- **Graphify nie jest potrzebny**, bo projekt jest mały. *(zespół)*

## Finanse i parametry biznesowe

- **Wszystkie progi, ceny i okresy ustawia admin w panelu, nie w kodzie.** Dotyczy to progu publikacji sklepu (50 ofert, minimum 40, 3 miesiące), planów S/M/L/XL, prowizji, długości okresu próbnego i kampanii. Każda zmiana zostawia historię i może obowiązywać od wskazanej daty. *(właściciel)*
- **Panele od początku:** admin, sklep, akwarysta. *(właściciel)*
- **Na pilot bez kwot.** Plany i prowizje są skonfigurowane, ale nic nie jest naliczane, dopóki właściciel nie zatwierdzi cennika. *(robocze)*

## Sklepy (WYCOFANE robocze ustawienia; patrz uwaga na górze)

Logikę sklepu projektujemy od nowa i w całości, bo w prototypie jej praktycznie nie było. *(właściciel)* Pełny projekt jest w `10-domena-sklepu.md`. Robocze ustawienia z jego ostatniej sekcji:

- Klient kupuje u sprzedawcy, na jego stronie albo w sklepie stacjonarnym. Nie budujemy marketplace z koszykiem i płatnościami. *(robocze)*
- Na start klient może wysłać do sklepu zapytanie i wybrać placówkę. Rezerwacje i odbiór osobisty oraz automatyczne feedy produktowe (Google Merchant, Ceneo) przychodzą w etapie 4. *(robocze)*
- Sklep może dodawać produkty spoza bazy wiedzy. Pokazujemy je na stronie sklepu, ale nie w rekomendacjach doradcy. *(robocze)*
- Sklep po rejestracji przechodzi ręczną weryfikację u admina. Okres próbny liczy się od akceptacji. *(robocze)*
- Jeden NIP to jeden sklep z wieloma placówkami. Sklep ma właściciela i pracowników. *(robocze)*
- Do progów publikacji liczą się opublikowane, aktywne oferty. *(robocze)*

## Wygląd, UX i wydajność

- **Po zbudowaniu kluczowych funkcji robimy przegląd UX, designu i wydajności**, żeby nie powtórzyć błędów prototypu: nieczytelnych ekranów, zbyt długich formularzy, wolnego ładowania (np. wysyłania całego indeksu bazy wiedzy przy każdym wejściu na doradcę) i niespójnych komunikatów. *(właściciel)*
- **Intuicyjność ponad oryginalność.** Korzystamy ze sprawdzonych wzorców dużych serwisów: przewidywalna nawigacja, jedna główna akcja na ekranie, krótkie formularze krok po kroku, czytelne stany pusty / ładowanie / błąd, dostępność (WCAG AA), wersja mobilna od początku. *(właściciel)*
- **Ikony z darmowych, publicznych bibliotek.** Domyślnie Heroicons (używa ich już Filament), uzupełniająco Lucide. Żadnych własnych ani płatnych zestawów bez potrzeby. *(właściciel)*
- **Minimum patetycznych haseł.** Teksty konkretne i rzeczowe: mówią, co użytkownik zyska i co ma zrobić. Bez sloganów marketingowych. *(właściciel)*
- Wzorzec wizualny: elevenlabs.io (patrz „Kierunek”). *(właściciel)*

## Sposób pracy

- **Zrozumiałe nazwy:** w dokumentach nie używamy numerów ani skrótów typu „WP-02”, „D-15” czy „P3”. Kroki i ustalenia nazywamy po polsku, opisowo. *(właściciel)*

## Konta i dostęp (wdrożone)

- Bez okresu próbnego. Konto po aktywacji e-mailowej to Tank bezterminowo. Fishroom nadaje ręcznie administrator z datą końca i powodem. Brak jakichkolwiek danych dostępu oznacza Tank. Zawieszone konto widzi jeden komunikat. *(właściciel)*
- Jedna decyzja dostępu `ResolveAccess(aktor, funkcja, kontekst)` dla gościa, Tank, Fishroom, zawieszonych i administratora. Administrator ma dostęp do wszystkiego (podgląd). Wszystkie komunikaty odmowy pochodzą z tego jednego miejsca.
- Uproszczenie względem modelu domenowego (sekcja 7): zamiast `fishroom_grants` i `access_changes` z JSON-em są kolumny `users.fishroom_ends_at` i `users.suspended_at` oraz tabela `access_changes` (rodzaj, koniec Fishroom, aktor, powód). Pełne nadania z ceną i wersjami ustawień dodamy razem ze sprzedażą przez operatora.

## Baza wiedzy (wdrożone)

- Uproszczenie względem modelu domenowego (sekcja 7): zamiast rewizji, wydań i słowników są tabele `catalog_records` (klucz `(area, legacy_id)`), `catalog_entries` (treść rekordu w partii: surowe pola, znormalizowane wartości po `;`, tekst do wyszukiwania), `catalog_import_batches` (stan `pending`/`active`/`superseded`/`failed`, skróty plików, liczby, raport błędów, jedna aktywna partia wymuszona przez `UNIQUE(active_slot)`), `catalog_id_registry` (ID nigdy nie wracają do puli: klucz obcy z rekordów i blokada usuwania w modelu), `catalog_descriptions` (jeden opis na rekord, ręczna edycja nie jest nadpisywana importem) i `catalog_photos` (same metadane, zdjęcia z importu czekają na weryfikację).
- Import: `php artisan kb:import <katalog>` sprawdza całą paczkę (osiem plików: cztery TSV, dwa pliki opisów, rejestr, rekordy przyszłe), zapisuje partię i aktywuje ją atomowo. Ten sam skrót paczki nie zmienia nic. Paczka z błędami zostawia wyłącznie wpis `failed` z raportem plik/wiersz. Rejestr: `aktywny` → active, `przyszły` i `zarezerwowany-bez-danych` → future, `wstrzymany` → retired; klucze sprzętu i pielęgnacji rejestrowane z TSV.
- Odczyt dla innych modułów: `KnowledgeBase\Contracts\Catalog` (`snapshot`, `find`, `search`); wyszukiwanie od liczby znaków i limitu z ustawień, bez rozróżniania wielkości liter i polskich znaków.
- Panel admina: „Baza wiedzy” (lista z wyszukiwaniem i filtrami, widok rekordu, edycja opisu i metadanych zdjęć) oraz „Importy” (przywracanie wcześniejszej partii). Edycja atrybutów rekordu czeka na osobny krok.

## Doradca: Drop i Tank (wdrożone)

- Silnik w `Advisor\Engine` jest czystym PHP: `DropEngine` (krótki dobór gościa, zawężanie puli jak w starym silniku, ocena bez wag) i `TankEngine` (wagi 4/3/2/1 z awansem). Wejście to `TankProfile` z pasmami jako stabilnymi kodami (`nano`, `small`, …), etykiety z katalogu służą tylko do wyświetlania i odczytu bazy wiedzy. Dzięki temu „Nano (< 40L)” przechodzi przez formularz bez ryzyka utraty znaku `<`.
- Rekordy silnika buduje `Advisor\FaunaPool` z `Catalog::snapshot`. Wejście Tank (profil z formularza) zastąpi później zbiornik z modułu Akwarium bez zmiany silnika.
- Wielowartościowe pasmo akwarium rekordu liczy się według najlepszej oceny z rozpoznanych pasm („Wszystkie” daje 3). Wykonanie 1.1.60 pomijało to kryterium.
- Prezentacja remisu: Tank pokazuje grupę równorzędnych (ten sam procent i wektor ocen), a przy równym procencie i różnych wektorach jedną kartę z kryterium rozstrzygającym; opis odsłania się dla jednej karty grupy. Drop pokazuje „N gatunków z wynikiem X%” i dopiero „Pokaż przykład” odsłania jedną kartę.
- Limit Drop: podpisane (szyfrowane) cookie `drop_allowance` z identyfikatorem, początkiem okna i kluczami żądań; zużywa się tylko przy odsłonięciu karty, klucz jest powiązany z profilem żądania. Pusty wynik, ekran remisu i błąd nie zużywają limitu. Licznik IP (hash HMAC z sekretem aplikacji) działa za flagą `drop.ip_enabled`; techniczny limit prób na IP działa zawsze. Ustawienia: `drop.free_selections`, `drop.cookie_days`, `drop.ip_limit`, `drop.ip_days`, `drop.attempt_rate`.
