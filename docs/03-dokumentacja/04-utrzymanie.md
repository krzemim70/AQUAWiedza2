# Utrzymanie

## Harmonogram

Zadania rejestruje [routes/console.php](../../routes/console.php). Wszystkie mają blokady `onOneServer()` i `withoutOverlapping()`. Przy wielu instancjach użyj wspólnego cache z tym samym prefiksem. Na serwerze uruchamiaj `php artisan schedule:run` co minutę i sprawdź konfigurację przez `php artisan schedule:list`.

| Zadanie | Częstotliwość | Działanie |
|---|---|---|
| `expire-fishroom` | Codziennie | Kończy wygasłe nadania Fishroom i zapisuje zmianę w historii. |
| `purge-unverified-accounts` | Codziennie | Usuwa stare niepotwierdzone konta inne niż administracyjne. |
| `expire-offers` | Co godzinę | Oznacza wygasłe opublikowane oferty i zamyka okresy ich cen. |
| `purge-offer-imports` | Co godzinę | Czyści pliki i wiersze importów po terminie retencji. |
| `shop-daily-reports` | Co godzinę | Po osiągnięciu ustawionej godziny tworzy brakujący raport za poprzednią lokalną dobę dla sklepu aktywnego lub zawieszonego. Domyślnie od 10:00 w strefie `Europe/Warsaw`. |
| `pseudonymize-orders` | Codziennie | Pseudonimizuje zamówienia po upływie retencji kontaktu. |
| `notify-record-watchers` | Co 15 minut | Sprawdza dostępność, kolejkuje wiadomości i wygasza powiadomione obserwacje po okresie retencji. |

Godzina codziennych wywołań harmonogramu to północ strefy aplikacji (UTC). Doba raportu jest liczona w `APP_DISPLAY_TIMEZONE`. Starszy pominięty dzień uzupełnij przez `php artisan shops:daily-reports --date=RRRR-MM-DD`. Klucz sklepu i dnia zapobiega dublowaniu raportu. Gdy raport już istnieje, a jego wysyłka zakończyła się błędem, ponów zadanie wysyłki z kolejki.

## Kolejka i poczta

- Używaj `QUEUE_CONNECTION=database` i nadzoruj proces `php artisan queue:work database --sleep=3 --timeout=60`. Czas workera musi być krótszy niż `DB_QUEUE_RETRY_AFTER` (domyślnie 90 s). Po wdrożeniu lub zmianie konfiguracji odśwież cache konfiguracji i wykonaj `php artisan queue:restart`.
- Globalne `after_commit` połączenia bazodanowego ma wartość `false`. Operacje zależne od zapisu używają jawnie `DB::afterCommit`, `->afterCommit()` lub `ShouldQueueAfterCommit`, np. zdarzenie rejestracji, import CSV i poczta o zamówieniach.
- Kolejkowana poczta korzystająca z `QueuedMail` ma trzy próby, z odstępami 60 i 300 s. Import CSV ma trzy próby na zadanie, zapisuje w partiach do 100 wierszy i po wyczerpaniu prób odnotowuje błąd importu. Wiadomość z wynikiem importu jest wysyłana w trakcie obsługi zadania przy błędach w raporcie lub nieudanym imporcie, jeśli odbiorca nadal należy do zespołu. Sprawdzaj log oraz `php artisan queue:failed`. Po usunięciu przyczyny ponów konkretne zadanie przez `php artisan queue:retry <uuid>`.
- E-maile obejmują weryfikację, reset hasła, zdarzenia zamówień, raporty dobowe, wynik importu CSV i dostępność obserwowanego rekordu. W produkcji skonfiguruj SMTP przez `MAIL_*` i zweryfikowany adres `MAIL_FROM_ADDRESS`. Ustaw SPF, DKIM i DMARC po stronie domeny nadawcy, a przed publikacją sprawdź doręczenie i linki pod docelową domeną.
- Lokalnie można użyć `MAIL_MAILER=log` i [podglądu HTML](01-uruchomienie.md#podgląd-poczty). Przy sprawdzaniu obserwacji znacznik jest zapisywany po przyjęciu wiadomości przez kolejkę. Awaria pomiędzy tymi krokami może spowodować ponowienie.

## Retencja i prywatność

| Dane | Zachowanie |
|---|---|
| Niepotwierdzone konta | Domyślnie usuwane po 30 dniach od rejestracji, po ponownym sprawdzeniu stanu konta. Wiek i wielkość porcji są w `identity.pending.*`. |
| Import CSV | `shops.import.csv.retention_hours`, domyślnie 24 godziny. Zadanie usuwa plik i wiersz partii po wygaśnięciu. |
| Obserwacje z wysłaną wiadomością | Po upływie `shops.watch.retention_days` od powiadomienia (domyślnie 14 dni) są oznaczane jako usunięte. |
| Kontakt zamówienia | Po terminie liczonym od stanu końcowego (`shops.orders.pseudonymize_days`, domyślnie 90 dni) lub przy usunięciu konta. Pseudonimizacja usuwa migawkę kontaktu i powiązanie z klientem oraz czyści notatkę i powody w historii. Pozostają m.in. kod zamówienia, stany, daty, pozycje i kwoty. Odczyt ukrywa kontakt już z upływem terminu. |
| Usunięcie konta | Usuwa prywatne dane konta z zależnościami, token resetu i sesje bazodanowe. Zamówienia są pseudonimizowane, a opinie tracą powiązanie z autorem. |

## Wdrożenie

| Obszar | Konfiguracja |
|---|---|
| Aplikacja | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` z publicznym adresem HTTPS, `APP_LOCALE=pl` i `APP_DISPLAY_TIMEZONE` dla prezentacji dat. Ten sam `APP_KEY` w aplikacji HTTP i workerze. |
| Baza i procesy | Ustaw `DB_*`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database` i `SESSION_DRIVER=database`. Wykonaj `php artisan migrate --force` i zbuduj zasoby przez `npm run build`. Uruchom nadzorowanego workera i harmonogram. |
| Sesje | Używaj HTTPS, ustaw `SESSION_SECURE_COOKIE=true` i zachowaj `SESSION_HTTP_ONLY=true`. |
| Indeksowanie | `APP_INDEXABLE=true` dla wdrożenia przeznaczonego do indeksowania. Na stagingu ustaw jawnie `false`, zwłaszcza przy `APP_ENV=production`. Przy `false` aplikacja zwraca `X-Robots-Tag: noindex` i `Disallow: /` w robots. Panele dostają `noindex` także na produkcji indeksowanej. |
| Import CSV | W konfiguracji PHP serwera ustaw `upload_max_filesize = 12M` i `post_max_size = 13M`, a na serwerze WWW odpowiedni limit żądania, np. `client_max_body_size 13m;` dla nginx. Limity aplikacji nadal wynikają z `shops.import.csv`. |
| CSP | Middleware wysyła `Content-Security-Policy-Report-Only` dla odpowiedzi poza ścieżkami API. To tryb raportowania bez blokowania. Nagłówek nie wskazuje odbiornika raportów. |
| Administrator | Panel używa osobnego guarda i wymusza dwuskładnikowe logowanie aplikacją uwierzytelniającą z kodami odzyskiwania. `ADMIN_REQUIRE_2FA=false` działa wyłącznie w `APP_ENV=local`. |

## Kopie i monitoring

Kopie zapasowe oraz monitoring są do wdrożenia. Zakres kopii obejmuje bazę i pliki potrzebne do odtworzenia wdrożenia, z próbą przywrócenia. Punkty kontroli to `/up`, działanie harmonogramu i workera, wiek zadań w `jobs`, błędy w `failed_jobs` i doręczenia SMTP.
