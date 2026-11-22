@if ($movements->isEmpty())
    <p>Brak wpisów.</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left">
                <th>Data</th>
                <th>Zmiana</th>
                <th>Dostępna ilość po zmianie</th>
                <th>Osoba</th>
                <th>Powód</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $movement)
                <tr>
                    <td>{{ $movement->occurred_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement->kind === 'opening' ? 'Stan otwarcia' : sprintf('%+d', $movement->delta_free) }}</td>
                    <td>{{ $movement->free_after }}</td>
                    <td>{{ $movement->actor?->name ?? '—' }}</td>
                    <td>{{ $movement->reason ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
