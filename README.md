# aquawiedza-v2

Nowa wersja systemu AquaSmart / Aikwarium (doradca akwarystyczny), poza WordPressem. Obecny system (`../AquaSmart-plugins`, `../AQUAWiedza`) traktujemy jako PoC: przenosimy funkcje i algorytmy, nie implementację.

## Etapy

| # | Etap | Status |
|---|---|---|
| 1 | Analiza stanu obecnego i projekt v2 (bez kodu) | ✅ zrobione; otwarte pytania do twórcy (`90-pytania-do-tworcy.md`) |
| 2 | Szkielet v2 z kluczowymi funkcjami | 🔄 gotowa baza projektu; w toku: ustawienia admina; dalej: konta i dostęp, baza wiedzy, doradca, akwarium, sklepy… |
| 3 | Layout i branding | — |
| 4 | Funkcje zaawansowane, teksty, publikacja | — |

## Dokumenty

**Analiza (`docs/01-analiza/`)**, wszystkie twierdzenia z odwołaniami `plik:linia`:
- [05 – inwentarz techniczny](docs/01-analiza/05-inwentarz-techniczny.md): hooki, endpointy, 13 tabel, cron, dane
- [10 – silnik rekomendacji](docs/01-analiza/10-silnik-rekomendacji.md): algorytm K1/K2/K3, słowniki, 16 testów golden
- [20 – sklep](docs/01-analiza/20-sklep.md): automat publikacji (Model Y), bufor, tracking, rollup
- [30 – konta i panel użytkownika](docs/01-analiza/30-konta-i-panel-uzytkownika.md): rejestracja, trial, zbiorniki, pomiary, Redfield, RODO
- [40 – dokumentacja → wymagania](docs/01-analiza/40-dokumentacja-wymagania.md): katalog wymagań FR-*, sprzeczności, produkcja
- [90 – pytania do twórcy](docs/01-analiza/90-pytania-do-tworcy.md)

**Projekt (`docs/02-projekt/`):**
- [00 – ustalenia projektu](docs/02-projekt/00-decyzje.md)
- [01 – stack i architektura](docs/02-projekt/01-stack-i-architektura.md) (baza: MySQL 8.4)
- [02 – niezależna recenzja stacku](docs/02-projekt/02-recenzja-stacku.md)
- [10 – domena sklepów](docs/02-projekt/10-domena-sklepu.md) (SHOP-01…20, tabele, automaty stanów, panele)
- [11 – model domenowy i plan etapu 2](docs/02-projekt/11-model-domenowy.md) (Drop/Tank/Fishroom, moduły, tabele, plan 13 kroków)

**Aplikacja:** Laravel 13 + Filament 5 + Livewire 4 + MySQL 8.4. Środowisko lokalne: `../tools/README.md`.

**Polecenia:** `composer test` (Pest), `composer lint` (Pint), `composer analyse` (Larastan, poziom 6), `composer check` (wszystko; musi przejść przed każdym merge do `master`), `npm run build`.
