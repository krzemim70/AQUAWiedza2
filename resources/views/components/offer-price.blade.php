@props(['offer', 'strong' => true])

@php($money = fn (int $grosze) => number_format($grosze / 100, 2, ',', ' ').' zł')
@php($promo = $offer->promoEndsAt !== null && $offer->lowestPriorGrosze !== null)
{{-- bez opakowania, żeby cena w zdaniu pozostała zwykłym tekstem --}}
@if ($strong)<strong>{{ $money($offer->priceGrosze) }}</strong>@else{{ $money($offer->priceGrosze) }}@endif
@if ($promo)<span class="ml-1 inline-flex items-center rounded-full border border-aqua bg-aqua-50 px-2.5 py-0.5 text-sm font-semibold text-aqua-700">Promocja do {{ $offer->promoEndsAt->copy()->subSecond()->timezone(config('app.display_timezone'))->format('d.m') }}</span>
<small class="block text-muted">@if ($offer->lowestPriorSinceStart)Najniższa cena od rozpoczęcia sprzedaży: {{ $money($offer->lowestPriorGrosze) }}@else Najniższa cena z {{ $offer->lookbackDays }} dni przed obniżką: {{ $money($offer->lowestPriorGrosze) }}@endif</small>
@endif
