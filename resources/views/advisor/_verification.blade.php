<p class="lead">Sprawdzenie produktu pod kątem warunków w Twoim akwarium.</p>

@if ($verification->notice)
    <p class="advisor-feedback" role="status">{{ $verification->notice }}</p>
@endif

@include('advisor._result-card', ['card' => $verification, 'full' => true, 'unscored' => $verification->compatibility === []])

@if ($verification->compatibility !== [])
    <div class="card mt-6">
        <table class="advisor-comparison">
            <caption>Porównanie warunków w akwarium</caption>
            <thead>
                <tr>
                    <th scope="col">Parametr</th>
                    <th scope="col">Twoje warunki</th>
                    <th scope="col">Optymalnie</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($verification->compatibility as $row)
                    <tr>
                        <th scope="row">{{ $row['label'] }}</th>
                        <td>{{ $row['user'] }} <strong class="mt-1 block text-sm">{{ ucfirst($row['status']) }}</strong></td>
                        <td>{{ $row['optimal'] ?: 'Brak danych' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($verification->alternative || $verification->correction)
    <section class="mt-10" aria-labelledby="advisor-verification-more">
        <h2 id="advisor-verification-more">Warto rozważyć także</h2>
        <div class="advisor-grid">
            @foreach (array_filter([$verification->alternative, $verification->correction]) as $card)
                @include('advisor._result-card', ['card' => $card, 'full' => false])
            @endforeach
        </div>
    </section>
@endif

@include('advisor._calculation', ['criteria' => array_column($verification->compatibility, 'label')])
