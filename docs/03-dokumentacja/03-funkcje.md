# Funkcje

Drop to dostęp gościa. Tank to podstawowy dostęp konta, a Fishroom rozszerza Tank. Poniżej podano dostęp użytkownika danej funkcji. Operacje na prywatnych zasobach sprawdzają też właściciela, a operacje sklepowe uprawnienia w konkretnym sklepie. Klucze ustawień definiuje [SettingKey](../../app/Domain/Settings/SettingKey.php).

Pasma i opisy katalogu są podstawą porównań. Brak danych albo rozbieżność źródeł ogranicza wniosek. Serwis pomija porady, dla których brakuje podstawy w bazie wiedzy.

## Serwis publiczny i doradca

| Funkcja i trasa | Dostęp i działanie | Ustawienia i granice |
|---|---|---|
| Strona główna i SEO — `/`, `/robots.txt`, `/sitemap.xml` | Publiczne. Strona główna przedstawia funkcje i ścieżki wejścia. Publiczne widoki mają metadane SEO, a mapa obejmuje m.in. rekordy aktywnej partii katalogu. Wybrane stare adresy WordPressa mają przekierowania. | `APP_INDEXABLE` steruje sygnałami indeksowania. Parametry prezentowanego planu Fishroom pochodzą z ustawień ceny, waluty i okresu. Panele i strefy prywatne nie są w mapie. |
| Strony informacyjne — `/regulamin`, `/prywatnosc`, `/kontakt`, `/dla-sklepow` | Publiczne. Widoki oznaczone jako w przygotowaniu. Prywatność dodatkowo pokazuje informacje o ciasteczkach, mieście i obserwowaniu. | Czas ciasteczek wynika z konfiguracji sesji i `drop.cookie_days`. Pełne treści stron są w przygotowaniu. |
| Wejście do doradcy — `/doradca` | Publiczne. Pokazuje ścieżki doboru wraz z informacją o dostępie. | Dostęp do poszczególnych ścieżek wynika z `ResolveAccess`. Ranking powstaje w wybranym trybie. |
| Szybki dobór — GET/POST `/dobor` | Gość. Ranking ryb według pasma zbiornika, wielkości ryb, temperatury i pH w typie „Ogólne / Towarzyskie”. | `drop.*` steruje doborami, ciasteczkiem i limitami IP. Domyślnie jeden bezpłatny dobór. Ranking uwzględnia wyłącznie profil z formularza. |
| Kreator — GET/POST `/doradca/kreator` | Konto z aktualnymi zgodami. Fishroom oraz Tank przy włączonym `advisor.guided.tank_access` (domyślnie). Prowadzi przez pytania, pokazuje ranking albo weryfikuje wybrany rekord z katalogu zależnie od ścieżki. | `advisor.ranking.*` i `advisor.verification.*` ustawiają progi, format wyniku i reguły weryfikacji. Wyszukiwanie stosuje limity liczby znaków i wyników. Weryfikator nie mapuje pH na Equilibrium, EasyBalance ani pH Control. |
| Kreator nowego akwarium — `/kreator-akwarium` | Publiczny. Sześciokrokowy kreator zbiornika w przeglądarce (litraż, fauna, flora, sprzęt, pielęgnacja, sklepy) oparty na pakiecie katalogu. Zapis szkicu na koncie (`Tank` i `Fishroom` ze zgodami) trafia do `/moje-akwarium/plany` (z możliwością zastąpienia istniejącego szkicu lub konwersji w zbiornik). | `advisor.creator.guest_offers` włącza oferty sklepów dla gościa po wybraniu miasta. Zapis planu wymaga konta i podlega `aquarium.plan.limit`. Przy konwersji planu w zbiornik pozycje pielęgnacji (`Care`) są pomijane — zbiornik przejmuje faunę, florę i wyposażenie. |
| Mój zbiornik — `/doradca/moj-zbiornik`, POST `/doradca/obsada` | Tank i Fishroom z aktualnymi zgodami. Dobór z formularza lub profilu własnego zbiornika. Karty pokazują dopasowanie, uwagi do obsady zapisanego zbiornika i przejście do rekordu lub dodania do obsady. | `aquarium.compat.severity` wpływa na komunikaty zgodności przy obsadzie. Wynik rankingu nie gwarantuje zgodności z pozostałą obsadą. |
| Strona produktu — `/katalog/{area}/{id}`, POST `/doradca/dodaj-do-akwarium` | Publiczny odczyt rekordu aktywnej partii, parametrów, zdjęcia i ofert. Tank i Fishroom mają szerszy widok danych oraz akcje zależne od rodzaju rekordu, np. dodanie do własnego akwarium. | Szczegóły i akcje wynikają z dostępu, a oferty z reguł publikacji i ceny efektywnej. Strona nie składa zamówienia samym otwarciem oferty. |

## Konto i zgody

| Funkcja i trasa | Dostęp i działanie | Ustawienia i granice |
|---|---|---|
| Rejestracja, logowanie i hasło — `/rejestracja`, `/rejestracja/akwarysta`, `/rejestracja/sklep`, `/logowanie`, `/haslo/reset`, `/haslo/email`, `/weryfikacja` i `/weryfikacja/ponow` | Gość zakłada konto lub zgłasza sklep. Weryfikacja używa podpisanego linku `/weryfikacja/{id}/{hash}`. Wylogowanie przez POST `/wyloguj`. Zgłoszenie sklepu wymaga decyzji operatora. | `accounts.email_verification_required` oraz ustawienia `identity.registration.*`, `identity.activation.lifetime_seconds`, `identity.password.min_length`. Sam formularz zgłoszenia nie otwiera panelu sklepu. |
| Konto i zgody — `/moje-konto`, POST `/moje-konto/miasto`, `/moje-konto/nazwa` i `/moje-konto/haslo`, GET/POST `/zgody` | Zalogowany użytkownik zmienia dane i akceptuje aktualny regulamin i politykę prywatności oraz składa oświadczenie o pełnoletności. Akceptacja jest sprawdzana na trasach z `consents`. Newsletter jest opcjonalny. | `identity.consents.version` wymusza ponowną akceptację po zmianie wersji. Samodzielny zakup Fishroom jest odłożony. Dostęp nadaje administrator. |
| Prywatność — `/moje-konto/prywatnosc`, POST `/newsletter` i `/eksport` oraz DELETE `/konto` pod tym prefiksem | Konto z dostępem do własnych danych. Wycofanie newslettera, eksport JSON i usunięcie po potwierdzeniu hasłem. | `identity.export.interval_minutes` ogranicza częstotliwość eksportu. Samodzielne usunięcie konta administratora jest zablokowane. |

## Strefa akwarysty

Dostęp wymaga zalogowania i aktualnych zgód. Funkcje zbiornika są w zakładkach `/moje-akwarium/zbiorniki/{tank}` wybieranych przez `?karta=...`.

| Funkcja | Dostęp i działanie | Ustawienia i granice |
|---|---|---|
| Zbiorniki — `/moje-akwarium` i zakładka `zbiornik` | Tank i Fishroom. Lista, tworzenie, edycja i usuwanie własnych zbiorników. | Limity liczby i litrażu: `aquarium.tank_limit` i `aquarium.tanks.volume_l`. Domyślnie Tank ma jeden zbiornik, Fishroom bez limitu. Zbiorniki są prywatne. |
| Obsada i zgodność — `obsada` | Tank i Fishroom. Zapis gatunku i ilości, porównania pasm zbiornika i obsady z katalogiem. Przy ostrzeżeniu użytkownik potwierdza zapis. | `aquarium.compat.severity` steruje wagą komunikatów. Brak werdyktu biologicznego, ostrzeżenia o parze na podstawie temperamentu ani konfliktu z samej różnicy indeksów wielkości. Brakujące dane są oznaczane. Porównanie obsady korzysta z pól katalogowych. |
| Pomiary i podpowiedź — `pomiary`, `historia`, `dziennik` | Tank i Fishroom zapisują pomiary. Fishroom widzi historię i dziennik. Podpowiedź porównuje pH i temperaturę ostatniego odczytu z pasmami katalogowymi fauny, pokazuje podstawę, braki danych oraz wiek odczytu. Uwzględnia odczyty pomiędzy pasmami oraz wykryte rozbieżności między pasmem i jednoznacznym zakresem z opisu. | `aquarium.measurements.warning_ranges`, `advisor.water_hints.checks` i `advisor.water_hints.stale_days`. Mapa produktów jest domyślnie pusta. Podpowiedź nie zaleca korekt, grzałek ani testu dla starego odczytu. |
| Wyposażenie — `wyposazenie`, POST `/moje-akwarium/wyposazenie`, PATCH/DELETE `/moje-akwarium/zbiorniki/{tank}/wyposazenie/{equipment}` | Tank i Fishroom. Dodawanie pozycji z katalogu sprzętu, zapis ilości i edycja listy. | `aquarium.equipment.limit` ogranicza liczbę pozycji. Lista nie dobiera sprzętu ani jego mocy. |
| Przypomnienia — `przypomnienia`, `podpowiedzi` | Fishroom. Ręczne przypomnienia oraz podpowiedź typu z cytatem dotyczącym czynności dla zapisanej obsady lub sprzętu. Podpowiedź otwiera formularz, a użytkownik podaje termin i interwał. | `aquarium.reminders.horizon_days` i `aquarium.reminders.interval_days`. Brak prognoz zużycia preparatów i automatycznego planowania terminów. |
| Plan akwarium — `/moje-akwarium/plany`, `/moje-akwarium/plany/{plan}` | Tank i Fishroom. Szkic utworzony w formularzu prostego planu lub zapisany z `/kreator-akwarium` z litrażem lub pasmem i pozycjami obsady. Są porównanie katalogowe, przegląd kategorii sprzętu oraz lista dostępnych ofert po placówkach z wariantem, ilością i ceną. Można dodać pojedynczą pozycję do koszyka i zamienić plan w zbiornik (z pominięciem pozycji pielęgnacji `Care`). | `aquarium.plan.limit` ogranicza szkice. Oferty są filtrowane wg miasta konta, a przy pustym mieście pochodzą z całego kraju. Brak obowiązkowej listy sprzętu, optymalizatora i masowego dodawania do koszyka. |

## Obserwowanie i kalkulatory

| Funkcja i trasa | Dostęp i działanie | Ustawienia i granice |
|---|---|---|
| Obserwowanie — `/moje-konto/obserwowane`, POST `/{area}/{id}` i `/powiadomienia` oraz DELETE `/{watchId}` pod tym prefiksem | Tank i Fishroom ze zgodami. Obserwację można rozpocząć na stronie produktu lub karcie doradcy. E-mail o dostępności używa miasta z konta w chwili sprawdzania, a przy pustym mieście uwzględnia cały kraj. Podpisany link `/obserwowane/wypisz/{watchId}/{activation}` pozwala wypisać się bez logowania. | `shops.watch` określa limity i retencję. Jedno powiadomienie na aktywację w normalnym przebiegu. Ponowna aktywacja unieważnia poprzedni wypis. E-mail podaje dane oferty z chwili wykrycia i nie rezerwuje towaru. |
| Kalkulatory — `/kalkulatory` oraz `/litraz`, `/dawkowanie`, `/co2`, `/obsada` pod tym prefiksem | Publiczne. Obliczają litraż z jawnym zaokrągleniem i pasmem, przeliczają dawkę z danych użytkownika, szacują CO₂ z KH i pH dla słodkiej wody oraz liczą wskaźnik z wpisanych długości ryb i litrażu. Konto ze zgodami może zapisać litraż we własnym zbiorniku przez POST `/kalkulatory/litraz/zapisz`. W zbiorniku jest też zakładka `kalkulatory`. | `aquarium.calculators.limits` i `aquarium.calculators.co2`. Brak doboru preparatu, progów CO₂ i werdyktu o obsadzie. Niepełne dane obsady dają komunikat „Brak danych” z listą wymaganych wartości. |
| Redfield — `/moje-akwarium/redfield` | Tank i Fishroom ze zgodami. Przelicza wpisane NO₃ i PO₄ i pokazuje strefę wyniku. | `aquarium.redfield.max_input_mg_l` i `aquarium.redfield.zone_edges`. Kalkulator nie diagnozuje zbiornika. |

## Sklepy

Panel pod `/panel-sklepu/{shop}` wymaga uprawnień do sklepu. Poniższe ścieżki panelowe są względem tego adresu.

| Funkcja i trasa | Dostęp i działanie | Ustawienia i granice |
|---|---|---|
| Placówki i oferty — `/placowki`, `/zespol`, `/oferty` w panelu | Zespół według uprawnień. Zarządza placówkami, zespołem, wariantami oferty, ceną, ilością i terminem. Oferty widoczne publicznie przy rekordach katalogu zależą od stanu sklepu, placówki i oferty. | `shops.activation`, `shops.offer.min_price_grosze`, `shops.offer.bounds`. Brak ręcznej moderacji każdej publikacji. |
| Import ofert — `/import-ofert` w panelu oraz `/wzor`, `/sprawdz`, `/{batch}/zapisz`, `/{batch}/bledy` pod adresem importu | Zespół sklepu. Wzór CSV, podgląd i błędy, potwierdzenie fizycznych ilości, zapis kolejką, raport wyniku. Każdy wiersz zapisuje się atomowo. Zmienione po podglądzie oferty są pomijane z powodem. | `shops.import.csv` ogranicza rozmiar, liczbę wierszy i przechowywanie oraz podaje domyślną ważność. Domyślny limit 10 MiB i 10 tys. wierszy. Bez importu promocji. |
| Koszyk i zamówienia — `/koszyk`, `/zamowienia`, `/zamowienia/{order}`, `/zamowienia` w panelu | Tank i Fishroom ze zgodami składają i przeglądają własne zamówienia. Zmiana ceny wymaga ponownej akceptacji. Sklep podejmuje decyzję. Zatwierdzenie rezerwuje ilość i nadaje EAN-13. Sklep ręcznie potwierdza wpływ, przygotowanie i odbiór. | `shops.orders` określa dzienny limit, zakres ilości i retencję kontaktu. Szczegółowe akcje koszyka to POST ceny i wysłanie, PATCH ilości oraz DELETE pozycji. Koszyk nie rezerwuje towaru. Brak płatności online i wysyłki towaru. |
| Promocje — akcje w `/oferty` w panelu, odczyt przy ofercie | Zespół ustawia niższą cenę i okres, a trwającą promocję może zakończyć. Jedna cena efektywna jest używana w ofertach, doradcy, planie, koszyku i przy złożeniu zamówienia. Oznaczenie pokazuje koniec promocji i najniższą cenę przed obniżką, domyślnie z 30 dni, z uwzględnieniem wcześniejszych promocji. Przy krótszej historii używa okresu od rozpoczęcia sprzedaży. | `shops.promo` określa maksymalny okres i okno odniesienia. Bez historii sprzed obniżki brak oznaczenia promocji. Brak kampanii, osobnej strony promocji i procentowej etykiety rabatu. |
| Opinie — `/zamowienia/{order}/opinia`, `/sklepy/{shop}/opinie`, `/opinie` w panelu | Klient wystawia jedną opinię po odbiorze własnego zamówienia. Widoczne opinie są publiczne z informacją o weryfikacji. Sklep czyta swoje opinie. | `shops.reviews` ustala termin (domyślnie 30 dni), próg średniej (domyślnie 3 widoczne opinie) i długość komentarza. Brak odpowiedzi sklepu i e-maili o opiniach. |
| Popyt w okolicy — `/popyt-w-okolicy` w panelu | Zespół widzi liczbę aktywnych obserwacji na rekord w miastach swoich placówek. | `metrics.demand.k` określa próg pokazywania wyniku dla miasta i rekordu (domyślnie 5). Bez danych osób, odsłon i widoku ogólnokrajowego. |
| Raport dobowy — e-mail zespołu, polecenie `shops:daily-reports` | Raport dla sklepu aktywnego lub zawieszonego zawiera przejścia stanów, powody odrzuceń, ruch ilości i sumę potwierdzonych płatności. | Godzina z `shops.orders.report_hour` oraz strefa `APP_DISPLAY_TIMEZONE`. Raport obejmuje jedną dobę i nie uruchamia płatności. |

## Panel administratora

Dostęp do `/admin` mają administratorzy. Wymagania logowania opisuje [instrukcja utrzymania](04-utrzymanie.md#wdrożenie).

| Trasa | Działanie | Ustawienia i granice |
|---|---|---|
| `/admin/ustawienia` | Zmiany z datą obowiązywania, powodem i historią. | Zakres i walidacja z `SettingKey`. Zmiana dopisuje wersję. |
| `/admin/zdjecia`, `/admin/baza-wiedzy` | Przegląd zdjęć i rekordów, zarządzanie metadanymi zdjęć przy rekordzie i zatwierdzanie ich do publikacji. | Publiczny katalog pokazuje zweryfikowane zdjęcia z adresem URL. Formularz zapisuje metadane bez przesyłania pliku. |
| `/admin/opinie` | Ukrywanie opinii z powodem i przywracanie widoczności, z audytem. | Próg średniej ustala `shops.reviews.min_to_show`. Moderacja zmienia widoczność bez zmiany treści opinii. |
| `/admin/importy` | Lista partii i aktywacja partii katalogu. Jedna aktywna partia, poprzednia zachowana. | Nieudanej partii nie można aktywować. Walidacja i import paczki mają osobne polecenie `kb:import {path}`. |
