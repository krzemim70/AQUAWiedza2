{{-- Karta „Zgodność obsady”. Zmienne: $report (CompatibilityReport), $hasStock. --}}
<x-card class="mb-6">
    <h3 id="compat-heading">Zgodność obsady według bazy wiedzy</h3>
    @if (! $report->assessed)
        <p role="status">Uzupełnij wielkość zbiornika w zakładce „Zbiornik”, aby sprawdzić zgodność obsady.</p>
    @elseif (! $hasStock)
        <p>Dodaj gatunki do obsady, aby zobaczyć zgodność według bazy wiedzy.</p>
    @else
        @php($verdict = $report->verdict())
        <p role="status" class="text-lg font-semibold">
            {{ $verdict->label() }}
            @if ($report->findings)
                <span class="text-base font-normal text-text-2">(konflikty: {{ $report->count(\App\Domain\Advisor\Engine\Compatibility\Severity::Conflict) }}, uwagi: {{ $report->count(\App\Domain\Advisor\Engine\Compatibility\Severity::Warning) }}, informacje: {{ $report->count(\App\Domain\Advisor\Engine\Compatibility\Severity::Info) }})</span>
            @endif
        </p>
        @if ($report->findings)
            <details class="my-4" @if ($verdict !== \App\Domain\Advisor\Engine\Compatibility\Verdict::Clear) open @endif>
                <summary class="min-h-11 cursor-pointer py-2 font-semibold">Ustalenia ({{ count($report->findings) }})</summary>
                @include('aquarium._findings', ['findings' => $report->findings])
            </details>
        @endif
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Kontrole zgodności obsady</caption>
                <thead>
                    <tr><th scope="col">Kontrola</th><th scope="col">Sprawdzone</th><th scope="col">Zastrzeżenia</th><th scope="col">Brak danych</th></tr>
                </thead>
                <tbody>
                    @foreach ($report->rows as $row)
                        <tr>
                            <th scope="row">{{ $row->check->label() }}</th>
                            <td>{{ $row->checked }} {{ $row->check->unit() }}</td>
                            <td>{{ $report->findingsFor($row->check) }}</td>
                            <td>{{ $row->noData }} {{ $row->check->unit() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($report->unknown > 0)
            <p class="text-sm text-muted">Pominięto pozycje wycofane z katalogu: {{ $report->unknown }}.</p>
        @endif
        <p class="text-sm text-muted">Ocena opiera się wyłącznie na danych bazy wiedzy o gatunkach. Nie jest diagnozą i nie zastępuje porady specjalisty.</p>
    @endif
</x-card>
