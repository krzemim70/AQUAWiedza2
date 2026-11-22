<div>
    @if ($rows->isEmpty())
        <p>Brak zapisanych pomiarów.</p>
    @else
        <table>
            <caption>Pomiary od najnowszego</caption>
            <thead>
                <tr>
                    <th scope="col">Data</th>
                    @foreach ($labels as $label)
                        <th scope="col">{{ $label }}</th>
                    @endforeach
                    <th scope="col">Uwagi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr wire:key="m-{{ $row['m']->id }}">
                        <th scope="row">{{ $row['m']->measured_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</th>
                        @foreach ($labels as $param => $label)
                            <td>{{ $row['m']->$param ?? '—' }}</td>
                        @endforeach
                        <td>{{ implode(' ', $row['warnings']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
