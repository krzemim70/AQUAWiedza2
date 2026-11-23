@props(['average', 'count', 'shopId' => null, 'shopName' => null])

<div class="text-sm text-muted">
    @if ($average === null)
        <span>Za mało opinii</span>
    @else
        <span class="inline-flex flex-wrap items-center gap-x-1 font-semibold text-ink">
            <x-heroicon-s-star class="icon" aria-hidden="true" />
            Średnia ocena sklepu {{ number_format($average, 1, ',', ' ') }} z 5 (liczba opinii {{ $count }})
        </span>
    @endif
    <span class="block">Opinie wyłącznie po odebranym zamówieniu.</span>
    @if ($shopId !== null)
        <a href="{{ route('shops.reviews.index', $shopId) }}" class="inline-flex min-h-11 items-center underline"
            aria-label="Zobacz opinie o sklepie {{ $shopName }}">Zobacz opinie</a>
    @endif
</div>
