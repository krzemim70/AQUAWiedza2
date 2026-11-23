# Uruchomienie na Windows

Przygotuj przenośne PHP 8.4, Composer, Node i MySQL 8.4 w katalogu `..\tools` względem kopii projektu. Zmienna `AIKWARIUM_TOOLS` może wskazywać inny katalog z narzędziami.

```powershell
.\dev.cmd
```

Polecenie uruchamia MySQL, w razie potrzeby instaluje zależności i tworzy `.env`, wykonuje migracje oraz startuje serwer aplikacji, worker kolejki, podgląd logów i Vite. Otwórz `http://localhost:8000` przy domyślnym adresie. Aby zatrzymać MySQL, użyj `.\dev.cmd -Stop`. Jeśli serwer bazy jest współdzielony przez kopie robocze, zatrzymanie wpłynie też na pozostałe kopie.

## Dane do pracy lokalnej

- Katalog zasilisz poleceniem `php artisan kb:import <katalog-paczki>`. Polecenie sprawdza paczkę, zapisuje partię i aktywuje ją po poprawnej walidacji.
- Lokalnego administratora utworzysz przez `php artisan db:seed` po ustawieniu `DEV_ADMIN_EMAIL` i `DEV_ADMIN_PASSWORD` w `.env`. Ten seeder działa w `APP_ENV=local`.


## Bazy i testy

| Przeznaczenie | Konfiguracja |
|---|---|
| Aplikacja lokalna | MySQL `127.0.0.1:3306`, baza `aikwarium`, połączenie `DB_*` w `.env`. |
| Testy | Osobna baza MySQL na każdą kopię roboczą. Utwórz ją i nadaj użytkownikowi testowemu uprawnienia do migracji. Ustaw `DB_DATABASE` w środowisku procesu testów. `phpunit.xml` podaje `aikwarium_test` jako wartość domyślną. |

W osobnej konsoli załaduj środowisko narzędzi, np. `. ..\tools\env.ps1` przy standardowej instalacji. Zmienną bazy ustaw wyłącznie dla sesji testowej. Testy integracyjne odtwarzają schemat bazy, więc używaj w nich wyłącznie bazy testowej.

```powershell
$env:DB_DATABASE = 'aikwarium_test_moja_kopia'
composer check
Remove-Item Env:DB_DATABASE
```

`composer check` jest bramką jakości. Uruchamia kolejno `composer lint` (Pint), `composer analyse` (Larastan, poziom 6) i `composer test` (Pest, zestawy Golden, Unit, Architecture i Feature). `npm run build` buduje zasoby Vite osobno.

## Podgląd poczty

W `APP_ENV=local` otwórz `/_podglad-poczty/{rodzaj}`. Podgląd renderuje przykładowy HTML bez wysyłki.

- Konto: `weryfikacja`, `reset-hasla`.
- Zamówienia: `nowe-zamowienie`, `zamowienie-potwierdzone`, `zamowienie-odrzucone`, `zamowienie-gotowe`.
- Raport sklepu: `raport-dobowy`.

## Typowe problemy

| Objaw | Sprawdzenie |
|---|---|
| Brak PHP, Composer, Node lub MySQL | Sprawdź układ `..\tools`, wartość `AIKWARIUM_TOOLS` i środowisko konsoli przy ręcznym wykonywaniu poleceń. |
| Migracje lub testy nie łączą się z MySQL | Sprawdź, czy działa MySQL, czy baza istnieje oraz czy port i uprawnienia w `DB_*` są prawidłowe. Po zmianie ustawień wyczyść cache konfiguracji przez `php artisan config:clear`. |
| Vite nie może zapisać lub przeładować tymczasowego pliku konfiguracji | Zamknij poprzedni proces Vite i uruchom `npm run dev -- --configLoader runner`. |
| Wiadomości lub duży import CSV czekają | Sprawdź proces workera i kolejkę. Szczegóły są w [instrukcji utrzymania](04-utrzymanie.md). |
