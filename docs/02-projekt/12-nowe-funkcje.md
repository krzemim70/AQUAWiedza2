# Aikwarium v2 — nowe funkcje (projekt)

Dokument opisuje dziesięć zatwierdzonych funkcji: zgodność obsady, podpowiedź z pomiaru wody, „daj znać, gdy będzie dostępne”, plan nowego akwarium, przypomnienia z obsady i sprzętu, kalkulatory, statystyki popytu dla sklepów, import ofert z CSV, opinie o sklepie i promocje. Na końcu są pakiety robocze, fale realizacji i pytania do właściciela. Dokument jest rozszerzeniem `00-decyzje.md`, `10-domena-sklepu.md` i `11-model-domenowy.md`; nie zmienia ich ustaleń. Po zatwierdzeniu właściciel dopisuje decyzje do `00-decyzje.md` (opisowo, bez numerów).

## 1. Zasady wspólne

### 1.1. Twarda zasada: żadnych porad bez podstawy

Każde ostrzeżenie, ocena i rada ma jedno z dwóch źródeł:

1. **Dane bazy wiedzy.** W treści pokazujemy kolumnę i wartość (np. „baza: Temperatura = Grzałka często (22-25,5)”).
2. **Ugruntowany wzór** z podanym źródłem (np. zależność KH, pH i CO₂). Źródło jest w tym dokumencie i w opisie kalkulatora.

Brak danych albo dane niejednoznaczne dają komunikat „brak danych w bazie / sprawdź u sprzedawcy”, nigdy domysł. Dodatkowo:

- Nie podajemy dawek leków ani preparatów. Kalkulator dawkowania skaluje tylko dawkę producenta wpisaną przez użytkownika.
- Nie stawiamy diagnoz (ani weterynaryjnych, ani „choroby”). Używamy słów „zgodność z danymi bazy wiedzy”, „uwaga”, „sprawdź”.
- Reguły, które nie wynikają jednoznacznie z danych, są w liście pytań (§14) i do czasu decyzji pozostają wyłączone (ustawienie `null` albo wyłączony przełącznik).
- Każda reguła zostawia w wyniku **dowód**: kolumna, wartość, identyfikator rekordu. Ekran pokazuje go pod hasłem „Dlaczego?”, a testy go sprawdzają.
- Mapa „problem → produkt” jest wersjonowanym ustawieniem. Każdy wpis ma identyfikator produktu i fragment opisu z bazy, który go uzasadnia. Test blokuje publikację bazy, w której opis przestał zawierać ten fragment.

### 1.2. Co faktycznie jest w bazie wiedzy

Stan sprawdzony na plikach `tests/Fixtures/KnowledgeBase` (kopia referencyjna). Puste pola: **0** we wszystkich kolumnach wszystkich czterech obszarów.

**Fauna, 200 rekordów** (189 ryb, 6 skorupiaków, 5 ślimaków):

| Kolumna | Wartości (liczba rekordów) | Uwagi |
|---|---|---|
| Wielkość | Nano do 3 cm (25), Mała 3-5 (41), Średnia 5-10 (69), Duża 10-15 (33), B. duża >15 (32) | Tylko pasmo. Dla „>15 cm” brak górnej granicy. Brak długości w cm. |
| Temperatura | Grzałka często 22-25,5 (156), Grzałka sporadycznie 18-21,5 (25), Grzałka b. często 26-30 (18), Zimnolubne poniżej 18 (1: `FN0165`) | Jedna wartość na rekord. Pasma nie pokrywają 21,5-22 ani 25,5-26. |
| pH | Obojętna 6,8-7,5 (90), Kwaśna poniżej 6,8 (76), Zasadowa powyżej 7,5 (34) | Jedna wartość na rekord. |
| Temperament | Spokojny (133), Umiarkowany (38), Agresywny/Terytorialny (29) | Tylko etykieta, bez informacji, kogo dotyczy. U ślimaka `FN0153` (Anentome helena) wartość „Agresywny” oznacza zapewne drapieżnictwo wobec ślimaków. |
| Strefa | Dolna (83), Środkowa (66), Górna (51) | Informacja pomocnicza. |
| Typ | Ryba (189), Skorupiak (6), Ślimak (5) | |
| WielkośćAkwarium | 5 pasm i „Wszystkie” (9, same skorupiaki i ślimaki); **90 rekordów ma kilka pasm** po `;` | To jest rekomendowane pasmo zbiornika gatunku. |
| TypAkwarium | Ogólne/Towarzyskie (168), Nano/Krewetkarium (18), Roślinne (16), Specjalistyczne (12), Biotop Twardowodny (7); 21 rekordów ma dwa typy | |
| Region | 5 wartości | Nie wpływa na zgodność. |

**Flora, 194 rekordy:** Wielkość (4 pasma), Trudność, Strefa (5), **CO2: Zbędny 87, Wskazany 82, Niezbędny 25**, Kolor, WielkośćAkwarium (63 × „Wszystkie”), TypAkwarium (4). Brak jakichkolwiek wymagań dla pH, temperatury, KH, GH.

**Sprzęt, 108 rekordów:** kategoria_glowna: Filtracja 45, Oświetlenie LED 29, Ogrzewanie 28, Dozowanie CO2 6. Pola rodzaj, producent, Marka, produkt, Poziom_doświadczenia, Zdalne_sterowanie (wartości „nie”, „tak” i jedno „Nie” z wielką literą), WielkośćAkwarium (5 pasm, wielowartościowe), TypAkwarium, opis_krotki. Brak mocy w watach, wydajności, interwałów serwisu (moc bywa tylko w nazwie produktu).

**Pielęgnacja, 110 rekordów:** kategoria_zadania (Pielęgnacja na co dzień 48, Zapobieganie i kontrola 34, Start / przygotowanie 28), rodzaj_zadania (8), obszar (19), producent, Marka, produkt, opis. Brak pól akwarium (produkty nie są dopasowane do wielkości zbiornika). 4 nazwy produktów powtarzają się w różnych rekordach (np. „Equilibrium” `CH0041` i `CH0090`, „Fishtamin” `CH0081` i `CH0093`), więc kluczem jest zawsze identyfikator.

**Czego baza NIE zawiera** (stąd „brak danych” w funkcjach): długość dorosłej ryby w cm, minimalna liczebność stada, wymagania KH/GH/NO₃/PO₄ gatunków, wymagania wody roślin (poza CO₂), relacje drapieżnik-ofiara, moc grzałek w W, interwały serwisu sprzętu, dawkowanie preparatów, wielkości opakowań.

Liczby pomocnicze: z 19 900 par rekordów fauny **624 pary** różnią się o co najmniej dwa pasma temperatury, **2 584 pary** o dwa pasma pH (kwaśna i zasadowa), razem **3 146 par (15,8%)** ma rozłączne wymagania. Reguła niezgodności jest więc częsta i opłaca się ją sprawdzać automatycznie.

### 1.3. Wartości liczbowe pasm (z etykiet w danych)

| Pasmo | Zakres według etykiety | Zakres używany w regułach |
|---|---|---|
| Temperatura: Zimnolubne | poniżej 18 °C | od −∞ do 18 (bez 18) |
| Temperatura: Grzałka sporadycznie | 18-21,5 | od 18 do 22 (bez 22) |
| Temperatura: Grzałka często | 22-25,5 | od 22 do 26 (bez 26) |
| Temperatura: Grzałka b. często | 26-30 | od 26 do 30 włącznie |
| pH: Kwaśna | poniżej 6,8 | do 6,8 (bez 6,8) |
| pH: Obojętna | 6,8-7,5 | od 6,8 do 7,5 włącznie |
| pH: Zasadowa | powyżej 7,5 | powyżej 7,5 |

Odczyty 21,5-22 i 25,5-26 °C leżą między pasmami; traktujemy je jako granicę sąsiednich pasm (nie dają ostrzeżenia). Rozszerzenie dolnego końca pasma „Zimnolubne” i górnego „Zasadowa” w nieskończoność wynika z etykiet („poniżej”, „powyżej”). Potwierdzenie: pytanie 4 w §14.

### 1.4. Podział na moduły i kontrakty (nowe elementy)

| Moduł | Dostaje |
|---|---|
| Advisor (silnik, czysty PHP) | Reguły wynikające z danych: zgodność obsady, wymagania obsady, reguły podpowiedzi z pomiaru, rekomendacje kategorii w planie. Kontrakt `Advisor\Contracts\Compatibility`. |
| Aquarium | Akcje na zbiorniku i obsadzie, plany, wyposażenie, preparaty, przypomnienia, kalkulatory (czyste klasy), lista zakupów. |
| Shops | Obserwowanie rekordów, import CSV, opinie, promocje (kolumny ofert, historia cen), kontrakt `ShopRatings`. |
| Campaigns (nowy, wg `app/Domain/README.md`) | Kampanie promocyjne z panelu admina. |
| Metrics (nowy, wg README) | Zanonimizowane liczniki popytu. |
| Notifications (nowy, minimalny) | Preferencje tematów i jeden wspólny sposób wysyłki powiadomień. |
| IdentityAccess | Nowe wiersze macierzy dostępu (`Feature`), miasto użytkownika. |
| Settings | Nowe `SettingKey` (lista w §13). |

Granice zachowane: Aquarium nie czyta tabel Shops (tylko `PublicOffers`), Shops nie czyta tabel Aquarium, Metrics dostaje zdarzenia przez wywołanie akcji, a nie przez zapytania do cudzych tabel. Wszystkie akcje mają postać `VerbNoun::handle(DTO)`, nie zależą od HTTP i są gotowe pod przyszłe API aplikacji mobilnej (Sanctum).

### 1.5. Wspólne zasady dostępu

`ResolveAccess` rozstrzyga każdą funkcję. Proponowany podział (szczegóły przy funkcjach i w §13):

- **Gość (Drop):** kalkulatory (bez zapisu), podgląd ofert, wizytówka.
- **Tank (bezpłatny):** wszystko, co chroni użytkownika przed błędem, oraz jeden zbiornik i jeden plan: ostrzeżenia przy dodawaniu gatunku, podsumowanie zgodności obsady, podpowiedź z ostatniego pomiaru, obserwowanie (limit), plan nowego akwarium, zapis wyników kalkulatorów.
- **Fishroom (płatny):** to, co już dziś jest w Fishroom (historia, dziennik, przypomnienia) oraz wiele zbiorników i planów, wyższy limit obserwowanych rekordów, przypomnienia automatyczne i śledzenie preparatów.
- **Sklep (członkostwo):** import ofert, promocje, statystyki popytu, odpowiedź na opinię; sprawdza `Shops\Access`.
- **Administrator:** kampanie, moderacja opinii, ustawienia, podgląd wszystkiego.

---

## 2. Zgodność obsady w zbiorniku

### Wartość dla użytkownika

Przy dodawaniu gatunku do zbiornika użytkownik widzi, czy baza wiedzy wskazuje konflikt z obecną obsadą lub zbiornikiem. W panelu zbiornika widzi przejrzystą ocenę „Zgodność obsady” (nie „zdrowie”): ile kontroli przeszło bez zastrzeżeń, ile ma uwagi, ile nie ma danych. Nazwa „zdrowie obsady” sugerowałaby diagnozę, dlatego proponujemy „Zgodność obsady według bazy wiedzy” (przyjęte jako domyślne, §14.2).

### Źródła danych

Fauna: Wielkość, Temperatura, pH, Temperament, Typ, WielkośćAkwarium, TypAkwarium. Flora: WielkośćAkwarium, TypAkwarium, CO2. Zbiornik: `size_band`, `type`, `temp_band`, `ph_band`. Obsada: `tank_livestock` (area, catalog_id, quantity). Sprzęt zbiornika (Dozowanie CO2): tabela `tank_equipment` z §6.

### Reguły

Wejście: `TankContext` (pasmo, typ, opcjonalnie preferencje temperatury i pH) i lista pozycji obsady wraz z kandydatem. Wyjście: lista `Finding` (kod kontroli, ważność, gatunki, dowód, tekst) i podsumowanie.

| Kontrola | Warunek (pseudokod) | Ważność | Dowód |
|---|---|---|---|
| Zbiornik za mały według bazy | `rekord.pasmaAkwarium` niepuste, rekord bez „Wszystkie”, `zbiornik.band.index < min(rekord.pasmaAkwarium.index)` | uwaga | WielkośćAkwarium rekordu |
| Dorosła ryba za duża na zbiornik | rekord Typ=Ryba, `rekord.wielkość.index − zbiornik.band.index ≥ 3` (ta sama reguła wykluczenia, co w rankingu Tank, `11-model-domenowy.md` §4) | konflikt | Wielkość rekordu i pasmo zbiornika |
| Typ zbiornika | `zbiornik.type ∉ rekord.typyAkwarium` i brak „Wszystkie” | uwaga | TypAkwarium rekordu |
| Temperatura (para) | oba rekordy mają Temperaturę, `|idx A − idx B| ≥ 2` (zakresy nie mają części wspólnej, odstęp co najmniej 4 °C) | konflikt | Temperatura obu rekordów |
| Temperatura wobec preferencji zbiornika | `zbiornik.temp_band` ustawione, `|idx − idx| ≥ 2` | uwaga | Temperatura rekordu, pasmo z formularza |
| pH (para) | żadna para pasm z list pH obu rekordów nie jest bliższa niż 2 pasma (praktycznie: Kwaśna i Zasadowa) | konflikt | pH obu rekordów |
| pH wobec preferencji zbiornika | j.w. dla `zbiornik.ph_band` | uwaga | pH rekordu |
| Temperament (ryby) | rekord A: Typ=Ryba i Temperament=Agresywny/Terytorialny; w zbiorniku jest rekord B: Typ=Ryba i Temperament=Spokojny | uwaga | Temperament obu rekordów |
| CO₂ roślin bez dozowania | flora z CO2=Niezbędny, a w sprzęcie zbiornika brak rekordu kategorii Dozowanie CO2 | informacja | CO2 rekordu, brak pozycji w `tank_equipment` |
| Obciążenie cm ryby na litr | patrz §7 (kalkulator); ocena tylko, gdy właściciel ustawi próg | informacja | wzór i próg z ustawień |

Pary liczymy tylko dla fauny (flora nie ma wymagań wody). Kontrola bez danych (brak wartości w polu albo brak pasma zbiornika) jest liczona jako „brak danych”, nie jako zgodna.

**Ocena (podsumowanie):** trzy stany i licznik, bez procentów i bez „punktów zdrowia”:

- „Brak zastrzeżeń według bazy” (0 konfliktów, 0 uwag),
- „Są uwagi” (≥1 uwaga, 0 konfliktów),
- „Są konflikty” (≥1 konflikt),

oraz tabela: kategoria kontroli, sprawdzone, zastrzeżenia, brak danych (np. „Temperatura: 45 par sprawdzonych, 2 konflikty”). Pod spodem stała adnotacja: „Ocena opiera się wyłącznie na danych bazy wiedzy o gatunkach. Nie jest diagnozą i nie zastępuje porady specjalisty.”

### Czego NIE mówimy

- Nie mówimy, że ryby „się zjedzą”, „będą chore” ani „pasują” (tylko „baza wskazuje”).
- Nie oceniamy liczebności stada, wzajemnych relacji innych niż etykiety temperamentu ryb, stref pływania, regionu pochodzenia (brak danych albo brak jednoznacznej reguły).
- Temperament u skorupiaków i ślimaków nie wchodzi do reguł (pytanie 1).
- Przy zbiorniku większym niż rekomendowany nie ostrzegamy.
- Przy braku pasma zbiornika (np. po imporcie) pokazujemy prośbę o uzupełnienie wielkości zamiast oceny.

### Przepływ UI

- **Dodawanie gatunku:** ten sam kontrakt przed zapisem w trzech miejscach, które dziś wywołują `AddToLivestock`: panel akwarium `/moje-akwarium/zbiorniki/{id}` (komponent `TankLivestock`), strona produktu `/katalog/{obszar}/{id}` (`AddToTankController`) i wynik doradcy (`TankAdvisorController`). Jeśli są ustalenia (`Finding`), użytkownik widzi okno: lista ustaleń z dowodem i przyciski „Dodaj mimo to” i „Anuluj”. Brak ustaleń: dodanie jak dziś. Konflikt nie blokuje dodania (decyzja należy do użytkownika), ale jest zapisywany w obsadzie jako przyjęty (`acknowledged_at`), żeby nie pytać drugi raz o to samo.
- **Panel zbiornika:** nad listą obsady karta „Zgodność obsady” z trzema stanami, licznikami i rozwijaną listą ustaleń.
- **Doradca (ranking Tank):** karta kandydata dostaje plakietkę „Uwagi dla Twojej obsady: N” (to samo wywołanie na obsadzie zbiornika). Kolejność rankingu się nie zmienia (ranking to osobna, zatwierdzona logika).

### Model danych i właściciel

Moduł właściciela: **Advisor** (reguły, DTO `TankContext`, `StockLine`, `Finding`, `CompatibilityReport`; kontrakt `Compatibility::check(TankContext, list<StockLine>, ?StockLine $candidate)`). **Aquarium** woła kontrakt w akcji `CheckLivestockFit` (czyta własną obsadę i zbiornik) i zapisuje przyjęcie ostrzeżeń.

| Zmiana | Opis |
|---|---|
| `tank_livestock.acknowledged_findings` (JSON, null) | Kody ustaleń przyjętych świadomie przez użytkownika. |
| `FaunaRecord` | Dochodzi `temperament` (enum `Temperament`) i `zone`. Dochodzi `FloraRecord` (CO2, pasma i typy akwarium). Zmiana w `FaunaPool`. |

Ustawienia (wersjonowane, panel admina): `aquarium.compat.severity` (ważność każdej kontroli: konflikt/uwaga/informacja/wyłączona), `aquarium.compat.temperament` (które kombinacje temperamentu uruchamiają uwagę; start: tylko „Agresywny/Terytorialny” z „Spokojny”, ryba-ryba).

### Powiadomienia i joby

Brak. Ocena liczona na żądanie (obsada ma kilkadziesiąt pozycji, par jest co najwyżej kilkaset).

### Dostęp

Tank: ostrzeżenia przy dodawaniu i karta zgodności (jeden zbiornik). Fishroom: to samo dla wielu zbiorników. Gość: brak (nie ma obsady). Nowy wiersz `Feature::CompatibilityCheck` (minimalny poziom Tank).

### Testy (złote przypadki na prawdziwych danych)

| Przypadek | Oczekiwanie |
|---|---|
| `FN0001` (Gupik, temperatura Grzałka często, Spokojny) i `FN0165` (Błyszczek tęczowy, Zimnolubne) | konflikt temperatury (indeksy 2 i 0) |
| `FN0117` (Bocja siatkowana, pH Kwaśna) i `FN0080` (Pyszczak Saulosa, pH Zasadowa) | konflikt pH; temperatury (b. często i często) sąsiadują, więc bez konfliktu temperatury |
| `FN0090` (Bojownik, Agresywny) i `FN0001` (Spokojny), ryba-ryba | uwaga temperamentu |
| `FN0153` (ślimak, Agresywny) i `FN0001` | brak uwagi temperamentu (reguła tylko ryba-ryba) |
| `FN0078` (Pielęgnica cytrynowa, WielkośćAkwarium tylko „Bardzo duże”) w zbiorniku Duże | uwaga „zbiornik za mały według bazy” |
| `FN0076` (Akara, B. duża) w zbiorniku Nano | konflikt (`4 − 0 ≥ 3`) |
| `FN0150` (Krewetka Amano, „Wszystkie”) w zbiorniku Nano | brak ustaleń o wielkości |
| Flora z CO2=Niezbędny (np. `FL0011`) bez Dozowania CO2 | informacja o CO₂ |
| Zbiornik bez pasma | brak oceny, prośba o uzupełnienie |
| Test spójności z danymi: wszystkie 19 900 par fauny | liczby par w konflikcie temperatury (624) i pH (2 584) zgodne z §1.2 |

### Stan: wdrożone

Silnik `Advisor\Engine\Compatibility\*` (czysty PHP), kontrakt `Advisor\Contracts\Compatibility` (`check`, `notesFor`), akcja `Aquarium\Actions\CheckLivestockFit`, migracja `tank_livestock.acknowledged_findings`. Różnice względem projektu:

- Ostrzeżenie jest w `AddToLivestock`: bez `acknowledge` akcja rzuca `LivestockFitWarning` i nic nie zapisuje (ta sama ochrona dla przyszłego API). Okno to osobna strona `aquarium/confirm-add` (formularze produktu i doradcy, bez JS) albo blok w komponencie `TankLivestock`. „Dodaj” w panelu zbiornika zwiększa teraz ilość (wcześniej ustawiało).
- Przyjęcie zapisuje kody `kontrola:ID[:ID]` w wierszu obsady; pomijane przy kolejnym dodaniu tego gatunku. Karta zgodności ich nie ukrywa.
- Karta jest częścią widoku `TankLivestock` (nad listą obsady), bez osobnej zakładki.
- Stan „Brak zastrzeżeń” nie zależy od ustaleń o ważności „informacja” (CO₂). Kontrola `stocking_load` (cm na litr) nie jest wdrożona (czeka na pytanie 3).
- Plakietka rankingu liczy uwagi i konflikty (bez informacji) i nie zmienia kolejności.

### Ryzyka

- Etykieta „Agresywny” bywa niejednoznaczna (ślimak drapieżny). Dlatego tylko ryby i treść „baza wskazuje”.
- Fałszywe poczucie bezpieczeństwa przy stanie „Brak zastrzeżeń”. Mitigacja: stała adnotacja i licznik „brak danych”.
- Dane w bazie są redakcyjne, mogą się zmienić po imporcie. Ocena zapisuje numer partii bazy (jak wynik doradcy).

---

## 3. Pomiar wody i podpowiedź z produktem

### Wartość dla użytkownika

Po zapisaniu pomiaru (i na karcie ostatniego pomiaru) użytkownik widzi, czy zmierzone pH lub temperatura wychodzi poza zakres wymagany przez jego obsadę według bazy, oraz — tylko jeśli baza jednoznacznie wskazuje produkt — produkt z pielęgnacji lub sprzętu i jego oferty w okolicznych sklepach.

### Źródła danych

Pomiar: `water_measurements.ph`, `temp_c`. Obsada: Temperatura i pH rekordów fauny (pasma z §1.3). Produkty: pielęgnacja (`CH…`, opis) i sprzęt (kategoria Ogrzewanie, WielkośćAkwarium, TypAkwarium). Oferty: `PublicOffers::forRecords` z filtrem miasta użytkownika (§12, `users.city`). Pozostałe parametry (KH, GH, NO₃, PO₄, NO₂, NH₄) nie mają w bazie wymagań gatunków; zostają przy dotychczasowych ostrzeżeniach o „typowym zakresie” (`MeasurementWarnings`), bez porady produktowej.

### Reguły

```
dla parametru P ∈ {pH, temperatura}:
  gatunki = fauna obsady z wartością P (zakres = tabela §1.3)
  jeśli gatunki puste: brak podpowiedzi (komunikat „brak wymagań w bazie dla tej obsady”)
  poza = { g : odczyt poza zakresem g }       # nie dotyczy odczytów na granicy pasm 21,5-22 i 25,5-26
  jeśli poza puste: koniec (nic nie mówimy)
  kierunek = „za wysokie” gdy odczyt > górna granica g, „za niskie” gdy < dolna
  komunikat = „Odczyt P = X jest poza zakresem wskazanym w bazie dla: {lista gatunków z wartością i zakresem}.”
  jeśli poza == gatunki i wszystkie w tym samym kierunku:    # porada bezpieczna dla całej obsady
      produkty = mapa(P, kierunek)  (tabela poniżej)
      jeśli produkty puste: „Baza nie zawiera produktu na ten przypadek. Sprawdź u sprzedawcy.”
  w przeciwnym razie: „Wymagania gatunków w zbiorniku różnią się (lista). Sprawdź zgodność obsady.” bez produktów
```

**Proponowana mapa problem → produkt** (tylko tam, gdzie baza jednoznacznie to opisuje; ustawienie `advisor.water_hints.map`, każdy wpis z cytatem uzasadniającym):

| Problem | Obszar i rekord | Dowód w danych | Uwagi |
|---|---|---|---|
| pH za wysokie | Pielęgnacja `CH0089` Acid Buffer (obszar Suplementy) | opis: „pozwala bezpiecznie obniżać pH w połączeniu z Alkaline Buffer, przy regularnej kontroli testami” | Kategoria rekordu (Przeciwdziałanie glonom i niedoborom) nie opisuje zastosowania, więc dopasowanie jest po identyfikatorze, nie po kategorii. Opis wspomina o Alkaline Buffer, którego nie ma w bazie. |
| pH za niskie | brak | brak produktu o opisie „podnosi pH” (`CH0094` EasyBalance „stabilizuje pH i twardość”, `CH0090`/`CH0041` Equilibrium podnoszą twardość) | Komunikat „brak danych / sprawdź u sprzedawcy”. |
| Temperatura za niska | Sprzęt: kategoria_glowna = Ogrzewanie (28 rekordów); filtr: WielkośćAkwarium zawiera pasmo zbiornika (lub „Wszystkie”) i TypAkwarium zawiera typ zbiornika | kategoria „Ogrzewanie” | Dla pasma Małe w bazie jest 10 pozycji, dla Nano 3. Jeśli lista pusta: „brak w bazie”. Moc w W nie jest w kolumnie, więc nie dobieramy mocy. |
| Temperatura za wysoka | brak (baza nie ma sprzętu chłodzącego) | brak | „Brak danych / sprawdź u sprzedawcy”. |
| Brak świeżego pomiaru pH | Pielęgnacja `CH0102` pH Test 6.0–7.6, `CH0104` Test pH 4.5–9.0 | opisy: „test kropelkowy do kontroli odczynu”, „pozwala dokładnie zmierzyć odczyn wody” | Aktywne dopiero po decyzji o progu „nieświeżego pomiaru” (pytanie 5). |
| Amoniak/azotyny > 0 (wyłączone do decyzji) | `CH0048` Prime („wiąże amoniak”), `CH0054` Stability („ogranicza skoki amoniaku i azotynów”), `CH0055` Nitrivec („wspiera rozkład amoniaku i azotynów”) | opisy | Wymaga progu i zatwierdzenia treści (pytanie 5). |
| PO₄ za wysokie (wyłączone) | `CH0086` PhosGuard („obniża poziom fosforanów”) | opis | Baza nie ma docelowego PO₄ dla żadnego gatunku (pytanie 5). |
| NO₃ za wysokie | brak produktu w bazie | `CH0100` ProAquaTest NO3 tylko mierzy | Tylko ostrzeżenie „typowy zakres”. |

Uwaga: istniejąca reguła korekty weryfikacji (`advisor.verification.correction_rules`, `ph_other`) wskazuje m.in. „equilibrium” dla pH. Opis `CH0090` mówi o twardości ogólnej, nie o pH. Dla podpowiedzi z pomiaru **nie** używamy tej listy; różnicę zgłaszamy w pytaniu 5.

### Czego NIE mówimy

- Nie podajemy dawki ani „ile wlać”; przy produkcie link do kalkulatora dawkowania (§7) i zdanie „Dawkowanie według opakowania producenta”.
- Nie mówimy „zrób podmianę”, „zmień karmienie”, „ryby chorują” (teksty ogólne dopiero po zatwierdzeniu przez właściciela, pytanie 6).
- Nie dajemy porady produktowej, gdy wymagania gatunków w zbiorniku są sprzeczne (łatwo pogorszyć sytuację części obsady).
- Nie oceniamy KH, GH, NO₃ ani PO₄ względem gatunków (brak danych).
- Nie sugerujemy zmiany pH „za wszelką cenę”; odczyt tuż przy granicy zakresu: do czasu decyzji o tolerancji pomiaru (pytanie 4) pokazujemy tylko informację z prośbą o powtórzenie testu, bez produktu.

### Przepływ UI

- Po zapisaniu pomiaru w `TankMeasurements` (`/moje-akwarium/zbiorniki/{id}`) pod potwierdzeniem pojawia się karta „Podpowiedź do tego pomiaru” (komunikat, dowód pod „Dlaczego?”, produkty z ceną „od” i sklepem; przycisk „Do koszyka” jak w doradcy; link do strony produktu).
- Ta sama karta nad ostatnim pomiarem na ekranie zbiornika.
- Oferty: sklepy w mieście użytkownika; brak miasta: komunikat „Ustaw miasto w koncie, żeby pokazać okoliczne sklepy” i oferty z całego kraju oznaczone miastem; brak ofert: przycisk „Daj znać, gdy będzie dostępne” (§4).

### Model danych i właściciel

Silnik w **Advisor** (`Engine\WaterHints`, czysty PHP): wejście pomiar, wymagania obsady, mapa; wyjście lista `Hint`. Akcja **Aquarium** `BuildWaterHints` składa pomiar, obsadę i oferty (przez kontrakty Advisor i Shops). Bez nowych tabel; mapa i progi w ustawieniach: `advisor.water_hints.map` (lista wpisów: problem, obszar, id, fraza dowodowa), `advisor.water_hints.tolerance` (pH, °C; start 0), `advisor.water_hints.checks` (włączone kontrole; start: pH i temperatura). Kontrola spójności mapy przy aktywacji partii bazy: `ActivateBatch` zgłasza ostrzeżenie administratorowi, gdy fraza dowodowa zniknęła z opisu.

### Powiadomienia i joby

Brak (podpowiedź jest w ekranie po zapisaniu pomiaru). Mobilnie: `BuildWaterHints` wywoływane przy zapisie pomiaru przez API.

### Dostęp

Tank (ostatni pomiar jest dziś w Tank): karta podpowiedzi. Gość: brak. Wiersz `Feature::WaterHints` (Tank). Oferty widoczne dla wszystkich z dostępem do koszyka; zakup wymaga `shop_cart` jak dziś.

### Testy (złote przypadki)

| Przypadek | Oczekiwanie |
|---|---|
| Zbiornik Małe, obsada `FN0001` (pH 6,8–7,5), pH = 7,9 | „za wysokie”, produkt `CH0089`, dowód z opisu |
| To samo, pH = 7,5 | brak podpowiedzi |
| To samo, pH = 6,5 | komunikat o braku produktu, bez rekomendacji |
| Obsada `FN0001`, temperatura 20 °C (zakres 22–26) | „za niska”, lista Ogrzewanie dla pasma Małe (10 pozycji z fixtures), brak mocy |
| Obsada `FN0001`, temperatura 21,8 °C | brak podpowiedzi (granica pasm) |
| Obsada `FN0001` + `FN0117` (Grzałka często i b. często), temperatura 23 °C | tylko informacja (poniżej zakresu `FN0117`, ale nie wszystkie gatunki), bez produktów |
| Obsada `FN0001` + `FN0080`, pH = 7,9 | informacja, bez produktu (sprzeczne wymagania) |
| Obsada z samą florą | brak podpowiedzi o pH i temperaturze |
| Zmiana opisu `CH0089` w nowej partii (fixture) | ostrzeżenie przy aktywacji partii |

### Ryzyka

- Dokładność testów kropelkowych (błąd odczytu) może dawać fałszywe „poza zakresem”; stąd tolerancja w ustawieniu i dopisek „powtórz test”.
- Obniżanie pH preparatem wymaga ostrożności; baza nie opisuje bezpiecznej szybkości. Do czasu decyzji właściciela rada nie zawiera nic poza cytatem z opisu produktu.
- Opis produktu w bazie to tekst redakcyjny, nie instrukcja. Dowód jest cytatem, a decyzja o zakupie należy do użytkownika.

---

## 4. „Daj znać, gdy będzie dostępne”

### Wartość dla użytkownika

Użytkownik, który znalazł w serwisie rekord (gatunek, roślinę, sprzęt, preparat) bez ofert w okolicy, zostawia obserwację i dostaje wiadomość, gdy któryś sklep wystawi ofertę.

### Źródła danych

`PublicOffers::forRecords(area, ids)` (oferty widoczne wg jednej polityki kwalifikacji), `freeQty > 0`, `locationCity` z DTO oraz `users.city`. Obserwacja jest własną tabelą Shops.

### Reguły

```
„rekord bez ofert” = brak widocznej oferty z freeQty > 0 (w mieście użytkownika, jeśli ustawione; inaczej gdziekolwiek)
dodaj obserwację: wymaga konta, limit wg poziomu (ustawienie), UNIQUE(user, area, legacy_id); rekord musi istnieć w katalogu
co 15 minut: dla nieodnotowanych obserwacji pobierz oferty rekordów (partiami per obszar)
   jeśli jest widoczna oferta z freeQty > 0 spełniająca filtr miasta:
        jedna wiadomość per użytkownik z listą rekordów i ofert (cena „od”, sklep, miasto)
        w tej samej transakcji: notified_at = now() WHERE notified_at IS NULL (idempotencja)
obserwacja po powiadomieniu: pozostaje 14 dni jako „powiadomiono”, potem jest usuwana; „Obserwuj ponownie” tworzy nową
```

### Czego NIE mówimy

Nie obiecujemy terminu ani że oferta się pojawi; nie ujawniamy użytkownikowi, który sklep ma towar poza treścią publicznej oferty; nie pokazujemy cudzych obserwacji. Gdy użytkownik nie ma miasta, komunikat mówi wprost, że powiadomimy o ofercie z dowolnego sklepu.

### Przepływ UI

- Strona produktu `/katalog/{obszar}/{id}`: przy braku ofert przycisk „Daj znać, gdy będzie dostępne” (stan zalogowany; dla gościa prowadzi do logowania). Po dodaniu: „Obserwujesz. Powiadomimy wiadomością e-mail”.
- Karta w doradcy i podpowiedź z pomiaru (§3): ten sam przycisk przy produktach bez ofert.
- Lista obserwacji w `/moje-konto/obserwowane` (usuń, pokaż stan), link „Wypisz się z powiadomień” w każdej wiadomości (temat „dostępność” w preferencjach).
- Panel sklepu (§11): licznik osób czekających na rekord w okolicy (liczba, nie osoby).

### Model danych i właściciel

Moduł **Shops**. Tabela `record_watches`: `id`, `user_id` (FK users, `ON DELETE CASCADE`), `area`, `legacy_id`, `city_key` (znormalizowane miasto użytkownika w chwili zapisu lub `NULL`), `created_at`, `notified_at NULL`. `UNIQUE(user_id, area, legacy_id)`, `IX(notified_at, area, legacy_id)`. Akcje: `WatchRecord`, `UnwatchRecord`, `NotifyRecordWatchers` (job w `routes/console.php`, `everyFifteenMinutes()`), kontrakt `Shops\Contracts\WatchCounts::byRecord(city, area, ids)` dla statystyk. Powiadomienie `RecordAvailableNotice` (kolejka, sprawdza preferencje z modułu Notifications). Ustawienie `shops.watch` (limit na poziom: `tank`, `fishroom`; dni przechowania po powiadomieniu).

### Powiadomienia i joby

Jedna wiadomość e-mail na użytkownika na przebieg (zbiorcza). Kolejka z ponowieniem. Preferencje: temat `availability`, domyślnie włączony dla obserwacji założonych przez użytkownika (to akcja na jego życzenie). Mobilnie: ten sam powiadomień, kanał push dopisywany w `via()`.

### Dostęp

Tank: do 10 aktywnych obserwacji (propozycja, ustawienie); Fishroom: 100 (propozycja). Gość: przycisk prowadzi do rejestracji. `Feature::RecordWatch` (Tank).

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Rekord `FN0001` bez ofert, obserwacja, potem opublikowana oferta z ilością > 0 | po przebiegu joba jedna wiadomość, `notified_at` ustawione; drugi przebieg nic nie wysyła |
| Oferta z ilością 0 | brak powiadomienia |
| Oferta w innym mieście niż `city_key` | brak powiadomienia; po zmianie filtra (brak miasta) wysłane |
| Oferta wygasła (`valid_to` w przeszłości) lub sklep wstrzymany | brak powiadomienia (polityka `PublicOffers`) |
| Dwa rekordy użytkownika spełnione w jednym przebiegu | jedna wiadomość z dwiema pozycjami |
| Przekroczony limit | błąd walidacji z komunikatem o limicie |
| Użytkownik wypisany z tematu | brak wiadomości, obserwacja zostaje |
| Usunięcie konta | obserwacje usunięte kaskadowo |

### Ryzyka

Spam (limit, jedna zbiorcza wiadomość, wypisanie); dopasowanie miasta po tekście (rozbieżna pisownia: normalizacja jak `Catalog::fold`, pytanie 10); obciążenie jobu przy wielu obserwacjach (jedna kwerenda ofert na rekord, partiami).

---

## 5. Plan nowego akwarium

### Wartość dla użytkownika

Prowadzone przejście: wielkość i typ zbiornika, ryby, rośliny, sprzęt, pielęgnacja; na końcu jedna lista zakupów rozbita na sklepy z cenami. Po zakupie plan zamienia się w zbiornik z obsadą.

### Źródła danych

Wszystkie cztery obszary katalogu. Silniki: ranking Tank (`TankEngine`) dla ryb, kwalifikacja K1 z kreatora (`Guide`, odpowiedzi „litraż” i „typ” z planu) dla roślin, sprzętu i pielęgnacji. Zgodność: §2. Oferty: `PublicOffers`.

### Reguły rekomendacji (z danych)

| Krok | Reguła | Źródło |
|---|---|---|
| Zbiornik | litraż z kalkulatora (§7) → `TankBand::forLiters`, typ z listy `TankType` | progi pasm z etykiet |
| Ryby | lista rankingu Tank dla profilu planu; każdy dodany gatunek przechodzi kontrolę zgodności z planowaną obsadą | §2 |
| Rośliny | rekordy flory, których WielkośćAkwarium obejmuje pasmo (lub „Wszystkie”) i TypAkwarium obejmuje typ | kreator K1 |
| Sprzęt: Ogrzewanie | jeśli którakolwiek wybrana ryba ma Temperaturę Grzałka często lub b. często: „Wskazana (baza: Grzałka często)”; tylko Grzałka sporadycznie: „Zależnie od pomieszczenia (baza: Grzałka sporadycznie)”; tylko Zimnolubne: „Baza nie wskazuje grzałki”; brak ryb: bez oceny | Temperatura fauny |
| Sprzęt: Dozowanie CO2 | roślina z CO2=Niezbędny: „Niezbędne dla: …”; tylko Wskazany: „Wskazane”; tylko Zbędny: bez oceny | CO2 flory |
| Sprzęt: Filtracja, Oświetlenie LED | lista pozycji pasujących do pasma i typu; bez twierdzenia, że element jest „obowiązkowy” (baza tego nie mówi, pytanie 7) | sprzęt |
| Pielęgnacja: start | wszystkie rekordy kategoria_zadania = „Start / przygotowanie” (28: Przygotowanie wody i Przygotowanie środowiska) | pielęgnacja |
| Pielęgnacja: karmienie | Ryba → obszar „Pokarmy dla ryb”; Skorupiak → „Pokarmy dla krewetek”; Ślimak → „brak danych” (baza nie ma takiego obszaru) | jak `Verifier::supporting` |
| Pielęgnacja: rośliny | gdy są rośliny: obszary „Nawozy” i „Preparaty wzbogacające” | pielęgnacja |
| Pielęgnacja: testy | rodzaj_zadania „Testowanie wody”, opcjonalnie | pielęgnacja |

Filtr sprzętu: WielkośćAkwarium zawiera pasmo planu lub „Wszystkie” oraz TypAkwarium zawiera typ planu. Pielęgnacja nie ma pól akwarium, więc nie jest filtrowana.

### Czego NIE mówimy

Nie piszemy „tego potrzebujesz”, jeśli baza nie ma takiej informacji (filtr, oświetlenie, ilość roślin, obsada „na litr” bez zatwierdzonego progu). Nie dobieramy mocy grzałki ani wydajności filtra w jednostkach. Nie składamy listy „zestaw startowy” z ilościami roślin i ryb wymyślonymi przez nas: ilości wpisuje użytkownik, a obciążenie pokazujemy według §7.

### Lista zakupów rozbita na sklepy

```
pozycje = (area, catalog_id, qty) z planu
oferty  = PublicOffers::forRecords po obszarze (filtr miasta jak w §3)
dla każdego sklepu (i jednej placówki, bo koszyk dopuszcza jedną placówkę na sklep):
    pokrycie = pozycje, które sklep ma w ilości >= qty (częściowe: oznaczone „dostępne N z M”)
    koszt    = suma cena × qty
wynik = lista sklepów malejąco po liczbie pokrytych pozycji, potem po koszcie
pozycje bez ofert = osobna sekcja z przyciskiem „Daj znać” (§4)
```

Przycisk „Dodaj wszystko od tego sklepu do koszyka” woła `ChangeCart` dla każdej pozycji (zasady koszyka bez zmian: zakaz kupna we własnym sklepie, limity ilości, jedna placówka). Pozycja niedostępna w całości jest pomijana z komunikatem.

**Miejsce na optymalizator „Zmieść mój zakup”:** akcja `SplitShoppingList` zwraca stały typ wyniku `ShoppingSplit` (lista sklepów z pozycjami, braki, suma). Dziś liczy prostą regułę powyżej. Optymalizator zastąpi jej wnętrze i doda pola kosztu przy limicie odbiorów, bez zmiany `ShoppingSplit`, ekranu ani koszyka. Limit odbiorów (propozycja 2, `10-domena-sklepu.md` §3) będzie wtedy jednym z parametrów wejścia.

### Przepływ UI

Nowa strona w strefie akwarysty `/moje-akwarium/plany` (lista) i `/moje-akwarium/plany/{id}` (kreator pięciu kroków z paskiem postępu, „Wstecz”, zapis po każdym kroku). Wejście z kafla „Zaplanuj nowe akwarium” na `/moje-akwarium` i ze strony głównej (sekcja doradcy). Krok końcowy: lista zakupów, przycisk „Utwórz akwarium z planu” (po zakupie) oraz „Do koszyka” per sklep. Zgodność obsady (§2) widoczna w kroku ryb.

### Model danych i właściciel

Moduł **Aquarium**. Tabele:

| Tabela | Kolumny |
|---|---|
| `tank_plans` | `id`, `user_id`, `name`, `size_band`, `type`, `liters` (null), `temp_band` (null), `ph_band` (null), `step`, `state` (`draft`/`converted`), `converted_tank_id` (null), `version`, daty |
| `tank_plan_items` | `plan_id`, `area`, `catalog_id`, `quantity`; `UNIQUE(plan_id, area, catalog_id)` |
| `tank_equipment` (wspólna z §6, tworzona w pakiecie fundamentowym) | `id`, `tank_id`, `area` (sprzęt), `catalog_id`, `installed_on` (null) |

Akcje: `CreatePlan`, `SavePlanStep`, `AddPlanItem`, `BuildShoppingList`, `SplitShoppingList`, `ConvertPlanToTank` (w transakcji, z kontrolą limitu zbiorników jak `CreateTank`; kopiuje obsadę i wyposażenie). Ustawienie `aquarium.plan.limit` (`drop`, `tank`, `fishroom`).

### Powiadomienia i joby

Brak. Opcjonalnie później: przypomnienie o niedokończonym planie (poza zakresem).

### Dostęp

Tank: 1 aktywny plan (propozycja). Fishroom: bez limitu. Gość: strona prowadzi do rejestracji, bez zapisu. `Feature::TankPlan` (Tank). Konwersja do zbiornika respektuje limit zbiorników (`TankLimit`); plan w stanie `draft` nie zużywa limitu zbiorników.

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Plan Nano z `FN0060` (Temperatura Grzałka sporadycznie) | rekomendacja grzałki „zależnie od pomieszczenia”, bez „wskazana” |
| Plan z `FN0001` | grzałka „wskazana” (Grzałka często) |
| Plan z samymi roślinami `CO2=Zbędny` | brak rekomendacji Dozowania CO2 |
| Plan z `FL0011` (CO2=Niezbędny) | rekomendacja Dozowania CO2 z listą pasującą do pasma i typu |
| Krok pielęgnacji dla planu z krewetką `FN0151` | w karmieniu „Pokarmy dla krewetek” (8 pozycji), dla ślimaka „brak danych” |
| Krok startowy | dokładnie 28 pozycji kategorii „Start / przygotowanie” |
| Lista zakupów: dwa sklepy, jeden z pełnym pokryciem | kolejność wg pokrycia; pozycje bez ofert w osobnej sekcji |
| Pozycja z ilością większą niż dostępna | oznaczona „dostępne N z M”, „Do koszyka” dodaje tylko dostępną ilość po potwierdzeniu |
| `ConvertPlanToTank` przy wypełnionym limicie Tank | odmowa z komunikatem z `ResolveAccess` |
| Idempotencja konwersji | druga konwersja tego samego planu nie tworzy drugiego zbiornika |

### Ryzyka

Użytkownik odbierze listę jako „kompletny zestaw” (mitigacja: nagłówek „Wybrane przez Ciebie pozycje”, brak sformułowań „potrzebujesz”); rozjazd stanu magazynowego między planem a zakupem (lista liczy bieżące oferty); koszyk jednej placówki na sklep przy pozycjach w różnych placówkach (komunikat konfliktu, jak dziś).

---

## 6. Przypomnienia z obsady i sprzętu

### Wartość dla użytkownika

Serwis podpowiada przypomnienia (podmiana wody, serwis filtra, nawożenie, test wody, kończący się preparat) na podstawie tego, co użytkownik ma w zbiorniku, i wysyła wiadomość w terminie. Przy kończącym się preparacie wiadomość zawiera link do ofert.

### Źródła danych

Obsada (`tank_livestock`), wyposażenie (`tank_equipment`, kategoria rekordu sprzętu: Filtracja, Ogrzewanie itd.), preparaty zbiornika (`tank_consumables`), istniejące `reminders` i `reminder_occurrences`. **Baza nie zawiera interwałów serwisu ani dawkowania**, więc interwały pochodzą z ustawień zatwierdzonych przez właściciela (pytanie 8), a daty końca preparatu wyłącznie z danych wpisanych przez użytkownika.

### Reguły

```
podpowiedzi (nie tworzą się „po cichu”; użytkownik akceptuje jednym kliknięciem):
  obsada niepusta            -> typ water_change,    interwał z ustawienia auto.water_change_days
  wyposażenie z Filtracja    -> typ filter_cleaning, interwał z ustawienia auto.filter_cleaning_days
  flora + preparat nawozowy  -> typ fertilization,   interwał z ustawienia auto.fertilization_days
  zbiornik bez pomiaru N dni -> typ water_test,      N z ustawienia
jeśli ustawienie ma wartość null: podpowiedź prosi użytkownika o wpisanie własnego interwału (bez domyślnej wartości)

kończący się preparat (dane od użytkownika z etykiety opakowania):
  dawka_na_zabieg = dawka_etykiety × litry_zbiornika / litry_odniesienia_etykiety
  zabiegi_w_opakowaniu = floor(zawartość_opakowania / dawka_na_zabieg)
  data_końca = data_otwarcia + zabiegi_w_opakowaniu × odstęp_między_zabiegami (dni)
  przypomnienie = data_końca − ustawienie auto.consumable_lead_days
  litry_zbiornika = tanks.volume_liters (wpisane przez użytkownika); brak -> prośba o litraż, bez wyliczenia
```

### Czego NIE mówimy

Nie piszemy „zrób podmianę 30%” ani „wymień wkład co N tygodni”; interwał jest zawsze tym z ustawienia zatwierdzonego przez właściciela albo wpisanym przez użytkownika. Nie wyliczamy końca preparatu bez danych z opakowania. Nie sugerujemy, że termin jest dowodem potrzeby.

### Przepływ UI

- Panel zbiornika, zakładka „Przypomnienia” (`TankReminders`): sekcja „Podpowiedzi” (każda z przyciskiem „Dodaj” po zatwierdzeniu interwału).
- Nowa zakładka „Wyposażenie i preparaty” w panelu zbiornika: dodawanie sprzętu z katalogu (wyszukiwarka `Catalog::search`), dodawanie preparatu z katalogu pielęgnacji z polami z opakowania (zawartość i jednostka, dawka, na ile litrów, co ile dni, data otwarcia). Link do kalkulatora dawkowania.
- Wiadomość e-mail z terminami dnia; dla preparatu przycisk „Zobacz oferty” prowadzący do `/katalog/care/{id}` (oferty w okolicy, §3).

### Model danych i właściciel

Moduł **Aquarium**.

| Zmiana | Opis |
|---|---|
| `tanks.volume_liters` (null) | Litraż wpisany lub z kalkulatora (§7). Pasmo zostaje źródłem doboru; zmiana pasma tylko przez edycję zbiornika z potwierdzeniem. |
| `tank_equipment` | jak w §5 |
| `tank_consumables` | `id`, `tank_id`, `catalog_id` (pielęgnacja), `package_amount` (decimal), `unit` (`ml`/`g`/`pcs`), `dose_amount`, `dose_per_liters`, `interval_days`, `opened_on`, `ends_on` (wyliczone i zapisane), `reminder_id` (null) |
| `reminders` | dochodzą `origin` (`manual`/`suggested`) i `source_key` (np. `water_change`, `consumable:{id}`), `UNIQUE(tank_id, source_key)` — idempotencja podpowiedzi |
| `reminder_notices` | `occurrence_id` (UNIQUE), `sent_at` — dedup wysyłki |

Akcje: `SuggestReminders`, `AcceptReminderSuggestion` (woła `CreateReminder`), `SaveEquipment`, `SaveConsumable` (przelicza `ends_on` i tworzy lub aktualizuje przypomnienie), `SendDueReminders`. Ustawienie `aquarium.reminders.auto` (interwały w dniach lub `null`, `consumable_lead_days`, `water_test_after_days`).

### Powiadomienia i joby

`SendDueReminders` codziennie o godzinie z ustawienia (strefa `Europe/Warsaw`): terminy przypadające dziś i zaległe niewysłane, jedna wiadomość na użytkownika (lista zbiorników i terminów), `reminder_notices` jako dedup. Temat preferencji `reminders`. Przejście na push w aplikacji mobilnej: ten sam job, dodatkowy kanał.

### Dostęp

Fishroom (zgodnie z istniejącym wierszem „Historia, trendy, dziennik i przypomnienia”). Tank widzi zaproszenie (opis i przykład), bez zapisu. Wyposażenie zbiornika (lista sprzętu) dostępne dla Tank, bo jest potrzebne do kontroli CO₂ (§2) i planu (§5). `Feature::ReminderSuggestions` (Fishroom).

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Zbiornik z `FN0001`, ustawienie interwału wody `null` | podpowiedź bez wartości domyślnej, wymaga wpisu |
| Podpowiedź zaakceptowana dwa razy | jedno przypomnienie (`UNIQUE(tank_id, source_key)`) |
| Preparat: 250 ml, dawka 5 ml na 100 l, zbiornik 63 l, co 7 dni, otwarty 1.12.2026 | dawka 3,15 ml, 79 zabiegów, koniec 7 × 79 = 553 dni później; przypomnienie o ustawione dni wcześniej |
| Preparat bez litrażu zbiornika | prośba o litraż, bez daty końca |
| `SendDueReminders` uruchomiony dwa razy tego samego dnia | jedna wiadomość |
| Zbiornik innego użytkownika | 404 (`Access`) |
| Zakończony Fishroom | brak wysyłki i brak podpowiedzi |

### Ryzyka

Użytkownik przyjmie interwał jako „zalecenie serwisu” (mitigacja: etykieta „Twoje przypomnienie co N dni”); błędne dane z opakowania wprowadzone przez użytkownika (pokazujemy wyliczenie krok po kroku); nadmiar wiadomości (jedna zbiorcza dziennie, preferencje).

---

## 7. Kalkulatory

### Wartość dla użytkownika

Cztery proste narzędzia, dostępne także bez konta: litraż z wymiarów, dawkowanie według etykiety producenta, szacunek CO₂ z KH i pH, obciążenie „cm ryby na litr”. Obok istnieje kalkulator Redfielda.

### Wzory i źródła

| Kalkulator | Wzór | Źródło i zastrzeżenia |
|---|---|---|
| Litraż | prostopadłościan: `L × W × H (cm) / 1000 = litry`; walec: `π × (d/2)² × H / 1000` | geometria; użytkownik podaje wysokość lustra wody, a nie wysokość szkła; wynik to objętość brutto, bez podłoża i dekoracji (pole „odejmij litry” wypełnia użytkownik) |
| Dawkowanie | `dawka = dawka_etykiety × V_zbiornika / V_odniesienia_etykiety`, opcja podstawy: cały zbiornik lub objętość podmienianej wody | tylko skalowanie wartości z etykiety producenta; baza wiedzy nie ma dawek; brak pola „zalecana dawka” |
| CO₂ | `CO₂ [mg/l] ≈ 3 × KH [°dKH] × 10^(7 − pH)` | zależność węglanowa (równanie Hendersona-Hasselbalcha) powszechnie używana w tabelach KH/pH/CO₂; źródła pomocnicze: https://aquariummath.com/calculators/co2 (tabela, przykład KH 4 / pH 6,8 ≈ 19 mg/l) oraz https://www.aquacalc.uk/blog/kh-co2-ph. Współczynnik 3 i brak korekty temperatury to przybliżenie; wzór zawyża wynik, gdy pH obniżają kwasy, torf, garbniki lub bufory inne niż węglanowe (np. preparaty obniżające pH, `CH0089`). Wartość współczynnika potwierdza właściciel (pytanie 9) |
| Cm ryby na litr | `obciążenie = Σ(ilość × długość_dorosłej_ryby_cm) / litry_netto` | **orientacyjna reguła praktyczna**, nie norma i nie wynik badań. Długość z górnej granicy pasma Wielkość (Nano 3, Mała 5, Średnia 10, Duża 15 cm); dla „B. duża (>15 cm)” baza nie ma długości, więc użytkownik wpisuje własną albo pozycja jest pominięta z komunikatem „brak danych”. Próg oceny pochodzi wyłącznie z ustawienia zatwierdzonego przez właściciela (pytanie 3); do tego czasu pokazujemy sam wynik w cm/l |

### Reguły i czego NIE mówimy

- Kalkulator CO₂ pokazuje liczbę i wzór. Ocenę stężenia („za dużo”, „bezpiecznie dla ryb”) pokazujemy dopiero, gdy właściciel zatwierdzi progi i źródło (często cytowane w poradnikach roślinnych: około 15–30 mg/l, a 30 mg/l jako granica bezpieczeństwa ryb; to nie jest dane bazy, więc nie wchodzi bez zatwierdzenia).
- Kalkulator dawkowania nie podpowiada produktu, dawki ani częstotliwości. Wymaga trzech pól z opakowania i pokazuje obliczenie krok po kroku, z komunikatem „Sprawdź dawkowanie na opakowaniu producenta. Kalkulator tylko przelicza podane wartości”. Wynik pokazujemy z dokładnością do 0,01 jednostki (zaokrąglenie matematyczne), bez zaokrąglania w górę.
- Cm/litr nie mówi „zbiornik jest przeładowany”, dopóki nie ma zatwierdzonego progu.
- Litraż nie przestawia pasma zbiornika automatycznie; proponuje je („To pasmo Małe 40–100 l”), użytkownik zatwierdza.

### Przepływ UI

Strona „Kalkulatory” `/kalkulatory` (publiczna) z czterema podstronami `/kalkulatory/litraz`, `/dawkowanie`, `/co2`, `/obsada`. Dla zalogowanego: przycisk „Zapisz litraż w zbiorniku”, wybór zbiornika w kalkulatorze obsady (podstawia obsadę i litraż), link do kalkulatora Redfielda (`/moje-akwarium/redfield`). Z karty podpowiedzi (§3) i z zakładki preparatów (§6) link do dawkowania. Wyniki w widoku z polem „Jak policzono”.

### Model danych i właściciel

Moduł **Aquarium**: czyste klasy bez Eloquent w `Aquarium\Calculators` (`TankVolume`, `Dosage`, `Co2Estimate`, `StockingLoad`), akcje `CalculateTankVolume`, `CalculateDosage`, `EstimateCo2`, `CalculateStocking` (walidacja wejścia, zakresy z ustawień). Zapis litrażu: `tanks.volume_liters` (§6). Ustawienia: `aquarium.calculators.limits` (zakresy wejść), `aquarium.calculators.co2` (współczynnik, opcjonalne progi z opisem źródła), `aquarium.stocking.cm_per_liter` (`null` = bez oceny).

### Powiadomienia i joby

Brak.

### Dostęp

Gość: wszystkie cztery, bez zapisu i bez podstawiania obsady. Tank i Fishroom: zapis litrażu w zbiorniku, podstawianie obsady. `Feature::Calculators` (Drop). Redfield zostaje przy dotychczasowym wierszu `Feature::Redfield`.

### Testy (złote przypadki)

| Przypadek | Oczekiwanie |
|---|---|
| 60 × 30 × 35 cm | 63,0 l → pasmo Małe (40–100 l) |
| Walec Ø 30 cm, wysokość 40 cm | 28,27 l → pasmo Nano |
| CO₂: KH 4, pH 6,8 | 19,0 mg/l |
| CO₂: KH 4, pH 7,2 | 7,6 mg/l |
| CO₂: KH 6, pH 7,0 | 18,0 mg/l |
| Dawkowanie: 5 ml na 100 l, zbiornik 63 l | 3,15 ml (krok po kroku widoczny) |
| Cm/l: 10 × `FN0001` (górna granica 5 cm) + 6 × `FN0060` (3 cm), 63 l | 68 cm / 63 l = 1,08 cm/l bez oceny (próg `null`) |
| Cm/l z gatunkiem „B. duża” (`FN0076`) | pozycja pominięta z komunikatem „brak danych”, po wpisaniu długości liczona |
| Wartości ujemne, zero, wartości nieliczbowe | błąd walidacji, bez wyniku |

### Ryzyka

Wzór CO₂ użyty poza założeniami (komunikat o ograniczeniach przy każdym wyniku); traktowanie reguły cm/l jako normy (nazwa „orientacyjna”, brak oceny bez progu); błąd wpisania jednostki w dawkowaniu (jednostka z listy, nie wolny tekst).

---

## 8. Statystyki popytu dla sklepów

### Wartość dla sklepu

Sklep w panelu widzi, czego akwaryści szukają w jego okolicy i które rekordy z doradcy nie mają ofert w okolicy, razem z liczbą osób czekających na dostępność. Z listy może od razu dodać ofertę.

### Źródła danych

Liczniki zdarzeń popytu (poniżej), obserwacje (`WatchCounts`), widoczne oferty (`PublicOffers`), miasta placówek sklepu. Regionem jest `city_key` (miasto użytkownika po normalizacji); użytkownik bez miasta liczy się tylko w statystyce ogólnej.

### Zdarzenia i anonimizacja

| Zdarzenie | Kiedy |
|---|---|
| `advisor_leader` | karta zwycięzcy rankingu lub kreatora pokazana użytkownikowi |
| `catalog_view` | wyświetlenie strony produktu |
| `watch` | założona obserwacja (§4) |
| `cart_add` | dodanie do koszyka |

Zapis to licznik, nie dziennik: `INSERT … ON DUPLICATE KEY UPDATE hits = hits + 1` na kluczu `(day, city_key, area, legacy_id, kind)`. Nie zapisujemy `user_id`, adresu IP, sesji ani czasu. Deduplikacja (jeden rekord, jedno zdarzenie dziennie na odwiedzającego) przez klucz w pamięci podręcznej z hashem sesji i datą, wygasający o północy. Żądania botów pomijamy (nagłówek i podstawowe wzorce). Zbiorcze wartości pokazujemy tylko, gdy `hits ≥ k` (ustawienie `metrics.demand`, propozycja `k = 5`), a regiony i rekordy poniżej progu łączymy w „inne”.

### Reguły

```
popyt(miasto, okres) = suma hits po rekordach, tylko wiersze z hits >= k, malejąco
bez_ofert(miasto)    = rekordy z popytu, dla których PublicOffers nie zwraca widocznej oferty z freeQty > 0
                       w tym mieście
czekający            = WatchCounts::byRecord(miasto, ...)  (liczba, bez tożsamości)
```

### Czego NIE mówimy

Nie pokazujemy pojedynczych użytkowników, e-maili ani godzin. Nie obiecujemy sprzedaży ani „zapotrzebowania” w złotówkach. Nie porównujemy sklepów ze sobą. Gdy danych jest za mało (poniżej progu): „Za mało danych, żeby pokazać statystykę w Twojej okolicy”.

### Przepływ UI

Panel sklepu `/panel-sklepu`: nowa strona „Popyt w okolicy” (okres 7/30/90 dni, filtr obszaru: ryby, rośliny, sprzęt, pielęgnacja). Tabele: najczęściej szukane, bez ofert w okolicy, liczba osób czekających. Przycisk „Dodaj ofertę” otwiera formularz oferty z wypełnionym rekordem (istniejący `OfferResource`). Admin `/admin`: widok globalny tej samej strony.

### Model danych i właściciel

Moduł **Metrics** (planowany w `app/Domain/README.md`). Tabela `demand_signals`: `day` (DATE), `city_key` (VARCHAR 80, pusty = bez regionu), `area`, `legacy_id`, `kind`, `hits` (UNSIGNED INT); `PRIMARY KEY (day, city_key, area, legacy_id, kind)`. Akcja `RecordDemand` (wołana z kontrolerów doradcy i strony produktu, z obsługą błędów, która nie psuje strony), kontrakt `Metrics\Contracts\Demand::top(city, from, to, area, limit)`, job `PruneDemandSignals` (retencja z ustawienia, propozycja 400 dni). Strona w panelu sklepu składa `Demand`, `PublicOffers` i `WatchCounts`.

### Powiadomienia i joby

`PruneDemandSignals` codziennie. Opcjonalnie później: cotygodniowy skrót e-mail dla sklepu (poza zakresem).

### Dostęp

Członek sklepu (każdy) widzi własną okolicę; w pilocie bez opłat. Czy rozszerzone statystyki (trendy, eksport) mają być płatne: do decyzji po pilocie. Nie ma funkcji dla konta akwarysty. Sprawdza `Shops\Access`.

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| 5 wyświetleń `FN0001` w Warszawie jednego dnia przez 5 sesji | wiersz `hits = 5`, widoczny dla sklepu w Warszawie |
| 4 wyświetlenia | brak w tabeli sklepu (poniżej `k`) |
| Ta sama sesja 10 razy ten sam rekord | liczy się 1 |
| Użytkownik bez miasta | tylko wiersz z pustym `city_key`, niewidoczny dla sklepów |
| Rekord z ofertą w mieście | nie pojawia się na liście „bez ofert” |
| Obserwacja w mieście | pojawia się jako liczba osób czekających |
| Sklep z innego miasta | nie widzi danych cudzego regionu |
| Test prywatności | w żadnej tabeli ani logu nie ma `user_id`, IP, sesji |

### Ryzyka

Ponowna identyfikacja przy małej liczbie osób w mieście (próg `k`, łączenie w „inne”); manipulacja popytem przez sklep (dedup sesji, limity, brak wpływu na rankingi); informacja o przetwarzaniu miasta w polityce prywatności (do uzupełnienia razem z `users.city`).

---

## 9. Import ofert z CSV

### Wartość dla sklepu

Sklep wgrywa jeden plik zamiast wpisywać oferty po jednej: nowe oferty, zmiany cen i ilości w jednym przebiegu, z podglądem błędów przed zapisem. Arkusz (Excel, Google Sheets) wystarczy wyeksportować do CSV.

### Źródła danych

Plik CSV od członka sklepu. Katalog (`Catalog::labels`) do sprawdzenia identyfikatorów. Ustawienia: `shops.import.csv` (istniejące: 10 MB i 10 000 wierszy), `shops.offer.bounds`, `shops.offer.min_price_grosze`, `shops.activation` (limit ofert). Istniejące akcje `SaveOffer`, `PublishOffer`, `SetAvailableQuantity`.

### Format pliku

| Kolumna | Wymagana | Zasady |
|---|---|---|
| `id_rekordu` | tak | np. `FN0001`; musi istnieć w aktywnym katalogu |
| `wariant` | nie | tekst; pusty = bez wariantu; razem z rekordem i placówką tworzy tożsamość oferty |
| `cena` | tak | w PLN z przecinkiem lub kropką, do 2 miejsc; zamiana na grosze; granice z ustawień |
| `ilosc` | tak | liczba całkowita ≥ 0, granice z ustawień |
| `placowka` | gdy sklep ma więcej niż jedną czynną placówkę | nazwa placówki (jak w panelu) |
| `wazna_od`, `wazna_do` | nie | daty ISO `RRRR-MM-DD`; domyślnie jak przy ofercie dodawanej w panelu |
| `publikuj` | nie | `tak`/`nie`; domyślnie `tak` |

Separator `;` lub `,` wykrywany z nagłówka. Kodowanie: UTF-8 (z BOM lub bez); jeśli plik nie jest poprawnym UTF-8, próbujemy Windows-1250 (typowy eksport polskiego Excela) i pokazujemy to w podglądzie. Szablon do pobrania: nagłówek i trzy przykładowe wiersze z prawdziwymi identyfikatorami.

### Reguły

```
krok 1 (podgląd, bez zapisu):
   walidacja pliku (rozmiar, liczba wierszy, nagłówki)
   dla każdego wiersza: rekord w katalogu, cena i ilość w granicach, placówka należy do sklepu,
        daty poprawne, brak duplikatu (rekord+wariant+placówka) w pliku
   limit ofert: liczba opublikowanych niewygasłych ofert po zapisie <= limit (150); nadmiar = błędy wierszy
   klasyfikacja: nowa / zmieniona / bez zmian / błąd
krok 2 (zapis, po potwierdzeniu):
   potwierdzenie sklepu: „ilości odpowiadają stanowi fizycznemu” (wymóg SetAvailableQuantity)
   zadanie w kolejce: każdy poprawny wiersz przez SaveOffer (+ PublishOffer) i SetAvailableQuantity z aktualną wersją
   wiersz błędny jest pomijany z powodem; przebieg można powtórzyć tym samym plikiem (wiersze bez zmian nie tworzą historii)
```

### Czego NIE mówimy

Nie zgadujemy identyfikatorów po nazwie (tylko dokładny identyfikator); nie poprawiamy cen ani ilości; nie ustawiamy ilości bez potwierdzenia stanu fizycznego. Link do arkusza Google (pobieranie z adresu) nie jest w zakresie: ryzyko SSRF i prywatności; wymaga osobnej decyzji (pytanie w §14.2).

### Przepływ UI

Panel sklepu `/panel-sklepu`, nowa strona „Import ofert” w grupie Oferty: pole pliku, przycisk „Sprawdź plik”, tabela podglądu (pierwsze 50 wierszy, liczniki: nowe, zmienione, bez zmian, błędy; pobranie pliku z błędami), pole zgody dotyczące stanu fizycznego, „Zapisz oferty”. Po zakończeniu powiadomienie Filament i raport.

### Model danych i właściciel

Moduł **Shops**. Tabela `shop_import_batches`: `id`, `shop_id`, `user_id` (nullOnDelete), `file_sha256`, `state` (`preview`/`queued`/`applied`/`failed`), liczniki, `report` (JSON z błędami wierszy, ograniczony rozmiarem), daty. Plik przechowywany na prywatnym dysku do czasu zapisu i usuwany po zakończeniu (retencja z ustawienia). Akcje `PreviewOfferImport`, `ApplyOfferImport` (job). Wiersze realizowane istniejącymi akcjami, więc blokady, historia ilości i wersje warunków działają jak dziś.

### Powiadomienia i joby

Job kolejki `ApplyOfferImport` (ponowienie bezpieczne). Powiadomienie dla użytkownika, który uruchomił import (Filament i e-mail przy błędach).

### Dostęp

Członek sklepu z prawem edycji ofert (`Shops\Access::authorizeMember`); w pilocie dla wszystkich sklepów. Brak dostępu dla pracownika bez uprawnienia do ofert (jak panel).

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Plik z 3 poprawnymi wierszami (np. `FN0001`, `SP0046`, `CH0047`) | podgląd „3 nowe”, po zapisie 3 oferty opublikowane z historią ilości |
| Ten sam plik drugi raz | „3 bez zmian”, brak nowych wpisów historii |
| Nieznany identyfikator `FN9999` | błąd wiersza, reszta zapisana |
| Cena `0,50` poniżej minimum z ustawień | błąd wiersza |
| Duplikat rekordu i wariantu w pliku | błąd obu wierszy |
| 151. oferta przy limicie 150 | błąd tego wiersza z powodem „limit ofert” |
| Plik Windows-1250 z polskimi znakami w wariancie | poprawne odczytanie, informacja w podglądzie |
| Brak zgody na stan fizyczny | zapis zablokowany |
| Pracownik innego sklepu | odmowa |
| Plik większy niż limit | odrzucenie przed parsowaniem |

### Ryzyka

Błędny eksport z Excela (średnik, przecinek dziesiętny, kodowanie; obsłużone i pokazane w podglądzie); przypadkowe nadpisanie cen (podgląd z różnicami „było → będzie”); wolny zapis dużych plików (kolejka); zaufanie do ilości (zgoda sklepu).

---

## 10. Opinie o sklepie po odbiorze zamówienia

### Wartość dla klienta i sklepu

Klient, który odebrał zamówienie, ocenia sklep (1-5 gwiazdek i krótki komentarz). Średnia i liczba opinii są widoczne przy ofertach, więc klient ma sygnał zaufania przy wyborze sklepu.

### Źródła danych

Własne zamówienia (`shop_orders`, stan Odebrane, zdarzenie odbioru w `shop_order_events`), nowa tabela opinii. Wszystkie dane są własnością Shops.

### Reguły

```
dodanie opinii: zamówienie w stanie „Odebrane”, klient = właściciel zamówienia,
                brak opinii dla tego zamówienia (UNIQUE), nie później niż N dni po odbiorze (ustawienie)
ocena: 1..5, komentarz do 500 znaków (opcjonalny)
publiczne: średnia i liczba pokazywane, gdy liczba opinii >= min_to_show (ustawienie); komentarze po moderacji
odpowiedź sklepu: jedna na opinię, bez usuwania opinii
ukrycie: tylko administrator, z powodem zapisanym w historii (obraźliwa, dane osobowe, nie dotyczy zakupu)
```

### Czego NIE mówimy

Nie wyświetlamy imienia ani nicku klienta („Klient, który odebrał zamówienie”). Nie ukrywamy opinii z powodu niskiej oceny. Nie pokazujemy średniej poniżej progu liczby opinii („Za mało opinii”). Opinie nie zmieniają kolejności ofert ani rekomendacji. Nie sugerujemy, że opinia jest oceną produktu z katalogu.

### Przepływ UI

- Szczegóły zamówienia `/zamowienia/{id}` po stanie Odebrane: przycisk „Oceń sklep” (strona z gwiazdkami i polem komentarza).
- Strona produktu `/katalog/{obszar}/{id}`, karta doradcy („Gdzie kupić”), koszyk (nagłówek grupy sklepu): „★ 4,6 (23)” obok nazwy sklepu; pod odnośnikiem „Zobacz opinie” lista komentarzy z datą i odpowiedzią sklepu. Pod listą objaśnienie, że opinie pochodzą wyłącznie od klientów, którzy odebrali zamówienie przez serwis.
- Panel sklepu: lista opinii o sklepie i pole odpowiedzi, przycisk „Zgłoś do administratora”.
- Admin: zasób „Opinie” (lista, ukrycie z powodem, zgłoszone przez sklepy).

### Model danych i właściciel

Moduł **Shops**. Tabela `shop_reviews`: `id`, `order_id` (UNIQUE, FK), `shop_id`, `customer_id` (null po pseudonimizacji), `rating` (1–5, `CHECK`), `comment` (null), `state` (`published`/`reported`/`hidden`), `hidden_reason` (null), `shop_reply` (null), `replied_at` (null), `created_at`. Akcje `SubmitReview`, `ReplyToReview`, `ReportReview`, `HideReview`. Kontrakt `Shops\Contracts\ShopRatings::forShops(ids)` zwracający `{średnia, liczba}`; DTO `PublicOffer` dostaje pola `shopRatingAvg`, `shopRatingCount` (puste, gdy poniżej progu). Pseudonimizacja zamówień (`PseudonymizeOrders`) zeruje `customer_id` w opinii; treść zostaje. Ustawienie `shops.reviews` (`window_days`, `min_to_show`, `max_length`).

### Powiadomienia i joby

Opcjonalna wiadomość dwa dni po odbiorze z prośbą o ocenę (temat `reviews`, jedna na zamówienie, dedup przez `shop_reviews.order_id` i znacznik wysyłki). Powiadomienie dla sklepu o nowej opinii (bez danych klienta).

### Dostęp

Pisanie: klient z dostępem do koszyka (`shop_cart`, Tank i Fishroom) i własnym odebranym zamówieniem. Czytanie: wszyscy (także gość). Odpowiedź: członek sklepu. Ukrycie: administrator. Właściciel i pracownicy sklepu nie oceniają własnego sklepu (już zablokowane kupno).

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Zamówienie Odebrane, pierwsza opinia | zapisana |
| Zamówienie w stanie Gotowe do odbioru | odmowa |
| Druga opinia do tego samego zamówienia | odmowa (`UNIQUE`) |
| Opinia cudzego zamówienia | odmowa |
| Po upływie okna dni | odmowa |
| Mniej niż `min_to_show` opinii | brak średniej w DTO |
| Po pseudonimizacji zamówienia | `customer_id = NULL`, treść bez zmian |
| Ukrycie bez powodu | odmowa; z powodem: opinia znika z publicznych list, wpis w historii |
| Odpowiedź sklepu dwa razy | druga odrzucona |

### Ryzyka

Fałszywe lub zamawiane opinie (tylko po odebranym zamówieniu, jedna na zamówienie, moderacja z powodem); wymogi prawa konsumenckiego dotyczące opinii (informacja, skąd pochodzą opinie, brak selektywnego usuwania negatywnych). Wymaga konsultacji prawnej (nie jest to porada prawna); sklepy z jednym zamówieniem nie mają średniej (próg); zależność od pseudonimizacji (opinia nie może przywracać tożsamości).

---

## 11. Promocje sklepu

### Wartość dla klienta i sklepu

Sklep ustawia cenę promocyjną z terminem. Klient widzi ją w wynikach doradcy, na stronie produktu i w koszyku; administrator może spiąć promocje w kampanie (np. „Start sezonu roślinnego”) z osobną stroną.

### Źródła danych

Oferty (`shop_offers`), nowa historia cen, kampanie z modułu Campaigns, `PublicOffers`. Cena efektywna zależy od czasu.

### Reguły

```
oferta ma: price_grosze (cena regularna), promo_price_grosze, promo_from, promo_to, campaign_id (opcjonalnie)
walidacja: promo_price < price, promo_from < promo_to, długość <= max (ustawienie), 
           jeśli kampania: okno promocji mieści się w oknie kampanii, obniżka >= minimum kampanii
cena efektywna(t) = promo_price, gdy promo_from <= t < promo_to; w przeciwnym razie price
„cena sprzed obniżki” = najniższa cena oferty z 30 dni przed promo_from (z historii cen); brak historii => bez ceny przekreślonej i bez procentu
cena zamówienia = cena efektywna w chwili złożenia koszyka (migawka); zatwierdzenie porównuje z ceną z historii
        z chwili złożenia, nie z bieżącą (zmiana ceny po złożeniu nie psuje zatwierdzenia)
```

Historia cen: tabela `shop_offer_price_history` zapisywana przy każdej zmianie ceny regularnej i przy starcie oferty. Reguła „najniższa cena z 30 dni przed obniżką” wynika z przepisów o informowaniu o cenach (konsultacja prawna, pytanie 11). Dopóki nie ma zatwierdzenia, serwis pokazuje samą cenę promocyjną i etykietę „Promocja”, bez przekreślonej ceny i procentu.

### Czego NIE mówimy

Nie piszemy „najniższa cena”, „oszczędzasz X zł” ani procentu bez historii cen zgodnej z regułą 30 dni. Promocja nie zmienia kolejności rekomendacji doradcy (kolejność wynika z dopasowania i jest niezależna od płatności); wyróżnienie to plakietka i sortowanie ofert tego samego rekordu po cenie efektywnej. Jeśli kiedyś pojawią się promocje płatne, muszą nosić oznaczenie „Oferta promowana” (poza zakresem).

### Przepływ UI

- Panel sklepu: w formularzu oferty (`OfferResource`) sekcja „Promocja”: cena promocyjna, od, do, kampania (lista aktywnych i nadchodzących, opcjonalna). Podgląd „Tak zobaczy to klient”.
- Doradca (karty ofert w rankingu i kreatorze), strona produktu, koszyk: plakietka „Promocja do dd.mm” i cena efektywna; koszyk wymaga akceptacji zmiany ceny, jak dziś, gdy cena zmieniła się po dodaniu (start lub koniec promocji).
- Strona `/promocje` (i `/promocje/{kampania}`): oferty objęte promocją w okolicy (miasto z konta), pobierane przez `PublicOffers`.
- Admin `/admin`: nowy zasób „Kampanie” (nazwa, opis, termin od-do, minimalna obniżka, tekst plakietki, stan, podgląd listy ofert) oraz ustawienia promocji.

### Model danych i właściciel

| Moduł | Zmiana |
|---|---|
| **Shops** | `shop_offers`: `promo_price_grosze` (null), `promo_from`, `promo_to` (null), `campaign_id` (null, bez klucza obcego; walidacja przez kontrakt Campaigns); tabela `shop_offer_price_history` (`offer_id`, `price_grosze`, `valid_from`, `valid_to` null); `PublicOffer` dostaje `regularPriceGrosze`, `promoEndsAt`, `lowestPriorGrosze`, pole `priceGrosze` oznacza cenę efektywną; akcje `SetOfferPromotion`, `EndOfferPromotion`; zmiany w `SaveOffer` (historia), `ApproveOrder` (porównanie z ceną z chwili złożenia), `ListCart`. |
| **Campaigns** (nowy) | tabela `campaigns` (`id`, `slug` unikalny, `name`, `description`, `starts_at`, `ends_at`, `min_discount_percent` null, `state`, `created_by`); kontrakt `Campaigns\Contracts\ActiveCampaigns::forDate(date)` zwraca DTO; zasób Filament w `/admin`; audyt przez activitylog. |
| **Settings** | `shops.promo` (`max_days`, `lookback_days` = 30, `show_reference_price` = wyłączone do decyzji), `campaigns.default_min_discount`. |

### Powiadomienia i joby

Brak powiadomień. Zadanie godzinowe `ExpireOffers` (istniejące) rozszerzone o zakończenie promocji nie jest potrzebne, bo cena efektywna zależy od czasu w odczycie (jak `valid_to`). Brak zmian w harmonogramie.

### Dostęp

Sklep: członek z prawem do ofert; w pilocie bez opłat. Kampanie: administrator. Klient: każdy (publiczne).

### Testy

| Przypadek | Oczekiwanie |
|---|---|
| Promocja 18,00 zł przy cenie 20,00 zł w oknie | `PublicOffers` zwraca 1800 gr jako cenę efektywną i 2000 gr jako regularną |
| Przed `promo_from` i po `promo_to` | cena regularna |
| Cena promocyjna ≥ regularnej | błąd walidacji |
| Okno promocji poza oknem kampanii | błąd walidacji |
| Zamówienie złożone w promocji, zatwierdzone po jej końcu | zatwierdzenie przechodzi (porównanie z ceną z chwili złożenia) |
| Cena zmieniona w sklepie po złożeniu zamówienia | zatwierdzenie dalej zgłasza zmianę warunków (jak dziś) dla ceny regularnej |
| Oferta bez historii 30 dni | brak ceny przekreślonej i procentu |
| Oferta z historią (cena 22, potem 20, promocja 18) | cena sprzed obniżki = najniższa z 30 dni = 20 zł |
| Ranking doradcy z promocją u sklepu A | kolejność rekordów bez zmian |

### Ryzyka

Zgodność z przepisami o cenach (historia 30 dni, pytanie 11); zmiana ceny efektywnej w trakcie koszyka (akceptacja zmiany jak dziś); zmiana `ApproveOrder` dotyka krytycznej ścieżki zamówień, wymaga pełnego zestawu testów zamówień; zaufanie do rankingu (zasada braku płatnego wpływu na kolejność).

---

## 12. Wspólne elementy techniczne

| Element | Opis | Używają |
|---|---|---|
| `users.city` | Opcjonalne miasto w koncie (ekran `/moje-konto`), znormalizowane do `city_key` jak `Catalog::fold`. Filtr „okoliczne sklepy” to dopasowanie miasta placówki do miasta użytkownika; promień i współrzędne poza zakresem (pytanie 10). | §3, §4, §8, §11 |
| Moduł Notifications | Tabela `notification_preferences` (`user_id`, `topic`, `email`), podpisany link wypisu, jedna klasa bazowa powiadomienia sprawdzająca preferencje w `via()`. Tematy: `availability`, `reminders`, `reviews`. Kanał push dochodzi z aplikacją mobilną bez zmiany wywołujących. | §4, §6, §10 |
| `FaunaRecord` i `FloraRecord` | Rozszerzone rekordy silnika (temperament, strefa, CO2). | §2, §3, §5 |
| `tank_equipment`, `tanks.volume_liters` | Tabele i kolumny tworzone raz, w pakiecie fundamentowym. | §2, §5, §6, §7 |
| Podział `TankShow` | Panel zbiornika dzielony na komponenty zakładek, żeby pakiety nie zmieniały tego samego pliku. | §2, §3, §6 |
| Pliki tras | Osobne pliki tras per funkcja ładowane jednym wpisem w konfiguracji tras. | wszystkie |
| Plik dowodów | Wspólny DTO `Evidence` (kolumna, wartość, id rekordu, partia bazy) i widok „Dlaczego?”. | §2, §3, §5 |

---

## 13. Dostęp i ustawienia w jednym miejscu

### Macierz dostępu (propozycja)

| Funkcja | Gość | Tank | Fishroom | Sklep | Admin |
|---|---|---|---|---|---|
| Ostrzeżenia przy dodawaniu gatunku, karta zgodności | — | tak (1 zbiornik) | tak (wiele) | — | podgląd |
| Podpowiedź z pomiaru | — | tak | tak | — | podgląd |
| „Daj znać, gdy będzie dostępne” | rejestracja | do 10 | do 100 | — | podgląd |
| Plan nowego akwarium | rejestracja | 1 plan | bez limitu | — | podgląd |
| Przypomnienia automatyczne i śledzenie preparatów | — | zaproszenie | tak | — | podgląd |
| Wyposażenie zbiornika | — | tak | tak | — | podgląd |
| Kalkulatory | tak (bez zapisu) | tak, z zapisem | tak, z zapisem | tak | tak |
| Statystyki popytu | — | — | — | tak (własna okolica) | globalnie |
| Import ofert z CSV | — | — | — | tak (prawo do ofert) | podgląd |
| Opinie: pisanie | — | tak (po odbiorze) | tak (po odbiorze) | — | — |
| Opinie: czytanie | tak | tak | tak | tak | tak |
| Opinie: odpowiedź, zgłoszenie | — | — | — | tak | moderacja |
| Promocje: ustawianie | — | — | — | tak | kampanie |
| Promocje: widok | tak | tak | tak | tak | tak |

Nowe wiersze `Feature`: `CompatibilityCheck` (Tank), `WaterHints` (Tank), `RecordWatch` (Tank), `TankPlan` (Tank), `ReminderSuggestions` (Fishroom), `Calculators` (Drop). Funkcje sklepu bramkuje `Shops\Access`, jak dotychczasowe.

### Nowe ustawienia (`SettingKey`, wersjonowane)

| Klucz | Zawartość | Wartość startowa |
|---|---|---|
| `aquarium.compat.severity` | ważność kontroli §2 | jak w tabeli reguł |
| `aquarium.compat.temperament` | kombinacje uruchamiające uwagę | ryba Agresywny/Terytorialny wobec ryby Spokojny |
| `aquarium.stocking.cm_per_liter` | próg oceny obciążenia | `null` (bez oceny) |
| `aquarium.calculators.limits` | zakresy wejść | rozsądne zakresy techniczne |
| `aquarium.calculators.co2` | współczynnik, progi oceny | współczynnik 3, progi `null` |
| `aquarium.plan.limit` | limit planów na poziom | Tank 1, Fishroom bez limitu |
| `aquarium.reminders.auto` | interwały podpowiedzi, wyprzedzenie dla preparatów | wszystkie `null` do decyzji |
| `advisor.water_hints.map` | mapa problem → produkt z dowodami | pozycje z §3 oznaczone jako gotowe |
| `advisor.water_hints.checks` | włączone kontrole | pH i temperatura |
| `advisor.water_hints.tolerance` | tolerancja odczytu (pH, °C) | 0 |
| `shops.watch` | limity i retencja obserwacji | Tank 10, Fishroom 100, 14 dni |
| `shops.reviews` | okno, próg widoczności, długość | 30 dni, 3 opinie, 500 znaków |
| `shops.promo` | maks. długość, okres odniesienia, pokazywanie ceny przed obniżką | 30 dni, 30 dni, wyłączone |
| `campaigns.default_min_discount` | minimalna obniżka kampanii | brak |
| `metrics.demand` | `k`, retencja, poziom regionu | 5, 400 dni, miasto |
| `shops.import.csv` | istniejące | bez zmian |

---

## 14. Pytania do właściciela (tylko te, bez których nie da się bezpiecznie dać porady)

### 14.1. Blokują funkcję (do decyzji przed wdrożeniem)

1. **Temperament.** Czy ostrzegać tylko: ryba „Agresywny/Terytorialny” obok ryby „Spokojny” (propozycja)? Co z parą „Agresywny” i „Umiarkowany” oraz „Umiarkowany” i „Spokojny”? Czy temperament skorupiaków i ślimaków ma wchodzić do reguł (etykieta „Agresywny” u `FN0153` oznacza zapewne drapieżnictwo wobec ślimaków)?
2. **Ważność kontroli.** Czy „zbiornik za mały według bazy” to uwaga (propozycja), czy konflikt? Czy reguła „dorosła ryba ≥ 3 pasma większa niż zbiornik” (z rankingu Tank) ma być konfliktem w obsadzie?
3. **Cm ryby na litr.** Czy włączamy w ogóle ocenę? Jeśli tak: jaki próg i jakie źródło go uzasadnia? Do czasu decyzji pokazujemy sam wynik, bez oceny. Jaką długość przyjmujemy dla „B. duża (>15 cm)” (domyślnie: wpisuje użytkownik)?
4. **Granice pasm.** Czy odczyty 21,5–22 i 25,5–26 °C traktujemy jako „w zakresie” (propozycja)? Czy stosujemy tolerancję odczytu pH i temperatury (np. testy kropelkowe mają błąd)? Jaką?
5. **Mapa problem → produkt.** Czy zatwierdzasz proponowaną mapę z §3 (w szczególności `CH0089` Acid Buffer dla „pH za wysokie”, mimo że jego opis wspomina o Alkaline Buffer spoza bazy)? Czy włączyć kontrole: amoniak/azotyny (`CH0048`, `CH0054`, `CH0055`) i fosforany (`CH0086`) — wymaga progów i źródła? Po jakim czasie pomiar uznajemy za nieświeży (`CH0102`, `CH0104`)? Czy poprawić regułę weryfikacji, która wskazuje „equilibrium” dla pH (opis `CH0090` mówi o twardości)?
6. **Teksty ogólne.** Czy dopuszczamy w poradach zdania nieoparte na rekordzie (np. „wykonaj podmianę wody”)? Jeśli tak, kto je pisze i zatwierdza (treść trafia do ustawień)?
7. **Wymagania sprzętowe.** Czy filtr i oświetlenie wolno nazywać „potrzebnymi” w planie, czy baza (która tego nie mówi) ma pozostać bez takich twierdzeń?
8. **Interwały przypomnień.** Jakie interwały (podmiana wody, serwis filtra, nawożenie, test wody) i jakie wyprzedzenie dla kończącego się preparatu zatwierdzasz? Do decyzji podpowiedzi proszą użytkownika o własny interwał.
9. **CO₂.** Czy współczynnik 3 w `CO₂ = 3 × KH × 10^(7 − pH)` jest akceptowalny (wzór przybliżony, bez korekty temperatury)? Czy pokazujemy oceny stężenia (np. 15–30 mg/l jako zakres używany w poradnikach roślinnych) i z jakim źródłem?
10. **Okolica.** Czy „okoliczne sklepy” to to samo miasto (propozycja na start), czy potrzebny promień/województwo? Czy dodajemy w koncie pole „Miasto” (dane osobowe do polityki prywatności)?
11. **Zasady kampanii i cen (konsultacja prawna).** Czy serwis ma pokazywać „cenę sprzed obniżki” według reguły najniższej ceny z 30 dni (wymaga historii cen), czy na start tylko cenę promocyjną z etykietą? Czy promocja musi należeć do kampanii? Czy cena promocyjna obowiązuje, gdy promocja skończyła się między złożeniem a zatwierdzeniem zamówienia (propozycja: tak)?
12. **Opinie.** Czy regulamin określa zasady moderacji (kiedy administrator ukrywa opinię)? Czy sklep może odpowiadać? Jakie okno (dni po odbiorze) i próg widoczności średniej?

### 14.3. Odpowiedzi przyjęte tymczasowo na start (do potwierdzenia przez właściciela)

Zasada: gdy brak decyzji, wybieramy wariant, który niczego nie twierdzi ponad dane. Każdą z tych wartości da się zmienić w ustawieniach.

1. Temperament: tylko uwaga (nie konflikt) dla ryby „Agresywny/Terytorialny” obok ryby „Spokojny”. Pozostałe pary i bezkręgowce bez komunikatu.
2. Zbiornik mniejszy niż w bazie dla gatunku: uwaga. Dorosła ryba co najmniej 3 pasma większa niż zbiornik: konflikt (ta sama reguła odrzuca gatunek w rankingu zbiornika).
3. Cm ryby na litr: kalkulator pokazuje wyłącznie wynik obliczenia, bez oceny i progu. Dla „B. duża (>15 cm)” długość wpisuje użytkownik.
4. Granice pasm: odczyt w przerwie między sąsiednimi pasmami (np. 21,5–22 °C) traktujemy jako w zakresie. Bez dodatkowej tolerancji; pokazujemy odczyt i zakres z bazy.
5. Pomiar → produkt: tylko `CH0089` dla „pH za wysokie” (z cytatem opisu) i sprzęt z kategorii Ogrzewanie dla „temperatura za niska”. Bez kontroli amoniaku, azotynów i fosforanów (brak progów w bazie). Informacja o starym pomiarze po 30 dniach (ustawienie). Reguła weryfikacji wskazująca „equilibrium” dla pH zostaje do przeglądu przez właściciela.
6. Żadnych ogólnych zdań porad nieopartych na rekordzie.
7. Plan akwarium nie nazywa sprzętu „potrzebnym”; pokazuje go jako propozycje do rozważenia.
8. Przypomnienia: bez domyślnych interwałów — użytkownik podaje własny; aplikacja tylko proponuje rodzaj przypomnienia.
9. CO₂: wynik opisany jako przybliżenie ze wzoru, bez oceny „za mało / za dużo”.
10. Okolica: to samo miasto. Pole „Miasto” w koncie jest opcjonalne; dopisać je do polityki prywatności.
11. Promocje: tylko cena promocyjna z etykietą „Promocja do …”, bez ceny przekreślonej i procentu (do czasu decyzji prawnej i historii cen). Promocja nie musi należeć do kampanii. Cena promocyjna obowiązuje, jeśli była ważna w chwili złożenia zamówienia.
12. Opinie: okno 30 dni po odbiorze, średnia widoczna od 3 opinii, administrator może ukryć opinię, bez odpowiedzi sklepu w pierwszej wersji.

### 14.2. Decyzje wygodne (przyjęliśmy wartości domyślne, nie blokują)

- Nazwa „Zgodność obsady” zamiast „zdrowie obsady” (wzmianka w §2): przyjęta.
- Limity obserwacji (10 / 100), planów (1 / bez limitu), próg widoczności statystyk `k = 5`, retencja liczników 400 dni, okno opinii 30 dni, próg średniej 3 opinie: ustawienia, zmienne w panelu admina.
- Import ofert: tylko plik CSV (bez pobierania z adresu arkusza); pobieranie z adresu wymaga osobnej decyzji ze względu na bezpieczeństwo.
- Statystyki popytu bezpłatne w pilocie; ewentualna płatna wersja po pilocie.
- Poprawki danych bazy, o które warto poprosić redakcję (nie blokują): ujednolicić „Nie”/„nie” w polu Zdalne_sterowanie sprzętu; dodać obszar karmy dla ślimaków w pielęgnacji; rozważyć dopisanie długości dorosłej ryby w cm i wymagań wody roślin.

---

## 15. Pakiety robocze, fale i kolizje

Zasada podziału: każdy pakiet to osobna gałąź z własnymi plikami; pliki wspólne (ustawienia, wiersze dostępu, trasy, rekordy silnika, tabele współdzielone, komponenty zakładek) przygotowuje pierwszy pakiet, żeby kolejne mogły iść równolegle bez konfliktów. Migracje mają unikalne znaczniki czasu rezerwowane per pakiet. Przed oddaniem każdego pakietu `composer check`.

### Pakiety

| Pakiet | Zakres | Pliki i obszary | Zależy od | Może kolidować z |
|---|---|---|---|---|
| **Fundament nowych funkcji** | Wszystkie nowe `SettingKey` z §13 (definicje i wartości startowe), nowe wiersze `Feature` (i `minTier`), pliki tras per funkcja ładowane jednym wpisem, `Evidence` i widok „Dlaczego?”, rozszerzenie `FaunaRecord` (temperament, strefa) i nowy `FloraRecord` w `FaunaPool`/odpowiedniku, migracje `tanks.volume_liters`, `tank_equipment`, `users.city`, pole „Miasto” w koncie, podział `TankShow` na komponenty zakładek, pola `PublicOffer` (promo, ocena) puste | `app/Domain/Settings/SettingKey.php`, `app/Domain/IdentityAccess/Models/Feature.php`, `routes/*.php`, `bootstrap/app.php`, `app/Domain/Advisor/Engine/FaunaRecord.php`, `app/Domain/Advisor/FaunaPool.php`, `app/Livewire/Aquarium/TankShow.php`, widok konta, migracje | — | Wszystkie pakiety (dlatego idzie pierwszy i sam) |
| **Zgodność obsady** | Silnik reguł, kontrakt `Compatibility`, akcja `CheckLivestockFit`, okno ostrzeżeń przy dodawaniu (3 miejsca), karta zgodności w panelu zbiornika, plakietka w rankingu Tank | `app/Domain/Advisor/Engine/Compatibility/*`, `app/Domain/Advisor/Contracts/Compatibility.php`, `app/Domain/Aquarium/Actions/CheckLivestockFit.php`, `TankLivestock` i jego widok, `AddToTankController`, `TankAdvisorController`, widok strony produktu, testy i fixture | Fundament | Podpowiedź z pomiaru (czyta wymagania obsady; kolizja tylko jeśli powstaną równolegle), Plan (używa `Compatibility`) |
| **Import ofert z CSV** | Podgląd i zapis pliku, kolejka, strona w panelu sklepu | `app/Domain/Shops/Actions/*OfferImport*`, `app/Domain/Shops/Models/ImportBatch.php`, strona Filament w `app/Filament/Shop/Pages`, migracja `shop_import_batches`, testy | Fundament | Promocje (obie używają `SaveOffer`; ten pakiet go nie zmienia, więc kolizja tylko przy równoczesnej zmianie `SaveOffer`) |
| **Kalkulatory** | Cztery czyste klasy i akcje, strony i trasy, zapis litrażu | `app/Domain/Aquarium/Calculators/*`, akcje `Calculate*`/`EstimateCo2`, `app/Livewire/Calculators/*`, widoki, plik tras kalkulatorów, testy | Fundament | Brak (własne pliki) |
| **Podpowiedź z pomiaru** | Silnik `WaterHints`, akcja `BuildWaterHints`, karta w `TankMeasurements` i na ekranie zbiornika, kontrola mapy przy aktywacji partii | `app/Domain/Advisor/Engine/WaterHints/*`, `app/Domain/Aquarium/Actions/BuildWaterHints.php`, `TankMeasurements`, `ActivateBatch` (jedna dodana kontrola), testy | Zgodność obsady (wymagania obsady) | Zgodność obsady (ten sam widok zbiornika, ale osobny komponent po podziale) |
| **Promocje i kampanie** | Kolumny ofert, historia cen, cena efektywna, `ApproveOrder`/`ListCart`, moduł Campaigns, zasób admina, strona `/promocje`, plakietki | `app/Domain/Shops/Models/Offer.php`, `SaveOffer`, `ApproveOrder`, `ListCart`, `Contracts/PublicOffers.php`, `Data/PublicOffer.php`, `OfferResource` (panel sklepu), `app/Domain/Campaigns/*`, `app/Filament/Resources/Campaigns/*`, widoki kart ofert, migracje | Fundament, Import CSV (żeby nie zmieniać `SaveOffer` jednocześnie) | Opinie (oba zmieniają `PublicOffers` i DTO), Import CSV (`SaveOffer`) |
| **Powiadomienia i obserwowanie rekordów** | Moduł Notifications (preferencje, wypisanie, klasa bazowa), tabela obserwacji, akcje, job co 15 minut, przycisk na stronie produktu i w kartach, lista obserwacji w koncie | `app/Domain/Notifications/*`, `app/Domain/Shops/Actions/*Watch*`, `app/Domain/Shops/Models/RecordWatch.php`, `routes/console.php` (jedna linia harmonogramu), widok strony produktu (przycisk), strona konta (lista), migracje | Fundament | Zgodność obsady (widok strony produktu) — kolejność: Zgodność wcześniej; Promocje (karty ofert) |
| **Plan nowego akwarium i lista zakupów** | Tabele planów, kreator pięciu kroków, rekomendacje z danych, `BuildShoppingList`, `SplitShoppingList` (miejsce na optymalizator), konwersja do zbiornika | `app/Domain/Aquarium/Actions/*Plan*`, `Models/TankPlan*.php`, `app/Domain/Advisor/Engine/Setup/*`, `app/Livewire/Aquarium/Plan*`, widoki i trasy planów, migracje `tank_plans`, `tank_plan_items` | Zgodność obsady, Kalkulatory (litraż), Fundament | Brak własnych kolizji (nowe pliki); dotyka koszyka tylko przez `ChangeCart` |
| **Przypomnienia automatyczne i wyposażenie** | Zakładka wyposażenia i preparatów, `tank_consumables`, podpowiedzi przypomnień, job wysyłki, dedup | `app/Domain/Aquarium/Actions/*Reminder*`, `SaveEquipment`, `SaveConsumable`, `app/Livewire/Aquarium/TankEquipment.php`, `TankReminders`, migracje, testy | Powiadomienia (klasa bazowa), Fundament (`tank_equipment`, `volume_liters`), Kalkulatory (dawkowanie jako link) | Plan (używa `tank_equipment`, ale tabela jest z Fundamentu) |
| **Opinie o sklepie** | Tabela i akcje opinii, kontrakt ocen, wyświetlanie przy ofertach, zasób admina, panel sklepu | `app/Domain/Shops/Actions/*Review*`, `Models/Review.php`, `Contracts/ShopRatings.php`, `PublicOffers.php` (wypełnienie pól oceny), widoki zamówień klienta, `PseudonymizeOrders`, zasoby Filament | Fundament, Promocje (kolejność zmian w `PublicOffers`) | Promocje (`PublicOffers`, DTO, widoki kart) |
| **Statystyki popytu dla sklepów** | Moduł Metrics, `RecordDemand` w kontrolerach, strona „Popyt w okolicy”, job retencji | `app/Domain/Metrics/*`, wywołania w `CatalogController`, kontrolerach doradcy i koszyka, strona w `app/Filament/Shop/Pages`, migracja, testy | Zgodność obsady i Obserwowanie (kontrolery już zmienione), Fundament (`city`) | Kontrolery doradcy i strony produktu (zmieniane wcześniej przez inne pakiety), dlatego idzie na końcu |

### Fale

| Fala | Pakiety równolegle | Dlaczego ta kolejność | Wspólne pliki w fali |
|---|---|---|---|
| 0 | Fundament nowych funkcji (sam) | Zdejmuje z pozostałych edycje plików wspólnych | — |
| 1 | Zgodność obsady, Import ofert z CSV, Kalkulatory | Trzy rozłączne obszary: widok zbiornika i silnik, panel sklepu, strony kalkulatorów | brak |
| 2 | Podpowiedź z pomiaru, Promocje i kampanie, Powiadomienia i obserwowanie | Pomiar korzysta ze Zgodności; Promocje zmieniają `SaveOffer` po Imporcie; Powiadomienia nie dotykają ofert ani widoku zbiornika (jedna linia w harmonogramie) | brak (rozłączne: Advisor/Aquarium pomiar, Shops oferty/Campaigns, Notifications/Shops obserwacje); drobna zbieżność: widok strony produktu (Powiadomienia dodają przycisk, Promocje plakietkę), rozwiązywana kolejnością scalania |
| 3 | Plan nowego akwarium, Przypomnienia automatyczne, Opinie o sklepie | Plan potrzebuje Zgodności i Kalkulatorów; Przypomnienia potrzebują Powiadomień; Opinie po Promocjach (wspólny `PublicOffers`) | brak (Aquarium plany, Aquarium przypomnienia, Shops opinie); Plan i Przypomnienia dotykają różnych komponentów po podziale zakładek |
| 4 | Statystyki popytu (sam) | Wymaga stabilnych kontrolerów, obserwacji i miasta; potrzebuje ruchu, żeby dane miały sens | — |

Każda fala daje użytkownikowi wartość bez kolejnej: po fali 1 działają ostrzeżenia, kalkulatory i import; po fali 2 podpowiedzi z pomiaru, promocje i obserwowanie; po fali 3 plan, przypomnienia i opinie; fala 4 uzupełnia sklepy o popyt.

### Uwagi do wykonania

- Wszystkie reguły zgodności i podpowiedzi są w czystych klasach silnika i akcjach domenowych (bez Eloquent i Filament w silniku, kontrola architektury obejmuje nowe katalogi), więc ekrany i przyszłe API aplikacji mobilnej wołają te same operacje.
- Nowy test spójności z danymi: po każdej aktywacji partii bazy sprawdzamy dowody mapy podpowiedzi (§3) oraz to, że listy używane w regułach (np. wartości Temperatury, pH, Temperamentu) zawierają wyłącznie znane etykiety. Nieznana etykieta daje „brak danych”, nie błąd użytkownika.
- Do testów używamy fixture `tests/Fixtures/KnowledgeBase` (kopia referencyjna 200/194/108/110 rekordów), a przypadki z identyfikatorami z tego dokumentu są złotymi testami w `tests/Golden`.
