@push('styles')
    <style>
        .local-demand-table { width: 100%; text-align: start; }
        .local-demand-table :is(th, td) { padding: .65rem .5rem; vertical-align: top; overflow-wrap: anywhere; }
        .local-demand-table th { font-weight: 600; }
        .local-demand-table :is(th, td):last-child { text-align: end; }
        .local-demand-table th:first-child { text-align: start; }
        .local-demand-table tbody tr { border-top: 1px solid var(--color-line, rgb(128 128 128 / .25)); }
        .local-demand-table small { display: block; font-weight: 400; }
    </style>
@endpush

<x-filament-panels::page>
    @forelse ($demand as $city)
        <x-filament::section :heading="$city['city']">
            <table class="local-demand-table" aria-label="Aktywne obserwacje – {{ $city['city'] }}">
                <thead>
                    <tr>
                        <th scope="col">Rekord</th>
                        <th scope="col">Liczba obserwacji</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($city['records'] as $record)
                        <tr>
                            <th scope="row">
                                {{ $record['name'] }}
                                <small>{{ $record['area']->label() }} · {{ $record['id'] }}</small>
                            </th>
                            <td>{{ $record['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @empty
        <x-filament::section>
            <p>Za mało danych, żeby pokazać statystykę w Twojej okolicy.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
