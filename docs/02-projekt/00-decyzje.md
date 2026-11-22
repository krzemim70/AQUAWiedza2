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

- Administrator może aktywować konto ręcznie, pojedynczo lub grupowo, z opcjonalnym powodem zapisywanym w historii. Potwierdzenie adresu e-mail można wyłączyć w ustawieniach; wtedy nowe konta są aktywne od razu. Ustawienie jest przeznaczone dla środowisk testowych: przy wyłączonym potwierdzeniu odpowiedź rejestracji różni się dla nowego i zajętego adresu, więc na produkcji musi pozostać włączone.

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

## Sklepy: oferty (wdrożone)

- Sklep powstaje w chwili akceptacji zgłoszenia firmy (weryfikacja zostaje w „Zgłoszenia sklepów”) w stanie „w przygotowaniu”, razem z członkostwem właściciela. Jeden sklep na NIP; akceptacja zgłoszenia z zajętym NIP-em jest odrzucana w całości. Stan „w weryfikacji” istnieje w słowniku stanów, ale nic go jeszcze nie ustawia.
- Dostęp do panelu sklepu wynika z członkostwa w co najmniej jednym sklepie (`shop_members`), nie z typu konta. Panel `shop` jest wielodostępny (tenant = sklep), a każda akcja domenowa sprawdza członkostwo na serwerze. Właściciel prowadzi placówki i zespół (dodaje istniejące konto po adresie e-mail jako pracownika), pracownik oferty i ilości. Jeden właściciel na sklep wymusza unikalna kolumna wyliczana.
- Oferta wskazuje rekord katalogu kluczem `(area, legacy_id)` bez klucza obcego (katalog należy do innego modułu). Przy zapisie i publikacji klucz sprawdza `Catalog::labels`. Tożsamość oferty to sklep, placówka, rekord i znormalizowany wariant. Cena w groszach z granicami w ustawieniach (`shops.offer.min_price_grosze`, `shops.offer.bounds`), ważność `[od, do)`, `CHECK` na cenę i daty w bazie.
- Dostępną ilość zmienia wyłącznie `SetAvailableQuantity`: podaje się nową ilość, potwierdzenie stanu fizycznego i oczekiwaną wersję, pod blokadą wiersza oferty; ilość zarezerwowana pozostaje bez zmian, a każda zmiana (także stan otwarcia) zapisuje wiersz historii. Rezerwacje pojawią się w kroku z koszykiem.
- Aktywacja: po publikacji oferty, zmianie placówki, przyznaniu odstępstwa albo zmianie ustawienia `shops.activation` (próg 50, limit 150) sklep w przygotowaniu z czynną placówką staje się aktywny, gdy liczba opublikowanych, niewygasłych ofert w czynnych placówkach wskazujących aktywne rekordy katalogu (także z ilością 0) osiągnie próg albo gdy obowiązuje odstępstwo administratora (powód i zakres czasu, bez nakładania). Aktywność zostaje po spadku liczby ofert. Limit ofert liczy opublikowane niewygasłe oferty, szkice go nie zajmują. Wstrzymanie i wznowienie wymagają powodu.
- Publiczny odczyt: `Shops\Contracts\PublicOffers::forRecords(area, ids)` zwraca oferty opublikowane, w oknie ważności, aktywnych sklepów, czynnych placówek i aktywnych rekordów katalogu (od najtańszej), z dostępną ilością (0 = niedostępna). Wygaśnięcie oznacza godzinowe zadanie, ale odczyt sprawdza `valid_to` natychmiast. Doradca Tank pokazuje „Gdzie kupić” przy jednoznacznym zwycięzcy.
- Odłożone: moderacja ofert (oferty od razu publikuje sklep), `shop_verifications` z historią prób i dokumentami (zastępuje je zgłoszenie), klucze operacji dla zmian ilości, zakończenie udziału sklepu, raport dobowy.

## Sklepy: koszyk i zamówienia (wdrożone)

- Koszyk to jeden otwarty koszyk na konto (`shop_cart_items`, bez rezerwacji), dla poziomów Tank i Fishroom (`ResolveAccess`, funkcja `shop_cart`). Pozycja: oferta widoczna wg tej samej polityki co „Gdzie kupić” (`PublicOffers::forOffers`), ilość w granicach ustawienia `shops.orders` (domyślnie 1–50) i nie większa niż dostępna ilość, zakaz kupna we własnym sklepie, jedna placówka odbioru na sklep w koszyku. Zmiana ceny po dodaniu wymaga jej zaakceptowania przed wysyłką.
- Wysyłka (`SubmitCart`) jest atomowa: blokada wiersza klienta, klucz ponowienia (ten sam klucz zwraca pierwszy wynik; brak skrótu treści, więc inna treść pod tym samym kluczem nie daje konfliktu), jedno zamówienie per sklep (nagłówek, pozycje z migawką nazwy, wariantu, ceny i wersji warunków, migawka kontaktu: nick, e-mail i telefon podany w koszyku), dzienny limit zamówień klienta (domyślnie 5; każdy sklep zużywa jedną jednostkę; dzień lokalny `Europe/Warsaw`), opróżnienie koszyka. Przy przekroczeniu limitu lub problemie z pozycją cały koszyk zostaje otwarty.
- Decyzja sklepu obejmuje całe zamówienie, jedna transakcja z blokadą nagłówka, ofert (rosnąco po id) i licznika EAN. `ApproveOrder` sprawdza ważność, cenę, wersję warunków i dostępną ilość każdej pozycji, rezerwuje wszystko razem (dostępna → zarezerwowana, ruch w historii ilości, wiersz `shop_reservations`) i nadaje EAN-13; przy niedoborze nic się nie zmienia. `RejectOrder`: kod `brak_aktualnej_oferty` albo `inne` (opis obowiązkowy), także nadaje EAN. Powtórka tej samej decyzji nie ma skutku, sprzeczna decyzja daje błąd.
- EAN-13: `2` + numer placówki (4 cyfry, `shop_locations.ean_no`, nadawany przy pierwszej decyzji pod blokadą licznika) + sekwencja globalna (7 cyfr, jednorzędowa `shop_ean_sequence`) + cyfra kontrolna (wagi 1 i 3). Wyczerpanie numeracji wycofuje decyzję i zapisuje wpis krytyczny w logu.
- Dalsze stany (`AdvanceOrder`): potwierdzone → opłacone (ręczne potwierdzenie pełnej kwoty, `shop_order_payments`) → w przygotowaniu → gotowe do odbioru → odebrane (rezerwacje zużyte); wydanie bez kodu. `CloseOrder` (klient dla nowego, członek sklepu dla otwartego, administrator): powód obowiązkowy, po wpływie także opis rozliczenia, aktywne rezerwacje wracają do dostępnej ilości dokładnie raz. Każda zmiana zapisuje wiersz historii (`shop_order_events`: kto, kiedy, z jakiego stanu).
- Powiadomienia (log): kolejkowane wiadomości `OrderNotice` (do zespołu sklepu o nowym zamówieniu, bez danych klienta; do klienta o decyzji i gotowości) i `DailyReportNotice`, wysyłane przez `DB::afterCommit`. Raport dobowy `BuildDailyReports` uruchamia się co godzinę, działa po godzinie z ustawienia (10:00 Europe/Warsaw), obejmuje poprzedni dzień lokalny i jest jeden na sklep i dzień (`UNIQUE` + blokada sklepu), zaległe dni nadrabia. Pseudonimizacja (`PseudonymizeOrders`, codziennie): 90 dni po stanie końcowym (ustawienie) usuwa kontakt, odłącza klienta, czyści opisy; odczyt ukrywa kontakt już po terminie.
- Ekrany: koszyk i „Moje zamówienia” (lista, szczegóły z osią stanów, numer jako tytuł przelewu z kopiowaniem) w `/koszyk` i `/zamowienia`; panel sklepu: Zamówienia (filtr stanu, szczegóły z kontaktem, czynności zależne od stanu); admin: lista zamówień tylko do odczytu.
- Uproszczenia pilota: brak zapisu rachunku sklepu w zamówieniu (instrukcja przelewu = nazwa sklepu, kwota i EAN), brak terminów wygaśnięcia zamówień, brak rejestru dostarczeń powiadomień (zwykłe powiadomienia Laravel w kolejce), brak widoku raportu dobowego w panelu (raport trafia e-mailem), brak powiadomienia klienta o wpływie i zamknięciu, zamówienie ma jedną ilość (żądana = potwierdzona).
