<div>
    <p><a href="{{ route('aquarium.index') }}">&larr; Moje zbiorniki</a></p>
    <h1>{{ $tank->name }}</h1>
    <p>{{ $tank->type->value }}, {{ $tank->size_band?->label() ?? 'bez wielkości' }}@if ($tank->size_band) (ok. {{ $tank->volumeLiters() }} l)@endif</p>
    <p><a href="{{ route('advisor.tank', ['tank' => $tank->id]) }}">Dobierz ryby do tego zbiornika</a></p>

    <nav class="tabs" aria-label="Sekcje zbiornika">
        <ul>
            @foreach ($tabs as $key => $item)
                <li>
                    <button type="button" wire:click="$set('tab', '{{ $key }}')" @if ($key === $current) aria-current="page" @endif>
                        {{ $item['label'] }}@unless ($item['allowed']) (Fishroom)@endunless
                    </button>
                </li>
            @endforeach
        </ul>
    </nav>

    <section class="panel" aria-labelledby="tab-heading">
        <h2 id="tab-heading">{{ $tabs[$current]['label'] }}</h2>
        @if ($tabs[$current]['allowed'])
            <livewire:dynamic-component :is="$tabs[$current]['component']" :tank-id="$tank->id" :key="$current.'-'.$tank->id" />
        @else
            <x-card class="locked-state"><x-heroicon-o-lock-closed aria-hidden="true" />
            <p>{{ $tabs[$current]['message'] }}</p>
            <p>Plan Fishroom odblokowuje tę sekcję, w tym historię parametrów, dziennik i przypomnienia dla wielu zbiorników.</p>
            </x-card>
        @endif
    </section>
</div>
