# Moduły domenowe

Katalog `app/Domain/<Module>` powstaje razem z pierwszym kodem modułu (bez pustych katalogów). Pełny opis: `docs/02-projekt/11-model-domenowy.md` §1 i §6.

| Moduł | Odpowiedzialność |
|---|---|
| IdentityAccess | Konto, aktywacja, zgody, poziom dostępu (Tank/Fishroom, zawieszenie), jedna decyzja `ResolveAccess` i historia zmian dostępu |
| KnowledgeBase | Encje i słowniki wiedzy, rewizje, importy, publikowane wydania katalogu |
| Advisor | Sesje doradcy, plany pytań, ranking, weryfikacja i historia; czysty silnik w `Engine` |
| Aquarium | Zbiorniki, obsada, pomiary, dziennik, przypomnienia, kalkulator |
| Shops | Sklep (stany, aktywacja), placówki, członkostwo, oferty z historią ilości, koszyk, zamówienia (decyzja, rezerwacje, EAN-13, realizacja), raport dobowy, pseudonimizacja; publiczny odczyt ofert (`Contracts\PublicOffers`) |
| Tracking | Zapis kliknięć w oferty i kontrola przekierowań |
| Metrics | Próbki dzienne, okresy, raporty, estymaty, telemetria |
| Settings | Definicje i wersje ustawień (`effective_from`), audyt |
| Campaigns | Okna kampanii, segmenty, kody, wykorzystania |
| Notifications | Szablony, preferencje, wysyłka i stan dostarczenia |
| Privacy | Zgody, eksport, usuwanie danych, retencja |
| Admin | Kompozycja Filament, operacje operatora, audyt administracyjny |

## Reguły

- Akcje `VerbNoun::handle(DTO)`; zdarzenia `NounPastVerb`.
- Eloquent/SQL tylko do własnych tabel modułu; do innych modułów przez `Contracts` (DTO) lub zdarzenia.
- Domena nie używa Filament, Livewire ani `App\Http`; `Advisor\Engine` nie używa Illuminate.
- Policy przed każdym zapisem; operacje wielozapisowe w transakcji; zdarzenia/joby `afterCommit`.

## Zdarzenia

- Zdarzenia domenowe implementują `ShouldDispatchAfterCommit` (nigdy nie wychodzą przed commitem ani przy rollbacku).
- Listenery i joby, które nie mogą zadziałać dwa razy, opierają idempotencję na kluczach `UNIQUE` w bazie.
- Bez własnego outboxa; wracamy do tematu dopiero przy zewnętrznym brokerze.
