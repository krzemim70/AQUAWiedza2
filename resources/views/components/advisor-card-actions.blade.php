<div {{ $attributes->class(['card-actions relative z-10', 'space-y-6' => ! $compact]) }}>
    @if ($compact)
        @if ($offers)
            <div class="flex flex-col items-start gap-2">
                <span>od {{ number_format(($availableOffer ?? $offers[0])->priceGrosze / 100, 2, ',', ' ') }} zł · {{ $shopCount }} {{ $shopLabel }}</span>
                @if ($availableOffer && $canCart)
                    <form method="POST" action="{{ route('cart.add') }}">
                        @csrf
                        <input type="hidden" name="offer_id" value="{{ $availableOffer->id }}">
                        <input type="hidden" name="qty" value="1">
                        <x-button type="submit" variant="secondary" :aria-label="'Do koszyka: '.$name">Do koszyka</x-button>
                    </form>
                @elseif ($availableOffer && $guest)
                    <x-button :href="$loginUrl" variant="secondary" :aria-label="'Do koszyka: '.$name.' — zaloguj się'">Do koszyka</x-button>
                @elseif (! $availableOffer)
                    <span class="text-muted">Chwilowo niedostępne</span>
                @endif
            </div>
        @else
            <p>Brak ofert w sklepach.</p>
        @endif
        @include('watches._card-action')
    @else
        @include('advisor._offers')

        @if ($description !== '' || ($productPage && ! $fullDescription))
            <details class="rounded-card border border-line bg-white px-5 py-2" @if ($productPage) open @endif>
                <summary class="min-h-11 cursor-pointer py-3">
                    @if ($productPage)
                        <h2>Dowiedz się więcej</h2>
                    @else
                        <h4>Dowiedz się więcej</h4>
                    @endif
                </summary>
                @if ($description !== '')
                    <p class="whitespace-pre-line">{{ $description }}</p>
                @endif
                @unless ($fullDescription)
                    <p>{{ $description !== '' ? 'Pełny opis i wszystkie parametry są dostępne' : 'Wszystkie parametry są dostępne' }} po zalogowaniu na konto Tank lub Fishroom.</p>
                    @if ($guest)
                        <div class="mb-4 flex flex-wrap gap-2">
                            <x-button :href="route('register')" variant="secondary">Załóż konto</x-button>
                            <x-button :href="$loginUrl" variant="secondary">Zaloguj się</x-button>
                        </div>
                    @endif
                @endunless
            </details>
        @endif

        @if (($living || $equipment) && ($guest || $canAdd))
            @php($heading = $equipment ? 'Dodaj do zbiornika' : 'Dodaj do akwarium')
            <x-card>
                @if ($productPage)<h2>{{ $heading }}</h2>@else<h3>{{ $heading }}</h3>@endif

                @if ($equipment && session('equipment_catalog_id') === $id)
                    <x-alert type="success" role="status">{{ session('equipment_status') }}</x-alert>
                @elseif (session('livestock_status') === [$area->value, $id])
                    <x-alert type="success" role="status">Dodano do akwarium.</x-alert>
                @endif

                @if ($guest)
                    <x-button variant="secondary" :href="route('register')" :aria-label="'Załóż konto, aby zapisać w swoim akwarium: '.$name">Załóż konto, aby zapisać w swoim akwarium</x-button>
                @elseif ($tanks->isEmpty())
                    <x-button variant="secondary" :href="route('aquarium.index')" :aria-label="'Załóż zbiornik w Moim akwarium, aby dodać: '.$name">Załóż zbiornik w Moim akwarium</x-button>
                @else
                    @php($showErrors = $equipment ? old('catalog_id') === $id : old('area') === $area->value && old('species') === $id)
                    @php($cardErrors = $errors->getBag($equipment ? 'equipment' : 'livestock'))
                    @if ($showErrors && $cardErrors->any())
                        <x-alert type="error" role="alert">{{ $cardErrors->first() }}</x-alert>
                    @endif
                    <form method="POST" action="{{ route($equipment ? 'aquarium.equipment.store' : 'advisor.add-to-tank') }}" class="flex flex-wrap items-end gap-3">
                        @csrf
                        @if ($equipment)
                            <input type="hidden" name="catalog_id" value="{{ $id }}">
                        @else
                            <input type="hidden" name="area" value="{{ $area->value }}">
                            <input type="hidden" name="species" value="{{ $id }}">
                            <input type="hidden" name="request_key" value="{{ $requestKey }}">
                        @endif
                        <input type="hidden" name="return_to" value="{{ $returnTo }}">
                        @if ($tanks->count() > 1)
                            <label class="!m-0 min-w-40">
                                Zbiornik
                                <select name="tank" required @if ($showErrors && $cardErrors->has('tank')) aria-invalid="true" @endif>
                                    @foreach ($tanks as $tankId => $tankName)
                                        <option value="{{ $tankId }}" @selected(($showErrors ? filter_var(old('tank'), FILTER_VALIDATE_INT) : $preferredTank) === $tankId)>{{ $tankName }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @else
                            <span class="basis-full">Zbiornik: {{ $tanks->first() }}</span>
                            <input type="hidden" name="tank" value="{{ $tanks->keys()->first() }}">
                        @endif
                        <label class="!m-0 w-24">
                            Ilość
                            <input type="number" name="quantity" value="{{ $showErrors && is_scalar(old('quantity')) ? old('quantity') : 1 }}" min="1" max="{{ $maxQuantity }}" required
                                @if ($showErrors && $cardErrors->has('quantity')) aria-invalid="true" @endif>
                        </label>
                        @php($addLabel = $preferredTank === null || $equipment ? $heading : 'Dodaj do obsady')
                        <x-button type="submit" :aria-label="$addLabel.': '.$name">{{ $addLabel }}</x-button>
                    </form>
                @endif
            </x-card>
        @endif
    @endif
</div>
