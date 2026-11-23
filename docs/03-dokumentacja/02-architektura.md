# Architektura

Aplikacja Laravel udostępnia publiczne widoki Blade, strefę akwarysty Blade/Livewire pod `/moje-akwarium` i panele Filament pod `/admin` oraz `/panel-sklepu`. Kod domenowy mieści się w `app/Domain`.

| Moduł | Odpowiedzialność |
|---|---|
| `IdentityAccess` | Rejestracja, aktywacja, zgody, członkostwo jako warunek dostępu, poziomy Drop/Tank/Fishroom, eksport i usuwanie konta. |
| `KnowledgeBase` | Walidacja i import partii, publikacja aktywnego katalogu, opisy i zdjęcia rekordów. |
| `Advisor` | Dobór gościa, ranking zbiornika, kreator, wyszukiwanie i weryfikacja rekordów. |
| `Aquarium` | Zbiorniki, obsada, wyposażenie, pomiary, dziennik, przypomnienia, plany i obliczenia. |
| `Shops` | Placówki, członkowie, aktywacja sklepów, oferty, historia cen i stanów, import CSV, koszyki, zamówienia, raporty, obserwacje, opinie i lokalny popyt. |
| `Notifications` | Preferencje i odczyt danych powiadomień na potrzeby konta. Powiadomienia o koncie i sklepach leżą przy odpowiednich modułach. |
| `Settings` | Definicje, walidacja, odczyt i wersjonowanie ustawień oraz audyt zmian. |

## Granice modułów i akcje

- Reguła projektu przewiduje operacje na własnych tabelach i wymianę danych przez `Contracts` lub zdarzenia. Kontrolery, komponenty Livewire i akcje Filament delegują operacje domenowe. Akcje nazywają się `VerbNoun` i wystawiają `handle(...)`. Typowane DTO przenoszą dane niezależnie od HTTP.
- `KnowledgeBase\Contracts\Catalog` udostępnia aktywną partię, rekordy i etykiety. Doradca buduje z nich wejście do silnika, akwarium wykorzystuje je m.in. w planach i przypomnieniach, a sklepy przy ofertach i imporcie.
- `Advisor\Contracts\Compatibility` udostępnia akwarium ocenę katalogową obsady i dane do porównania pomiarów. `Shops\Contracts\PublicOffers` udostępnia oferty m.in. na kartach katalogu i listach zakupów. `WatchCounts` agreguje obserwacje do widoku popytu.
- `IdentityAccess\Contracts\Accounts` służy m.in. do odczytu miasta obserwujących. Kontrakty `AccountData` z modułów Aquarium, Shops i Notifications składają dane eksportu konta. `Aquarium\Contracts\OwnTanks` udostępnia odczyt własnych zbiorników.
- Uprawnienia do zapisu sprawdzają polityki, bramki lub klasy dostępu domenowego. Zapisy wieloetapowe stosują transakcje, a skutki zewnętrzne zależne od zapisu są przekazywane po zatwierdzeniu. Przykładowo `SettingChanged` implementuje `ShouldDispatchAfterCommit`, a raporty dobowe deduplikuje klucz sklepu i daty.

## Silnik doradcy

`Advisor\Engine` to czysty PHP. Dostaje rekordy, profil albo odpowiedzi i reguły przez argumenty. Zawiera silniki doboru, pytania kreatora, punktację, ranking, weryfikację oraz porównania obsady i pomiarów. Odczyt katalogu, sesja i składanie widoku są poza silnikiem. Dowód katalogowy może wskazywać rekord, pole i partię danych.

## Dostęp, zgody i ustawienia

- `IdentityAccess\Models\Feature` definiuje funkcje i minimalne poziomy. `ResolveAccess` zwraca decyzję i komunikat, uwzględnia m.in. zawieszenie, role panelowe, konfigurację kreatora i limit zbiorników. Trasy korzystają z `access:<funkcja>`, panele pytają o decyzję przy wejściu, a dostęp do obiektu dodatkowo zależy od właściciela lub sklepu.
- `auth` sprawdza sesję, a `consents` kieruje na `/zgody`, gdy brakuje akceptacji bieżącej wersji regulaminu, polityki prywatności lub oświadczenia o pełnoletności. Zmiana `identity.consents.version` wymusza ponowną akceptację na trasach objętych bramką. Zgoda newsletterowa jest opcjonalna i odwoływalna.
- `SettingKey` definiuje klucze, wartości domyślne i walidację. `Settings` wybiera najnowszą wersję z `effective_from` przypadającym w chwili odczytu lub wcześniej, a w razie jej braku zwraca wartość domyślną. `/admin/ustawienia` pokazuje historię i zapisuje powód, autora oraz czas obowiązywania nowej wersji.

## Rozszerzenia i testy

| Miejsce | Konwencja |
|---|---|
| `routes/features/*.php` | `routes/web.php` automatycznie dołącza pliki tras poszczególnych funkcji. |
| `resources/tank-tabs/*.php` | Pliki w kolejności nazw zwracają wpisy postaci `klucz => [etykieta, Feature, komponent Livewire]`. Komponent dostaje `tank-id`, a zawartość zablokowanej zakładki się nie ładuje. |
| `tests/Architecture/ArchTest.php` | Presety PHP i security. Testy pilnują braku zależności domeny od Filament, Livewire i `App\Http`, braku Illuminate w silniku, zakazu `dd`, `dump`, `ray` i używania `env()` wyłącznie w konfiguracji. |
| `tests/Golden`, `tests/Unit`, `tests/Feature` | Przypadki referencyjne, testy obliczeń i testy zachowania aplikacji. |
