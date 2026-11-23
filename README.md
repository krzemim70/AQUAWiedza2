# Aikwarium v2

Aikwarium to polskojęzyczny serwis dla akwarystów i sklepów akwarystycznych. Doradca pomaga przejrzeć bazę wiedzy i porównać dane z profilem zbiornika, a strefa akwarysty pozwala prowadzić zbiorniki i planować ich obsadę. Sklepy publikują oferty lokalne i obsługują zamówienia z odbiorem w placówce.

## Szybki start na Windows

Przygotuj przenośne PHP, Composer, Node i MySQL w `..\tools`, a następnie uruchom z katalogu projektu:

```powershell
.\dev.cmd
```

Aplikacja używa Laravel 13, Filament 5, Livewire 4 i MySQL 8.4. Konfigurację narzędzi, baz i podglądu poczty opisuje [instrukcja uruchomienia](docs/03-dokumentacja/01-uruchomienie.md).

## Polecenia

| Polecenie | Działanie |
|---|---|
| `.\dev.cmd -Stop` | Zatrzymuje lokalny MySQL. |
| `composer test` | Uruchamia testy Pest. |
| `composer lint` | Sprawdza formatowanie kodu przez Pint. |
| `composer analyse` | Uruchamia Larastan na poziomie 6. |
| `composer check` | Wykonuje sprawdzenie formatowania, analizę statyczną i testy. |
| `npm run build` | Buduje zasoby Vite. |

## Stan projektu

- Zaimplementowano publiczny katalog i SEO, trzy tryby doradcy, konta i zgody, strefę akwarysty, obserwowanie rekordów oraz kalkulatory.
- Panel sklepu obsługuje oferty, import CSV, zamówienia do odbioru, promocje ofert, opinie, lokalny popyt i dobowe raporty e-mail. Panel administratora obsługuje m.in. ustawienia, zdjęcia, moderację opinii i aktywację partii katalogu.
- Odłożono kampanie i osobną stronę promocji, płatności online, optymalizację listy zakupów, masowe dodawanie planu do koszyka i szacowanie popytu z odsłon. Treści części stron publicznych są w przygotowaniu. Kopie zapasowe i monitoring pozostają do wdrożenia.

## Mapa dokumentacji

### Instrukcje dla bieżącej wersji

- [Uruchomienie](docs/03-dokumentacja/01-uruchomienie.md) opisuje lokalne środowisko Windows, bazy, testy i podgląd poczty.
- [Architektura](docs/03-dokumentacja/02-architektura.md) opisuje moduły, decyzje o dostępie i punkty rozszerzeń.
- [Funkcje](docs/03-dokumentacja/03-funkcje.md) zestawiają dostęp, trasy, ustawienia i granice działania.
- [Utrzymanie](docs/03-dokumentacja/04-utrzymanie.md) opisuje harmonogram, kolejkę, pocztę, retencję i konfigurację serwera.

### Analiza wcześniejszej wersji i przygotowanie v2

- [Inwentarz techniczny](docs/01-analiza/05-inwentarz-techniczny.md) opisuje wtyczki WordPressa, integracje, tabele i zadania cykliczne.
- [Silnik rekomendacji](docs/01-analiza/10-silnik-rekomendacji.md) opisuje pierwotne algorytmy doboru, słowniki i przypadki referencyjne.
- [Sklep](docs/01-analiza/20-sklep.md) opisuje wcześniejszy model publikacji ofert i naliczania statystyk.
- [Konta i panel użytkownika](docs/01-analiza/30-konta-i-panel-uzytkownika.md) opisują wcześniejszą rejestrację, zbiorniki, pomiary i prywatność.
- [Wymagania](docs/01-analiza/40-dokumentacja-wymagania.md) zbierają wymagania i sprzeczności z materiałów wyjściowych.
- [Strony publiczne na produkcji](docs/01-analiza/50-produkcja-strony-publiczne.md) inwentaryzują publiczną część wcześniejszego serwisu.
- [Aktualizacja analizy](docs/01-analiza/60-aktualizacja-do-galezi-aktualnej.md) zestawia różnice względem wersji przyjętej w pierwszych raportach.
- [Inwentarz bazy testowej](docs/01-analiza/70-baza-testowa-inwentarz.md) opisuje strukturę i dane kopii WordPressa.
- [Przegląd stagingu](docs/01-analiza/80-staging-przeglad.md) opisuje ekrany i zachowania wcześniejszego środowiska testowego.
- [Pytania do twórcy](docs/01-analiza/90-pytania-do-tworcy.md) zbierają tematy wymagające rozstrzygnięcia podczas projektowania.
- [Kreator doradcy](docs/01-analiza/92-kreator-doradcy.md) porównuje wcześniejszy kreator z projektem przeniesienia do v2.
- [Braki przed startem](docs/01-analiza/95-braki-profesjonalizm.md) inwentaryzują luki w bezpieczeństwie, obsłudze i gotowości do publikacji.

### Projekt i decyzje

- [Rejestr decyzji](docs/02-projekt/00-decyzje.md) zbiera ustalenia projektowe i późniejsze zmiany zakresu.
- [Stos technologiczny i architektura](docs/02-projekt/01-stack-i-architektura.md) opisują pierwotną propozycję techniczną.
- [Recenzja stosu](docs/02-projekt/02-recenzja-stacku.md) ocenia ryzyka i kompromisy pierwotnej propozycji.
- [Domena sklepów](docs/02-projekt/10-domena-sklepu.md) opisuje projekt aktywacji sklepów, ofert, koszyka i realizacji zamówień.
- [Model domenowy](docs/02-projekt/11-model-domenowy.md) opisuje projekt modułów, dostępu, danych i testów.
- [Nowe funkcje](docs/02-projekt/12-nowe-funkcje.md) opisują projekt rozszerzeń, z pierwszeństwem korekt z §14.4 przed wcześniejszymi założeniami.
- [Kierunki rozwoju](docs/02-projekt/13-kierunki-rozwoju.md) zbierają pomysły na pełne rekomendacje, funkcje dla klientów, aplikację mobilną i wstępną analizę urządzeń (do decyzji).
- [Etap „Teraz”](docs/02-projekt/14-etap-teraz.md) opisuje uzgodniony zakres i projekt najbliższego etapu: pola strukturalne i redakcja, zapasy, powiadomienia push, trendy, „Czy razem?”.
- [Kreator akwarium](docs/02-projekt/15-kreator-akwarium.md) opisuje projekt interaktywnego kreatora działającego w przeglądarce (do decyzji).
- [Zakupy i płatności](docs/02-projekt/16-zakupy-i-platnosci.md) analizuje obecny przepływ zamówień, warianty płatności, obowiązki platformy i proponuje etapy (do decyzji).
- [Lokalny dowóz](docs/02-projekt/17-lokalny-dowoz.md) porównuje dowóz własny sklepu, kurierów miejskich, platformy dostaw i paczkomaty, z zasadami dla żywych zwierząt (do decyzji).
- [Archiwalny model afiliacyjny](docs/02-projekt/archiwum/10-domena-sklepu-model-afiliacyjny.md) dokumentuje wcześniejszy projekt publikacji ofert i rozliczeń afiliacyjnych.
- [Archiwalny model domenowy](docs/02-projekt/archiwum/11-model-domenowy-przed-mapa.md) zachowuje wcześniejszą wersję projektu aplikacji i modelu danych.
