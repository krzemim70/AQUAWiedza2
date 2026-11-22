# Przegląd stagingu test.aikwarium.pl (22.11.2026)

Przegląd ręczny w przeglądarce: panel admina zalogowanym kontem administratora (tylko odczyt), front jako gość. Logowanie podanym kontem testowym nie powiodło się („Nieprawidłowy adres e-mail lub hasło”), więc strefy dla zalogowanych nie zostały jeszcze obejrzane.

## Wtyczki (16, w tym 13 włączonych)

| Wtyczka | Wersja | Stan | Uwagi |
|---|---|---|---|
| Aikwarium Shops | 0.4.0 | włączona | opis „etap 0”: placówki, oferty, zgłoszenia z odbiorem osobistym |
| AquaSmart Auth | 2.5.0 | włączona | |
| AquaSmart User | 1.0.14 | włączona | |
| AquaSmart V1.0 (Doradca) | 1.1.60 | włączona | shortcode `[akwa_filtr_r37]` |
| Better Search Replace | 1.4.11 | włączona | narzędzie do zamian w bazie, ryzykowne na stałe |
| Blogger Importer | 0.9.3 | włączona | niepotrzebna |
| Complianz (cookies) | 7.5.5 | włączona | do aktualizacji |
| Filester – File Manager Pro | 2.1.4 | włączona | **menedżer plików w panelu WP: duże ryzyko bezpieczeństwa** |
| Fluent Forms | 6.2.8 | wyłączona | |
| FluentSMTP | 2.4.1 | włączona | poczta |
| Importer WordPressa | 0.9.6 | włączona | niepotrzebna |
| LiteSpeed Cache | 7.9.1 | włączona | |
| Matomo Analytics | 5.13.1 | włączona | analityka |
| Nav Menu Roles | 2.1.3 | włączona | menu zależne od roli |
| Query Monitor | 4.0.7 | wyłączona | |
| Wordfence Security | 9.0.2 | **wyłączona** | |

Dziewięć wtyczek ma oczekujące aktualizacje, a WordPress sygnalizuje 12 aktualizacji. Motyw: Astra. To potwierdza powód odejścia od WordPressa: utrzymanie wtyczek, aktualizacji i bezpieczeństwa.

## Ekrany admina Aikwarium

| Ekran | Co robi | Wnioski dla v2 |
|---|---|---|
| Aikwarium → Użytkownicy (`aqs-users`) | Tabela kont: ID, imię, e-mail, rola, status (bezpłatne / trial / aktywne / trial wygasł / zawieszone), koniec trialu, data rejestracji. Zmiana statusu listą rozwijaną i przyciskiem „Zapisz” w każdym wierszu. Filtr statusu i roli. | W v2: lista z filtrami, wyszukiwaniem i paginacją. Zmiana statusu jako akcja z wymaganym powodem i historią, bez formularza w każdym wierszu. |
| Sklepy → Weryfikacja | Kolejka placówek do weryfikacji (pusta). | Kolejka weryfikacji z decyzją i powodem. |
| Sklepy → Lokalizacje | Słownik miejscowości, w którym współrzędne wpisuje się ręcznie. Miejscowość dostaje automatycznie najbliższe miasto wojewódzkie. | Słownik miejscowości z gotowej bazy (np. TERYT/GUS), bez ręcznego wpisywania współrzędnych. |
| Sklepy → Abonamenty | Placówki ze statusem (przygotowanie / aktywny), startem planu, miesiącem i liczbą „zbiorników ponad 20”. Stany abonamentu: aktywny / zawieszony / wygasły / wypowiedziany. | Abonament wyłączony w pilocie (decyzja z 18.11), ale model warto przygotować. |
| Sklepy → Audyt | Dziennik zdarzeń modułu sklepów. | Wspólny dziennik zdarzeń. |
| Sklepy → Ustawienia | Moderacja ofert; dni na odpowiedź klienta na korektę; dni do pseudonimizacji kontaktów; dzienny limit zgłoszeń klienta; linki sklepów w wynikach Doradcy; adres powiadomień o nowych placówkach; adres strony wyszukiwarki; usuwanie danych przy odinstalowaniu. | Wszystko trafia do wersjonowanych ustawień w naszym panelu admina. |
| Narzędzia → Aikwarium — dane | Źródło bazy wiedzy: „lokalne bazy wtyczki” albo pakiet importowany z Mediateki. Aktywne pliki: fauna **200**, flora **194**, sprzęt **108**, pielęgnacja **110** rekordów. | Baza wiedzy w bazie danych v2, z importem, wersjami i edycją w panelu. Liczby różnią się od starego repozytorium (184/201). |
| Narzędzia → Zdjęcia JJ | Import zdjęć gatunków. | Zdjęcia jako pliki z metadanymi (autor, licencja, weryfikacja). |
| Narzędzia → Znacznik opisu | Narzędzie do opisów gatunków. | Edycja opisów w panelu bazy wiedzy. |

Ustawienia sklepów i źródła danych są rozproszone w trzech miejscach menu (Aikwarium, Aikwarium Sklepy, Narzędzia). W v2 wszystko będzie w jednym panelu admina.

## Strony (15)

Strona główna („nowy start”), `/portal/` (to samo co strona główna, duplikat), `/doradca/`, `/dashboard/`, `/moje-akwarium/`, `/redfield/`, `/poznajmy-sie/` (ankieta), `/kup/` i `/oferty-w-twojej-okolicy/` (dwie strony z identyczną treścią: „Oferty w Twojej okolicy… Wybierz produkt w Doradcy”), `/panel-sklepu/`, `/logowanie/`, `/rejestracja/`, regulamin, polityka prywatności, polityka cookies.

## Dobór gościa (Drop): obserwacje

- Formularz ma cztery listy: wielkość zbiornika, wielkość ryby („Dopasuj do zbiornika”), temperatura, pH.
- **Błąd:** pierwsza pozycja listy zbiornika („Nano (< 40L)”) wygląda na wybraną (ma wartość), ale po kliknięciu „Pokaż dobór” pojawia się komunikat „Wybierz wielkość zbiornika.”. Prawdopodobnie serwer gubi wartość zawierającą znak `<`. Gość nie może więc wybrać Nano.
- Po wybraniu „Małe” i wartości domyślnych wynik brzmi: zwycięzca „Gupik Pawie Oczko — 100%”, dopisek „Oraz jeszcze 35 innych dopasowań z takim samym wynikiem”, trzy rozmyte wiersze i zachęta „Zobacz pozostałe 80 dopasowań — załóż darmowe konto”.
- **Problem UX:** przy domyślnych odpowiedziach 36 gatunków ma 100%, więc wskazanie „zwycięzcy” jest przypadkowe (kolejność alfabetyczna lub z pliku). Wynik nie pomaga w decyzji. W v2 przy remisie trzeba albo dopytać o kolejny parametr, albo uczciwie pokazać grupę, a nie jednego arbitralnego zwycięzcę.
- Treść strony jest dobra: konkretna, krótka i bez patosu. Wyjątkiem jest tytuł strony „Z PASJI DO AKWARYSTYKI”.

## Strefy po zalogowaniu (konto testowe użytkownika)

- Konto testowe ma w WordPressie rolę „Użytkownik Aikwarium”, ale **nie ma wiersza subskrypcji** (prawdopodobnie założone ręcznie w panelu WP).
- **Trzy sprzeczne komunikaty dla tego samego konta:**
  - dashboard: „Status subskrypcji: Brak subskrypcji”;
  - Moje Akwarium: „Twoja subskrypcja wygasła. Dane są dostępne tylko do odczytu.” (zakładki: Moje zbiorniki, Dziennik, Parametry wody, Przypomnienia);
  - Doradca: „Twój okres próbny wygasł. Skontaktuj się z nami aby aktywować konto.”, mimo że w obecnym modelu okresu próbnego nie ma (konto bezpłatne jest bezterminowe).
- **Admin nie może tego naprawić w panelu Aikwarium.** Lista „Aikwarium → Użytkownicy” pokazuje tylko konta z wierszem subskrypcji, więc konta bez niego tam nie ma.
- Wniosek dla v2: jedna funkcja decydująca o dostępie, a konto bez danych o abonamencie domyślnie dostaje poziom bezpłatny (Tank). Lista kont w adminie obejmuje wszystkie konta. Komunikaty o dostępie pochodzą z jednego miejsca.
- Przepływu Tank nie obejrzano na żywo: konto testowe jest zablokowane, a nowe konto wymaga aktywacji mailowej. Algorytm Tank jest opisany w `60-aktualizacja-do-galezi-aktualnej.md` (sekcje 2.2–2.3).
- Wygląd stref po zalogowaniu: ciemne tło ze zdjęciem roślin, biała karta z treścią, czerwony pasek komunikatu. Nagłówki: „Twoje akwaria w jednym miejscu”, „Dobierzmy ryby do Twojego akwarium”.

## Konto administratora w serwisie

- Doradca: „Twój okres próbny wygasł. Skontaktuj się z nami aby aktywować konto.” **Administrator też jest zablokowany.**
- Moje Akwarium: „Twoja subskrypcja wygasła. Dane są dostępne tylko do odczytu.”
- Kalkulator Redfielda: „Kalkulator dostępny dla aktywnych subskrybentów.”
- Panel sklepu: „Twoja placówka jest w weryfikacji. Dane pobraliśmy z Twojego konta.” Konto admina jest traktowane jak sklep w weryfikacji.
- Wniosek: wiersz subskrypcji powstaje wyłącznie przy aktywacji mailowej (`aquasmart-auth/includes/class-aqs-trial.php:49–62`). Konta założone inaczej, w tym administrator, są blokowane, a panel nie umożliwia naprawy. W v2 administrator ma pełny dostęp do wszystkich stref (do podglądu), a brak danych o abonamencie oznacza poziom bezpłatny.

### Jak odblokować konto testowe na stagingu (dla właściciela, z dostępem do MySQL)

```sql
INSERT INTO wp_aqs_subscriptions (user_id, role, status, trial_start, trial_end, notes, created_at, updated_at)
SELECT ID, 'aqs_user', 'free', NOW(), NULL, 'staging: ręczne odblokowanie', NOW(), NOW()
FROM wp_users WHERE user_email = '<e-mail konta testowego>'
  AND NOT EXISTS (SELECT 1 FROM wp_aqs_subscriptions s WHERE s.user_id = wp_users.ID);
```

## Po odblokowaniu kont (właściciel dopisał wiersze subskrypcji)

**Administrator:** `/doradca/` pokazuje „DORADCA - WERSJA PEŁNA”: prowadzony dobór z pytaniem „W czym mam Ci teraz pomóc?” i opcjami Fauna / Flora / Sprzęt / Pielęgnacja / Znajdź produkt. Na ekranie dla użytkownika widnieje techniczna etykieta „Aikwarium 103.60 (AquaSmart 1.1.60) build 20261018-A48-103.36”. W v2 nie pokazujemy numerów wersji.

**Konto testowe (bezpłatne):**
- Moje Akwarium → formularz „Dodaj zbiornik”: nazwa, wielkość (pasma), typ, wielkość ryby, temperatura, pH, notatka. Wybór z list działa dobrze, bez wpisywania litrów.
- Po dodaniu: „W wersji STARTOWEJ możesz zapisać jeden zbiornik. Kolejne zbiorniki są w wersji PEŁNEJ. Przejdź na wersję PEŁNĄ.” To stare nazwy poziomów (mapa: Drop / Tank / Fishroom). Podsumowanie zbiornika pokazuje tylko wielkość i typ, bez temperatury, choć została wybrana.
- **Niezgodność z mapą:** konto bezpłatne na `/doradca/` dostaje **pełny prowadzony Doradca** („wersja pełna”), a strona startowa po zalogowaniu mówi „Masz konto — korzystaj z pełnego Doradcy”. Widok Tank (ranking z własnego zbiornika, jeden zwycięzca z „Dowiedz się więcej”) nie jest podpięty do żadnej strony ani pozycji menu. Według mapy pełny doradca i wyszukiwarka mają być dopiero w płatnym Fishroom.
- Strona startowa po zalogowaniu ma tylko to jedno zdanie i link, bez informacji o zbiorniku, ostatnim pomiarze ani kolejnym kroku.
- Menu po zalogowaniu: Start, Doradca, Moje Akwarium, Kalkulator Redfielda, Moje konto, Wyloguj. Stopka: Regulamin, Polityka prywatności, Kontakt (mailto).

**Wnioski dla v2:** strona startowa zalogowanego użytkownika to pulpit z jego zbiornikiem, ostatnim pomiarem, wynikiem doboru i jedną główną akcją. Poziomy nazywamy tak samo wszędzie. Zawartość ekranu zależy od jednej decyzji o dostępie.
