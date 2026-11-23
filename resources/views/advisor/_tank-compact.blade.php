<li class="relative">
    <div class="flex min-w-0 items-center gap-3">
        @if (isset($thumbnails[$match->record->id]))
            <img src="{{ $thumbnails[$match->record->id] }}" alt="" width="56" height="56"
                class="size-14 shrink-0 rounded-lg border border-line bg-tint object-cover" loading="lazy" decoding="async">
        @else
            <span class="flex size-14 shrink-0 items-center justify-center rounded-lg border border-line bg-tint">
                <x-heroicon-o-photo class="size-6 text-muted" aria-hidden="true" />
            </span>
        @endif
        <a class="catalog-card-link inline-flex min-h-11 items-center after:absolute after:inset-0"
            href="{{ route('catalog.show', ['fauna', $match->record->id, 'wroc' => $catalogBackTo]) }}">
            {{ $match->record->name }} — {{ $match->percent() }}%
        </a>
    </div>
    @include('advisor._stock-notes', ['id' => $match->record->id])
    @if ($choose ?? false)
        <x-button variant="secondary" class="relative z-10" :href="route('advisor.tank', ['tank' => $tank->id, 'card' => $match->record->id])"
            :aria-label="'Dowiedz się więcej: '.$match->record->name">Dowiedz się więcej</x-button>
    @endif
    @if (! ($choose ?? false) || $card?->id !== $match->record->id)
        @include('advisor._card-actions', [
            'area' => \App\Domain\KnowledgeBase\Models\Area::Fauna,
            'id' => $match->record->id, 'name' => $match->record->name, 'variant' => 'compact',
            'cardOffers' => $offersById[$match->record->id] ?? [],
        ])
    @endif
</li>
