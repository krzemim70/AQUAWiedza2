# Kierunki rozwoju: pełne rekomendacje, utrzymanie klientów, aplikacja i urządzenia

Stan: pomysły i wstępna analiza (listopad 2026), do decyzji właściciela. Nic z tego dokumentu nie jest jeszcze wdrożone, chyba że zaznaczono inaczej. Obowiązuje twarda zasada: żadnej porady bez podstawy w bazie wiedzy albo w znanym, opisanym wzorze.

## 1. Co trzeba dodać, żeby rekomendacje produktów były pełne

Dziś po pomiarze polecamy tylko preparat obniżający pH (Acid Buffer, CH0089) i tylko przy spełnieniu ścisłych warunków. Ograniczeniem są dane, nie kod.

### Dane w bazie wiedzy (praca redakcji)

| Obszar | Czego brakuje |
|---|---|
| Preparaty | Parametr, kierunek i wielkość zmiany na dawkę z etykiety (np. „10 ml na 100 l podnosi KH o 1°dKH”), przeciwwskazania (np. krewetki, ślimaki), woda słodka/morska, pojemność opakowania i liczba dawek, kod kreskowy |
| Gatunki fauny | Liczbowe zakresy KH, GH, azotanów, fosforanów (dziś są tylko pasma pH i temperatury) |
| Rośliny | Wymagania wody i nawożenia w liczbach |
| Sprzęt | Moc, zakres litrów, przepływ, typ (pozwoli dobrać np. grzałkę do zbiornika) |
| Zasady opieki | Rekordy ze źródłem, np. maksymalna zmiana pH lub KH na dobę, odstęp między dawkami, kolejność korekt; dzięki nim silnik może podać cel i tempo korekty bez zgadywania |

### Logika

- Cel korekty liczony ze wspólnego zakresu obsady i pokazany jako wzór.
- Dawka wyłącznie z etykiety (kalkulator dawkowania już to robi).
- Ostrzeżenia o zależnościach parametrów (np. obniżenie KH zmienia pH i CO₂).
- Ocena trendu z kilku pomiarów zamiast pojedynczego odczytu; uwzględnienie dokładności testu (paski / testy kropelkowe / miernik).
- Zamienniki o tym samym działaniu, gdy w okolicy nie ma danego produktu.

### Pętla zwrotna

Użytkownik zaznacza „zastosowałem”, kolejny pomiar pokazuje efekt. Buduje to zaufanie i zbiera dane o skuteczności na przyszłość.

### Panel redakcji

Formularz pól strukturalnych w panelu admina, z wersjonowaniem partii bazy (już istnieje) i kontrolą zdań dowodowych. Później: wspomagane wyciąganie danych z kart producentów.

## 2. Funkcje, które przyciągną i utrzymają klientów

### Przyciąganie (wyszukiwarki i darmowe narzędzia)

- **Publiczny atlas gatunków**: strona każdego gatunku (około 200 opisów fauny) z sekcją „z kim może mieszkać”, zdjęciem i ofertami z okolicy.
- **„Czy te ryby mogą być razem?”** bez konta, z linkiem do udostępnienia.
- **„Moje pierwsze akwarium”**: plan, lista zakupów, prowadzenie przez dojrzewanie zbiornika.
- Kalkulatory (już są) i szybki dobór (już jest) jako wejście do serwisu.

### Utrzymanie

- **Asystent dojrzewania zbiornika**: pomiary amoniaku i azotynów krok po kroku (wymaga rekordów zasad w bazie).
- **Dziennik akwarium**: zdjęcia, oś czasu, wykresy pomiarów.
- **Cotygodniowe podsumowanie** (e-mail lub powiadomienie): zaplanowane prace, kończące się preparaty, nowe oferty obserwowanych rekordów.
- **Zapasy i ponowne zamówienie jednym kliknięciem** w tym samym sklepie.
- **Wspólne zbiorniki w rodzinie**: przypomnienia dla kilku osób.
- **Tryb urlopowy**: plan karmienia i opieki dla zastępcy.
- **Lojalność ze sklepami**: kupony lokalnych sklepów dla aktywnych użytkowników (sklepy płacą za ruch).
- Ostrożnie: poradnik objawów tylko jako „możliwe przyczyny opisane w bazie”, bez diagnoz, po konsultacji prawnej.
- Odłożone wcześniej: publiczny profil akwarium („może kiedyś”).

### Aplikacja mobilna jako asystent akwarysty

- Najpierw API (Sanctum; akcje domenowe są gotowe), potem **PWA** z powiadomieniami push (Android i iOS z ekranu początkowego), natywna aplikacja dopiero, gdy potrzebny będzie Bluetooth do urządzeń.
- Ekrany: szybki pomiar (duże pola, ostatnie wartości), powiadomienia o pracach, skaner kodu kreskowego (preparat lub sprzęt do zbiornika i zapasów), tryb offline.
- Później: odczyt paska testowego ze zdjęcia (dokładność do sprawdzenia), inteligentny asystent odpowiadający wyłącznie na podstawie bazy wiedzy, z cytatem rekordu; poza bazą mówi „nie wiem”.

## 3. Kolejność wdrażania

1. **Teraz (tanio)**
   - wirtualne zapasy i ponowne zamówienia (użytkownik podaje pojemność i dawkowanie z etykiety; system odejmuje zużycie i podpowiada „zostało około 2 tygodni” z ofertami z okolicy; karma tak samo: porcja × liczba karmień),
   - pola strukturalne preparatów w bazie i panel redakcji,
   - trendy pomiarów,
   - PWA z powiadomieniami push.
2. **Następnie**: API dla aplikacji, odbiornik danych z urządzeń (webhook lub MQTT, Home Assistant, plik CSV) oraz integracja z Tuya i ewentualnie Seneye.
3. **Na końcu**: własny sprzęt pod naszą marką. Najpierw krótka ankieta w serwisie „czy kupił(a)byś czujnik lub dozownik?”, pilotaż na małej serii, decyzja na podstawie danych.

Wspólny model biznesowy: znajomość stanu zbiornika i zapasów → trafne przypomnienia zakupowe → sprzedaż w sklepach partnerskich.

## 4. Urządzenia — wstępna analiza

### Co chcemy mierzyć i po co

| Dane | Źródło | Do czego |
|---|---|---|
| Temperatura | czujnik DS18B20 (tani, dokładny) | porównanie z zakresem obsady, alarm awarii grzałki |
| pH, przewodność/TDS | sonda pH (kalibracja, wymiana co 1–2 lata), sonda EC | trendy, warunki polecenia preparatu |
| Pobór prądu filtra i grzałki | gniazdko z pomiarem mocy | wykrycie zatrzymania filtra lub grzałki |
| Podane dawki nawozu, porcje karmy | dozownik, karmnik | dokładny stan zapasów i ponowne zamówienie |
| Poziom wody | czujnik poziomu | parowanie, dolewka |

### Gotowe ekosystemy (integracja)

| Ekosystem | Jak dostajemy dane | Ocena |
|---|---|---|
| **Shelly** (przekaźniki i gniazdka z pomiarem mocy, dodatek do czujników DS18B20) | lokalne API, MQTT do własnego serwera, wychodzące połączenie WebSocket | **Najlepszy kandydat do bezpośredniego połączenia bez chmury producenta**: urządzenie samo wysyła dane do nas. Brak pH. |
| **Tuya** (gotowe mierniki jakości wody Wi-Fi: pH, EC, TDS, ORP, temperatura; wielu dostawców z usługą OEM) | przez chmurę Tuya (API chmurowe, kolejka komunikatów) | Szeroki wybór tanich, gotowych urządzeń; dane płyną przez chmurę Tuya |
| **Seneye** | API producenta | Monitor wody (pH, NH₃, temperatura) dla akwarystów; warunki API do sprawdzenia |
| **EHEIM digital, Chihiros, Juwel, Kamoer** | API głównie lokalne lub nieoficjalne | Popularne w akwarystyce słodkowodnej; stabilność integracji niepewna |
| **Home Assistant** | nasz odbiornik danych (webhook/MQTT) | Jedna integracja obsługuje entuzjastów z dowolnym sprzętem |

### Czy urządzenia Tuya mogą łączyć się z naszą aplikacją zamiast z aplikacją producenta

| Wariant | Co widzi użytkownik | Gdzie płyną dane | Koszt i uwagi |
|---|---|---|---|
| **Aplikacja OEM Tuya** (gotowa aplikacja pod naszą marką) | naszą aplikację | chmura Tuya → nasz serwer przez API i kolejkę komunikatów | szybko, mało pracy, ograniczona elastyczność |
| **Zestaw SDK aplikacji Tuya** (własna aplikacja z parowaniem urządzeń Tuya) | naszą aplikację, nasze konta | chmura Tuya → nasz serwer | tygodnie lub miesiące pracy; pełna kontrola nad wyglądem i funkcjami |
| **Chmura prywatna Tuya** | naszą aplikację | **bezpośrednio na nasz serwer** (nowe oprogramowanie urządzeń) | rozwiązanie korporacyjne, projekt prowadzony z Tuya, wysoki koszt |
| Lokalna komunikacja lub wgranie własnego oprogramowania | — | sieć lokalna / nasz serwer | tylko dla hobbystów; nie do produktu (gwarancja, certyfikaty) |

Wniosek: przy standardowych urządzeniach Tuya użytkownik może widzieć wyłącznie naszą aplikację, ale dane przechodzą przez chmurę Tuya. Pełne bezpośrednie połączenie z naszym serwerem dają: chmura prywatna Tuya (drogo), urządzenia z otwartym protokołem (np. Shelly) albo urządzenie od producenta kontraktowego na module ESP32 z naszym oprogramowaniem wysyłającym dane przez MQTT/HTTPS.

### Własna linia pod naszą marką — propozycje wejścia

1. **Zestaw „Aikwarium Kontrola” na gotowych urządzeniach z otwartym protokołem**: gniazdko z pomiarem mocy (filtr, grzałka) i czujnik temperatury, skonfigurowane do wysyłania danych do nas. Najmniejsze ryzyko, bez własnej certyfikacji (sprzedajemy gotowe, certyfikowane urządzenia; ewentualna współpraca partnerska z producentem — do sprawdzenia).
2. **Miernik jakości wody pod naszą marką** od dostawcy Tuya z usługą OEM (pH, EC, TDS, temperatura) + aplikacja OEM lub SDK; dane przez chmurę Tuya do nas. Szybkie wejście w pH.
3. **Dozownik nawozów** (gotowy, z aplikacją lub API) — najcenniejszy dla zapasów: wie, ile podał.
4. **Docelowo**: urządzenie od producenta kontraktowego (np. moduł ESP32, nasze oprogramowanie, bezpośrednio do naszego serwera) — po pilotażu i ankiecie.

### Obowiązki przy sprzedaży sprzętu pod własną marką

Certyfikacja CE (urządzenia radiowe, kompatybilność elektromagnetyczna, niskie napięcie — zasilacz certyfikowany upraszcza), bezpieczeństwo produktu, rejestracja w systemie zużytego sprzętu elektronicznego, od 2027 r. obowiązki z unijnego aktu o cyberodporności dla urządzeń podłączonych do sieci (aktualizacje, obsługa podatności), gwarancja i serwis, kapitał w magazynie, dane z urządzeń objęte RODO. Przy odsprzedaży gotowych urządzeń pod własną marką część obowiązków producenta przechodzi na nas — do potwierdzenia prawnie.

### Matter, Thread, Zigbee i własna bramka

Pomysł: zamiast jednego producenta obsługujemy standardy, a sami przygotowujemy tylko bramkę „Aikwarium” w domu użytkownika, która zbiera dane lokalnie i wysyła je na nasz serwer.

| Standard | Co daje | Czego potrzebuje | Uwagi |
|---|---|---|---|
| **Matter** (Wi-Fi lub Thread) | wspólny standard Apple, Google, Amazon; jedno urządzenie może jednocześnie należeć do kilku systemów (np. Google Home i nasza bramka) | kontroler w sieci lokalnej (nasza bramka) | Matter nie ma typu urządzenia do pH ani jakości wody (są czujniki wycieku, zawory); pH wymagałoby własnego rozszerzenia producenta, którego inne systemy nie pokażą |
| **Thread** | sieć bateryjnych czujników o niskim poborze | router brzegowy (wielu użytkowników ma go w Apple TV, HomePod, Nest Hub) albo radio w naszej bramce | transport dla urządzeń Matter |
| **Zigbee** | ogromny wybór tanich czujników, dojrzałe oprogramowanie (Zigbee2MQTT) | koordynator Zigbee (radio w naszej bramce) | istnieją czujniki jakości wody z pH na Zigbee (np. modele Tuya YK-S03, BLE-YL01), obsługa zależy od konkretnego identyfikatora producenta; są projektowane do basenów |

**Bramka (propozycja):** mały komputer z radiem Zigbee i Thread (lub układ ESP32-C6, który ma Wi-Fi, Bluetooth i radio Zigbee/Thread), z otwartym oprogramowaniem: Zigbee2MQTT, kontroler Matter i nasz moduł wysyłający dane przez HTTPS lub MQTT. Alternatywa bez własnego sprzętu: integracja z Home Assistant, które entuzjaści już mają.

### Propozycje urządzeń (do weryfikacji dostępności w Polsce)

| Zadanie | Urządzenie | Łączność |
|---|---|---|
| Praca filtra i grzałki (pomiar mocy) | Shelly Plug S Gen4 / listwa Shelly Power Strip 4 Gen4 (pomiar na każde gniazdo) | Wi-Fi + Matter + Zigbee, MQTT do naszego serwera |
| Temperatura wody | Shelly 1 Gen4 lub Plus z dodatkiem do czujników i sondą DS18B20 (wodoodporna) | Wi-Fi, MQTT, lokalne API |
| Wyciek pod zbiornikiem | czujniki zalania Zigbee lub Matter (np. Aqara, Sonoff, Eve Water Guard) | Zigbee / Thread |
| Poziom wody | wyłącznik pływakowy podłączony do wejścia Shelly lub czujnika otwarcia Zigbee | Wi-Fi / Zigbee |
| Oświetlenie | sterowanie przez gniazdko lub ściemniacz Shelly, zapis fotoperiodu | Wi-Fi / Matter |
| Dozowanie nawozów | dozowniki z aplikacją (np. Kamoer, Chihiros, Jebao) | API producentów, do sprawdzenia |

### Pomiar pH

pH w akwarium to najtrudniejszy pomiar ciągły: sonda wymaga kalibracji (bufory 4 i 7) mniej więcej co miesiąc, wymiany co 1–2 lata, izolacji galwanicznej (zakłócenia od pomp i grzałek) i konstrukcji do stałego zanurzenia.

| Opcja | Zalety | Wady | Ocena |
|---|---|---|---|
| **Atlas Scientific (moduł EZO-pH, sonda laboratoryjna, izolowane wejście) na ESP32** — gotowe zestawy Wi-Fi do basenów i hydroponiki | profesjonalna dokładność (rozdzielczość 0,01), izolacja, obsługa w ESPHome, łatwo wysłać dane do nas | wyższa cena; zestawy nie są przeznaczone wprost do akwarium | **najlepsza podstawa własnego czujnika pH** (pilotaż, potem urządzenie pod naszą marką) |
| DFRobot Gravity (sonda analogowa) | tani, dobry do prototypu | mniejsza dokładność i stabilność | prototyp |
| Mierniki Wi-Fi Tuya (pH, EC, TDS, temperatura) | tanie, gotowe, dostawcy z usługą OEM | dane przez chmurę Tuya, jakość sond zmienna | szybki pilotaż z rynku |
| Mierniki Zigbee/Bluetooth Tuya (np. YK-S03, BLE-YL01) | lokalnie przez naszą bramkę (Zigbee2MQTT) | projektowane do basenów, zgłaszane problemy z kalibracją i odczytem w niższej temperaturze | do testów |
| Seneye | rozwiązanie akwarystyczne z API | wymienne elementy pomiarowe, warunki API do sprawdzenia | partnerstwo |
| Kontrolery profesjonalne (Neptune Apex, GHL) | najwyższa klasa | drogie, głównie akwarystyka morska | tylko integracja |

Rekomendacja: temperatura, prąd, wyciek i poziom — gotowe urządzenia Shelly i Zigbee/Matter przez bramkę lub bezpośrednio przez MQTT; pH — własny czujnik oparty na module Atlas Scientific i ESP32 wysyłający dane prosto do nas (Wi-Fi/MQTT), bo żaden standard nie przenosi pH między systemami.

### Do sprawdzenia przed decyzją

- Warunki i cennik aplikacji OEM i SDK Tuya, limity API, kolejka komunikatów.
- Certyfikaty i historia dostawców mierników Tuya z usługą OEM (np. ogłoszenia na Alibaba; brak niezależnej weryfikacji).
- Warunki API Seneye i program partnerski Shelly.
- Dokładność i trwałość tanich sond pH i EC w akwarium.
- Ankieta zainteresowania wśród użytkowników.

### Źródła wstępnej analizy

- Tuya — aplikacja OEM: https://www.tuya.com/platform/appdev/oemapp
- Tuya — SDK aplikacji: https://tuya.com/platform/appdev/app-sdk
- Tuya — API chmurowe: https://developer.tuya.com/en/docs/cloud/api-overview
- Tuya — chmura prywatna: https://developer.tuya.com/en/private-cloud
- Shelly — dodatek do czujników (API): https://shelly-api-docs.shelly.cloud/gen2/Addons/ShellySensorAddon
- Shelly — dodatek do czujników (produkt): https://shelly.com/products/shelly-plus-add-on
- Przykład miernika Tuya z usługą OEM: https://fjhedao.en.alibaba.com/product/1601111880082-213806299/PH_W2823EU_Tuya_Online_wifi_Digital_PH_Ec_Controller_3_in_1_water_quality_controller_for_Hydroponics_Aquariums.html
- Lokalna komunikacja z Tuya (projekt społeczności): https://github.com/make-all/tuya-local
- Matter 1.3 (czujniki wycieku, zawory): https://csa-iot.org/newsroom/matter-1-3-specification-released/
- Zigbee2MQTT — miernik YK-S03: https://www.zigbee2mqtt.io/devices/YK-S03.html
- Zigbee2MQTT — miernik BLE-YL01: https://www.zigbee2mqtt.io/devices/BLE-YL01.html
- Atlas Scientific — zestaw Wi-Fi do basenów: https://atlas-scientific.com/kits/wi-fi-pool-kit/
- Atlas Scientific — zestaw Wi-Fi do hydroponiki: https://atlas-scientific.com/kits/wi-fi-hydroponics-kit/
- Shelly — urządzenia Matter: https://www.shelly.com/collections/matter
- Shelly Power Strip 4 Gen4: https://shelly.com/products/shelly-power-strip-4-gen4
