# Pytania do twórcy obecnej wersji (AquaSmart / Aikwarium)

**Data:** 2026-11-22. Analiza dotyczy kodu z produkcji z 21.09.2026: shop 1.6.13, auth 2.4.1, user 1.0.6, silnik 1.1.6.
Pytania finansowe celowo pominięto. W v2 cennik, progi, prowizje i kampanie będą parametrami ustawianymi w panelu admina.

Przy każdym pytaniu w nawiasie podano, dlaczego jest ważne.

---

## A. Źródła i stan produkcji

1. **Brakujące dokumenty.** Sesje powołują się na pliki, których nie ma w repozytoriach. Czy możesz je udostępnić?
   - `REJESTR_DOK.md`, `ARCHITEKTURA_Katalog_Sklep.md`, `ARCHITEKTURA_SystemB_LinkWewnetrzny.md`, `kontekst-biznesowy-aikwarium.md`
   - `specyfikacja_v3.0.md`, `bazy-danych/README_bazy.md` (opis kolumn baz TSV), `ZASADA_AI_FRONTEND_SesjaA.md`
   - karty sesji A15, A19 oraz A26 i nowszych

   *(Bez nich część reguł znamy tylko z kodu.)*
2. **Zmiany po 21.09.2026.** Czy po mapowaniu T0 coś zmieniało się na produkcji, w kodzie, bazie albo konfiguracji? *(Ustala, od którego stanu zaczynamy.)*
3. **Prawdziwi użytkownicy.** Czy na produkcji są prawdziwi użytkownicy i sklepy (czy pilot ruszył), czy tylko konta testowe? Ile mniej więcej jest kont, sklepów, zbiorników, pomiarów, ofert i kliknięć? *(To rozstrzyga, czy migrujemy dane, czy startujemy od zera.)*
4. **Zrzut bazy.** Czy możemy dostać zanonimizowany zrzut bazy produkcyjnej, a przynajmniej zrzut samego schematu? *(Przyda się do planu migracji i do wykrycia duplikatów, osieroconych rekordów i kolizji slugów.)*
5. **Treści do przeniesienia.** Które strony WordPressa mają przejść do v2: regulamin, polityka prywatności, strona główna, inne? Jakie 8 zewnętrznych wtyczek jest aktywnych i jaki motyw? *(Część zewnętrznych wtyczek, np. Wordfence czy UpdraftPlus, może nie mieć odpowiednika w v2, ale trzeba zachować to, co robią.)*

## B. Silnik rekomendacji i baza wiedzy

6. **Edycja baz wiedzy.** Kto i jak edytuje dziś bazy fauny, flory, biotopu i pielęgnacji (pliki TSV)? Czy istnieje źródło nadrzędne, np. Excel? *(W v2 planujemy edytor baz wiedzy w panelu admina z walidacją i importem/eksportem.)*
7. **Duplikaty w bazach.** Ten sam produkt występuje pod dwoma ID, np. Equilibrium `CH0041`/`CH0090`, Purigen `CH0066`/`CH0091`, LED 16W Plant `SP0089`/`SP0095`. To celowe warianty, ten sam produkt w różnych zastosowaniach, czy duplikaty do scalenia? Plik `baza_biotop_end2.txt` to w rzeczywistości baza sprzętu. Czy nazwa „biotop” coś oznacza?
8. **Ścieżka „Kompletny plan dla zbiornika”.** Jest w kodzie silnika, ale nie ma jej w menu. Porzucona czy do przywrócenia? *(Kandydat na funkcję v2.)*
9. **Poprawność rekomendacji.** Czy obecne rekomendacje uznajesz za poprawne i mogą służyć jako wzorzec? Czy znasz przypadki złych rekomendacji? Czy istnieją arkusze testowe wymieniane w dokumentacji („37/37”, „90/90”)? *(Na tej podstawie zbudujemy zestaw testów regresji silnika v2.)*
10. **Punktacja.** Każde kryterium daje dziś 0–3 punkty, bez wag. Czy to świadoma decyzja, czy chcesz mieć wagi, np. ustawiane w panelu?
11. **Stabilność ID rekordów.** Oferty sklepów wiążą się z rekordami bazy wiedzy przez ID. Czy ID rekordów są stabilne między wersjami baz (fauna_end1 → kolejna)?

11a. **Większy zbiornik.** Przy doborze ryba przeznaczona do mniejszego zbiornika przechodzi do większego. Przy weryfikacji konkretnego gatunku dostaje za to 1 lub 0 pkt. Która reguła jest właściwa?
11b. **Wysokie rośliny w nano.** Dla zbiornika nano doradca proponuje opcję „Wysoka (>30 cm)”, np. `FL0156`. To błąd czy celowe zachowanie?
11c. **Weryfikacja bez warunków.** Weryfikacja produktu, dla którego nie sprawdzono żadnego warunku (np. AquaSafe), pokazuje „100% Zgodny”. Czy tak ma zostać?
11d. **Produkt korygujący.** Doradca proponuje produkt poprawiający dopasowanie (grzałka, CO₂, regulacja pH) ze stałym wynikiem „90%”. Wybiera go po fragmencie tekstu, bez sprawdzenia, czy temperaturę trzeba podnieść, czy obniżyć. Czy masz reguły eksperckie, kiedy i co polecać?
11e. **Remisy.** Przy remisie doradca pokazuje całą grupę wyników ex aequo, czasem 5 i więcej kart. Czy tak ma zostać, czy ograniczyć liczbę kart?
11f. **Publiczna wyszukiwarka.** Wyszukiwarka nazw i ID produktów działa publicznie, bez logowania, a rekomendacje wymagają konta. Czy katalog ma być publiczny, co mogłoby też dawać ruch z wyszukiwarek?
11g. **Zakres doradcy v2.** Specyfikacja wymienia GH/KH, światło, stadność, zgodność obsady (czy gatunki pasują do siebie), poziom doświadczenia i kompletny plan zbiornika. Kod tego nie robi, a fragmenty dotyczące doświadczenia są martwe. Które z tych funkcji mają wejść do v2?
11h. **Słowniki doradcy.** Czy obecne kategorie odpowiedzi doradcy są ostateczne? Przykłady: „Grzałka często (22-25,5)”, „Małe (40L - 100L)”, „Ogólne / Towarzyskie”. Czy w v2 przechodzimy na wartości liczbowe (°C, litry), które da się porównać z pomiarami użytkownika?

## C. Konta i dostęp

12. **Sklep jako akwarysta.** Sklep przy rejestracji dostaje też rolę akwarysty, więc ma swoje zbiorniki i dziennik. Celowo?
13. **Weryfikacja sklepów.** Czy po pilocie wystarcza potwierdzenie e-maila, czy wraca akceptacja admina? Czy weryfikować NIP automatycznie (GUS/VIES)?
14. **Konto po wygaśnięciu okresu próbnego.** Akwarysta widzi wtedy swoje dane, ale nie może ich zmieniać. Sklep zachowuje pełny panel i publikację. Celowo?
15. **Logowanie.** Czy chcesz logowanie przez Google/Facebook? Czy wymagać dwuskładnikowego logowania (2FA) dla sklepów i administratorów?

## D. Moje akwarium

16. **Typy zbiorników.** Która lista obowiązuje: słodkowodny / morski / roślinny / biotopowy, czy warianty z testów („Ogólne”, „Towarzyskie”)?
17. **Parametry wody.** Dziś: pH, temperatura, KH, GH, NO₃, PO₄. Czy dodać NO₂, NH₃/NH₄, Fe, CO₂, przewodność? Czy na produkcji są stare pomiary NO₂ (kolumnę usunięto)?
18. **Progi ostrzeżeń.** Są bardzo szerokie, np. NO₃ 0–500. Czy mają zależeć od typu zbiornika i od obsady?
19. **Obsada zbiornika.** Czy użytkownik ma zapisywać, jakie ryby i rośliny ma w zbiorniku? *(To warunek funkcji „poleć do mojego zbiornika”, alertów z wersji 2.2 i asystenta AI.)*
20. **Kalkulator Redfielda.** Czy zatwierdzasz obecne strefy: zielona 10–20, pomarańczowa 5–10 i 20–30, czerwona poniżej 5 i powyżej 30? Czy kalkulator ma brać ostatni pomiar zbiornika automatycznie?
21. **Kanał przypomnień.** Przypomnienia są dziś tylko listą w panelu. Czy wysyłać je też e-mailem lub powiadomieniem push?

## E. Sklepy (bez finansów)

22. **Zakres oferty sklepu.** Czy oferta może dotyczyć wyłącznie rekordu z bazy wiedzy, czy także produktów spoza bazy? Czy wraca import CSV z EAN?
23. **Oferta wewnętrzna.** Jaki scenariusz biznesowy stoi za „ofertą wewnętrzną” (System B)? Czy chodzi o sklep stacjonarny bez sklepu internetowego?
24. **Ministrona sklepu.** Co ma zawierać: logo, opis, adres, mapę, godziny otwarcia, zdjęcia produktów?
25. **Istniejące adresy.** Czy adresy `/sklep/{slug}/` i `/go/shop/{id}/` są gdzieś opublikowane (Google, materiały drukowane, sklepy partnerów)? *(Od tego zależy, czy potrzebne są przekierowania 301.)*
26. **Ile sklepów jest opublikowanych.** Ile sklepów jest dziś publicznie opublikowanych (próg 50 ofert)? Czy sklepy partnerskie mają być opublikowane od startu („zimny start” z A25)?

## F. Kierunek produktu

27. **Nazwa.** AquaSmart, Akwa Filtr czy Aikwarium? Która nazwa i domena obowiązują w v2?
28. **Języki.** Tylko polski, czy planujesz inne języki? *(Wpływa na projekt bazy wiedzy.)*
29. **Aplikacja mobilna.** Planujesz natywną aplikację, czy wystarczy strona działająca dobrze na telefonie (PWA)? *(Wpływa na to, czy od razu budujemy publiczne API.)*
30. **Kolejne wersje.** Czy mapa serwisów (2.1) i alerty z historii zbiornika (2.2) mają wejść do v2 od razu, czy później?
31. **Dotacja na AI.** Czy dotacja NCBR na asystenta AI (v3) narzuca wymogi technologiczne, np. prace badawczo-rozwojowe albo własny model?
