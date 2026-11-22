# Analiza produkcyjnej strony publicznej aikwarium.pl

Data analizy: 2026-11-22
Zakres: publiczne strony serwisu https://aikwarium.pl (WordPress), bez logowania i bez wysyłania formularzy.
Metoda: pobieranie przez WebFetch (odpowiedzi modelu streszczającego, nie surowy HTML). Tytuły, nagłówki i listy pól należy potwierdzić ręcznie przed przyjęciem jako wymagania.

Sprawdzono 17 adresów: 7 stron publicznych z treścią, 4 strony chronione logowaniem (lub niejednoznaczne), 4 odpowiedzi 404, 2 mapy witryny.

---

## Nawigacja

**Nagłówek (header):**
- Logo (tekst "aikwarium_.pl_" w kodzie logo; widoczny nagłówek: "Aikwarium – Z PASJI DO AKWARYSTYKI").
- Zaloguj się -> /logowanie/
- Załóż konto -> /rejestracja/
- Link pomocniczy "Przejdź do treści" (skip link, dostępność).

**Stopka (footer):**
- Regulamin -> /regulamin/
- Polityka prywatności -> /polityka-prywatnosci/
- Polityka cookies -> /polityka-plikow-cookies-eu/
- Kontakt -> mailto:biuro@aikwarium.pl (link mailto, nie strona WWW)
- Informacja: tło grafiki wygenerowane z pomocą AI.
- Przycisk "Przewijanie do góry".

**Strona główna:** narzędzie doboru ryb z czterema polami (wielkość zbiornika, wielkość ryby (długość), temperatura, pH wody) i przyciskiem "Pokaż dobór". Brak menu głównego poza nagłówkiem.

**Struktura:** płaska. Poza stroną główną wszystkie funkcje użytkowe są za logowaniem; publiczne są tylko strony prawne i formularze konta.

---

## Lista stron

| # | URL | Status HTTP / wynik | Tytuł / nagłówek | Cel (jedno zdanie) | Wymaga logowania |
|---|-----|---------------------|------------------|--------------------|------------------|
| 1 | https://aikwarium.pl/ | OK | brak potwierdzonego `<title>` (nagłówek "Dobierz ryby do swojego akwarium") | Strona startowa z narzędziem doboru ryb i zapowiedzią wersji PEŁNEJ | Nie |
| 2 | https://aikwarium.pl/logowanie/ | OK | "logowanie – Aikwarium" / "Zaloguj się" | Logowanie e-mailem i hasłem | Nie (to ekran logowania) |
| 3 | https://aikwarium.pl/rejestracja/ | OK | "Rejestracja – Aikwarium" / "Załóż konto" | Rejestracja użytkownika indywidualnego lub sklepu zoologicznego | Nie |
| 4 | https://aikwarium.pl/regulamin/ | OK | "Regulamin platformy Aikwarium.pl" | Regulamin korzystania z platformy | Nie |
| 5 | https://aikwarium.pl/regulamin-platformy-aikwarium/ | OK | "Regulamin platformy Aikwarium.pl" | Ten sam tytuł i zakres co strona 4 (prawdopodobny duplikat) | Nie |
| 6 | https://aikwarium.pl/polityka-prywatnosci/ | OK | "Polityka prywatności" | Informacja o przetwarzaniu danych osobowych (RODO) | Nie |
| 7 | https://aikwarium.pl/polityka-plikow-cookies-eu/ | OK | "Polityka plików cookies (EU)" | Opis cookies: tylko niezbędne, bez analityki i marketingu; data wejścia w życie 1.10.2026 | Nie |
| 8 | https://aikwarium.pl/doradca/ | Brak treści publicznej (w sitemapie) | Wynik zwrócił ekran logowania | Prawdopodobnie właściwy doradca AI | Tak (lub niejasne) |
| 9 | https://aikwarium.pl/dashboard/ | Wymaga logowania | Ekran "Zaloguj się" | Panel użytkownika | Tak |
| 10 | https://aikwarium.pl/moje-akwarium/ | Wymaga logowania (w sitemapie) | Ekran "Zaloguj się" | Moje akwarium (zbiorniki, pomiary) | Tak |
| 11 | https://aikwarium.pl/redfield/ | Niejasne (w sitemapie) | Wynik zwrócił ekran logowania | Nieznane; brak wzmianki w treści | Prawdopodobnie tak |
| 12 | https://aikwarium.pl/sklep/ | 404 | - | Wzorzec "sklep" z zadania, nieistniejący | - |
| 13 | https://aikwarium.pl/polityka-cookies/ | 404 | - | Druga ścieżka polityki cookies podana w treści strony 7 | - |
| 14 | https://aikwarium.pl/kontakt/ | 404 | - | Kontakt (w rzeczywistości mailto) | - |
| 15 | https://aikwarium.pl/wp-sitemap.xml | 404 | - | Indeks map witryny WordPress | - |
| 16 | https://aikwarium.pl/sitemap.xml | Przekierowanie do mapy 17 | - | Mapa witryny | - |
| 17 | https://aikwarium.pl/wp-sitemap-posts-page-1.xml | OK | - | Lista stron z mapy: 10 adresów (w tym strony 1-11) | - |

Uwaga: /doradca/ i /dashboard/ zostały w jednej serii zapytań zwrócone najpierw jako 404 lub z treścią logowania. Wynik jest niespójny i wymaga ponownego sprawdzenia w przeglądarce.

---

## Szczegóły stron

### 1. Strona główna (/)
- **Cel:** wejście do serwisu; użytkownik wybiera parametry zbiornika i ryby.
- **Sekcje:** "Dobierz ryby do swojego akwarium"; blok "Wersja PEŁNA – już wkrótce".
- **Formularz (pola):** wielkość zbiornika, wielkość ryby (długość), temperatura, pH wody (selektory); przycisk "Pokaż dobór".
- **CTA:** "Pokaż dobór" (główne działanie), "Zaloguj się", "Załóż konto".
- **Uwagi:** narzędzie doboru jest dostępne bez konta; wyniki nie zostały sprawdzone (nie wysyłano formularza).

### 2. Logowanie (/logowanie/)
- **Cel:** logowanie do konta.
- **Sekcje:** nagłówek "Zaloguj się"; podtytuł o doradcy AI; blok "Wersja PEŁNA".
- **Formularz:** adres e-mail, hasło; przycisk "Zaloguj się".
- **CTA/linki:** "Załóż konto", "Nie masz konta? Zarejestruj się", "Zapomniałem hasła", "Rozumiem" (zamknięcie baneru).
- **Uwagi:** brak komunikatów błędów. Pomocnicza informacja: logowanie adresem e-mail podanym przy rejestracji. Brak widocznego placeholdera.

### 3. Rejestracja (/rejestracja/)
- **Cel:** założenie konta użytkownika albo sklepu zoologicznego.
- **Sekcje:** "Załóż konto"; baner "Wersja PEŁNA – już wkrótce" (26 zł brutto/mies.).
- **Formularz:**
  - pola wspólne: imię i nazwisko, e-mail, hasło, powtórz hasło;
  - typ konta: użytkownik indywidualny lub sklep;
  - pola dla sklepu: nazwa sklepu, NIP, adres sklepu, adres URL sklepu, telefon kontaktowy;
  - zgody: akceptacja regulaminu (checkbox), zgoda na newsletter/marketing (checkbox opcjonalny);
  - ukryte pole antyspamowe (ma pozostać puste).
- **CTA:** "Zarejestruj się"; "Zaloguj się" i "Załóż konto" w nagłówku.
- **Uwagi:** zdjęcie w sekcji hero bez tekstu alternatywnego (alt).

### 4. Regulamin (/regulamin/)
- **Cel:** warunki korzystania z platformy (konta, narzędzie doradcze, oferty sklepów, subskrypcje, płatności, odstąpienie, reklamacje, dane, odpowiedzialność).
- **Sekcje:** § 1 Postanowienia ogólne, § 2 Definicje, § 3 Usługi, § 4 Charakter doradczy i edukacyjny, § 5 Rejestracja i konto, § 6 Konta sklepów, ogłoszenia i oferty, § 7 Faza pilotażowa, § 8 Okres próbny, subskrypcja i płatności, § 9 Prawo odstąpienia, § 10 Reklamacje, § 11 Zasady korzystania, § 12 Dane osobowe, § 13 Dostępność i przerwy, § 14 Odpowiedzialność operatora, § 15 Usunięcie konta, § 16 Newsletter i komunikacja, § 17 Zmiany regulaminu, § 18 Prawo właściwe i spory, § 19 Postanowienia końcowe, Załącznik nr 1 (wzór formularza odstąpienia).
- **Uwagi:** patrz sekcja "Problemy".

### 5. Regulamin platformy (/regulamin-platformy-aikwarium/)
- **Cel:** ten sam dokument co strona 4 pod innym adresem.
- **Sekcje:** § 1-§ 19 i Załącznik nr 1 (zgodnie z nagłówkami; pełnej zgodności treści nie potwierdzono).
- **Uwagi:** prawdopodobny duplikat treści (problem SEO i spójności prawnej).

### 6. Polityka prywatności (/polityka-prywatnosci/)
- **Cel:** informacja o przetwarzaniu danych osobowych (RODO).
- **Sekcje:** informacje ogólne; administrator i kontakt; kategorie danych; cele i podstawy prawne; obowiązek podania danych; odbiorcy; transfer poza EOG; okresy przechowywania; prawa osoby; eksport danych; decyzje automatyczne; bezpieczeństwo; usunięcie konta; wiek 18+; cookies; linki zewnętrzne; zmiany polityki; kontakt.
- **Uwagi:** patrz "Problemy".

### 7. Polityka plików cookies (EU) (/polityka-plikow-cookies-eu/)
- **Cel:** opis cookies. Deklaracja: tylko cookies niezbędne, bez analityki, reklam i marketingu.
- **Sekcje:** § 1 Informacje ogólne; § 2 Czym są cookies; § 3 Cookies stosowane na Platformie; § 4 Statystyki techniczne; § 5 Zarządzanie cookies; § 6 Zmiany polityki.
- **Uwagi:** data wejścia w życie to 1.10.2026, czyli przed datą analizy; warto potwierdzić, że zmiana została wdrożona.

### 8. Doradca (/doradca/) - do potwierdzenia
- W sitemapie. Pobranie zwróciło ekran logowania (raz 404). Na podstawie nazwy to właściwe narzędzie doradcze, dostępne po zalogowaniu. Wymaga ręcznego sprawdzenia.

### 9. Dashboard (/dashboard/) - za logowaniem
- Pobranie zwróciło ekran "Zaloguj się" z formularzem; nie ujawnia zawartości panelu.

### 10. Moje akwarium (/moje-akwarium/) - za logowaniem
- W sitemapie. Pobranie zwróciło ekran logowania. Z treści strony 2 i wzmianek w teaserze wynika, że to moduł śledzenia zbiorników i pomiarów.

### 11. Redfield (/redfield/) - nieznane
- W sitemapie. Pobranie zwróciło ekran logowania; nie wiadomo, czy to marka, sklep czy produkt. Wymaga wyjaśnienia z zespołem.

### 404 (bez treści)
- /sklep/ - 404. Brak publicznego katalogu sklepów; wzorzec /sklep/{slug}/ nie został potwierdzony.
- /polityka-cookies/ - 404 (druga ścieżka w treści polityki cookies).
- /kontakt/ - 404. Kontakt jest tylko przez mailto.
- /wp-sitemap.xml - 404.

---

## Treści do ponownego użycia

Krótkie parafrazy, bez długich cytatów.

- **Hasło marki:** "Z pasji do akwarystyki" (nagłówek).
- **Główny komunikat:** dobór ryb do zbiornika na podstawie wielkości zbiornika, wielkości ryby, temperatury i pH.
- **Zapowiedź płatnej wersji PEŁNEJ (26 zł brutto/mies., "już wkrótce"):** dobór ryb do własnych parametrów wody, rankingi i porównania gatunków, dziennik/śledzenie akwarium z pomiarami, lokalne oferty sklepów.
- **Charakter usługi:** doradztwo i edukacja, nie gwarancja ani zalecenie zakupu (§ 4 regulaminu). Warto zachować w nowej wersji.
- **Podział ról:** użytkownik indywidualny vs sklep zoologiczny (z danymi firmy i NIP).
- **Prywatność:** deklaracja braku cookies analitycznych i reklamowych (uproszczenie do banera cookies w nowej wersji).
- **Tekst pomocniczy logowania:** informacja o logowaniu adresem e-mail podanym przy rejestracji.

---

## Co wymaga logowania

- **Doradca doboru ryb** (/doradca/) - prawdopodobnie tylko po zalogowaniu (niepotwierdzone).
- **Panel użytkownika** (/dashboard/).
- **Moje akwarium** (/moje-akwarium/) - zbiorniki i pomiary.
- **Konto sklepu** - dane firmy, oferty, ogłoszenia (wg regulaminu §6); brak publicznych stron sklepów.
- **Wersja PEŁNA** - zapowiedziana, nieuruchomiona (płatna, 26 zł/mies.), więc nie jest jeszcze dostępna dla nikogo.
- Nazwa "AquaSmart" nie pojawia się na publicznych stronach; używana jest nazwa "Aikwarium".

---

## Problemy zauważone

**Treści prawne i placeholdery:**
- Regulamin §6 i innych miejsc: termin "Ministrona" w różnych odmianach (prawdopodobnie błąd tłumaczenia lub niedokończony termin dla profilu sklepu).
- Regulamin: "Konto Łączone" pojawia się w formularzu odstąpienia, ale nie jest zdefiniowane w dokumencie.
- Regulamin §14: zdanie wielokrotnie powtarza słowo "zaleceń" (niedopracowana redakcja).
- Polityka prywatności: pole operatora płatności oznaczone jako do uzupełnienia; brak inspektora ochrony danych, dane kontaktowe "zostaną dodane" w przyszłości.
- Polityka prywatności: funkcje warunkowe (raport kliknięć, eksport danych, dostawca bezpieczeństwa Wordfence "jeśli aktywny") - niejasne, czy działają.
- Polityka cookies: dwie różne ścieżki (/polityka-cookies/ w treści, 404; /polityka-plikow-cookies-eu/ w menu i stopce). Link w treści jest zepsuty.
- Regulamin dwukrotnie (/regulamin/ i /regulamin-platformy-aikwarium/) - duplikat.

**Techniczne i dostępność:**
- Brak potwierdzonych znaczników `<title>` dla większości stron (tytuły odczytano z tekstu przeglądarki, nie z kodu).
- Zdjęcie hero w rejestracji bez alt.
- Etykiety "Przejdź do treści" i "Przewijanie do góry" widoczne w treści streszczeń (prawdopodobnie elementy interfejsu, nie treść).
- Pola formularza w rejestracji widoczne dla sklepu (NIP, adres) bez potwierdzonej walidacji - do sprawdzenia.

**Brakujące / niespójne:**
- /sklep/, /kontakt/, /polityka-cookies/ - 404.
- /doradca/ - niespójne wyniki (404 vs ekran logowania); trzeba potwierdzić, czy to przekierowanie.
- /redfield/ - nieznane przeznaczenie.
- Baner "Wersja PEŁNA – już wkrótce" na każdej stronie: produkt niewydany; plan na nową wersję.
- Tło graficzne wygenerowane AI - zaznaczone w stopce; decyzja brandingowa do potwierdzenia.

---

## Weryfikacja ręczna (22.11.2026)

- Strona główna produkcji ma **szybki dobór ryb dla gości**: „Dobierz ryby do swojego akwarium”, bez zakładania konta. Pola: wielkość zbiornika, wielkość ryby (domyślnie „Dopasuj do zbiornika”), temperatura, pH, przycisk „Pokaż dobór”. Liczba darmowych doborów jest zapisywana na urządzeniu jako ochrona przed nadużyciami.
- **Tej funkcji nie ma w repozytorium `AquaSmart-plugins`** (brak tekstów „Bez zakładania konta”, „Pokaż dobór”). Produkcja ma więc kod nowszy niż stan z 21.09.2026, na którym oparto analizę. Potrzebujemy aktualnych wtyczek z serwera.
- Wniosek dla v2: odpowiedź na pytanie o dostęp gościa do doradcy brzmi „tak, z limitem darmowych doborów”. Limit będzie ustawieniem w panelu admina.
