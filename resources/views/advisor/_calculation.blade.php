<details class="advisor-details mt-8">
    <summary>Jak Doradca to policzył</summary>
    @if ($criteria === [])
        <p>W tym przebiegu nie było kryteriów, na podstawie których można obliczyć dopasowanie.</p>
    @else
        <p>Każde kryterium ma taki sam udział w wyniku. Doradca dzieli zdobyte punkty przez maksymalną liczbę punktów i mnoży wynik przez 100.</p>
        <ul class="list-disc space-y-1 pb-4 pl-5">
            @foreach ($criteria as $criterion)
                <li>{{ $criterion }}</li>
            @endforeach
        </ul>
    @endif
</details>
