{{-- Karta „Zgodność obsady”. Zmienne: $report (CompatibilityReport), $hasStock. --}}
<x-card class="mb-6">
    <h3 id="compat-heading">Zgodność obsady według katalogu</h3>
    @if (! $hasStock)
        <p>Brak danych do porównania. Obsada jest pusta.</p>
    @else
        @php($verdict = $report->verdict())
        <p role="status" class="text-lg font-semibold">{{ $verdict->label() }}</p>
        @if ($report->findings)
            <details class="my-4" @if ($verdict !== \App\Domain\Advisor\Engine\Compatibility\Verdict::Clear) open @endif>
                <summary class="min-h-11 cursor-pointer py-2 font-semibold">Wpisy katalogowe ({{ count($report->findings) }})</summary>
                @include('aquarium._findings', ['findings' => $report->findings])
            </details>
        @endif
        @if ($report->findingsFor(\App\Domain\Advisor\Engine\Compatibility\Check::Temperament) > 0)
            <p>Relacja pary: brak danych.</p>
        @endif
        <div class="table-scroll">
            <table>
                <caption class="sr-only">Kontrole zgodności obsady</caption>
                <thead>
                    <tr><th scope="col">Kontrola</th><th scope="col">Z danymi</th><th scope="col">Brak danych</th></tr>
                </thead>
                <tbody>
                    @foreach ($report->rows as $row)
                        <tr>
                            <th scope="row">{{ $row->check->label() }}</th>
                            @if ($row->checked === 0 && $row->noData === 0)
                                <td colspan="2">Brak danych</td>
                            @else
                                <td>{{ $row->checked }} {{ $row->check->unit() }}</td>
                                <td>{{ $row->noData }} {{ $row->check->unit() }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($report->unknown > 0)
            <p class="text-sm text-muted">Brak danych katalogowych dla {{ $report->unknown }} pozycji obsady.</p>
        @endif
    @endif
</x-card>
