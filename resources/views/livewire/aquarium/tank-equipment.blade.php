@php
    $maxQuantity = \App\Domain\Aquarium\Data\EquipmentData::MAX_QUANTITY;
    $addErrors = $errors->getBag('equipment');
@endphp
<div class="[&_a]:scroll-mt-24 [&_button]:scroll-mt-24 [&_input]:scroll-mt-24">
    <div aria-live="polite" aria-atomic="true">
        @if (session('equipment_status'))
            <x-alert type="success">{{ session('equipment_status') }}</x-alert>
        @endif
    </div>

    @if ($rows->isEmpty())
        <p>Brak zapisanego wyposażenia.</p>
    @else
        <ul class="m-0 list-none space-y-3 p-0">
            @foreach ($rows as $row)
                @php
                    $name = $row->area === \App\Domain\KnowledgeBase\Models\Area::Equipment ? ($names[$row->catalog_id] ?? null) : null;
                    $rowErrors = $errors->getBag('equipment-'.$row->id);
                @endphp
                <li id="equipment-{{ $row->id }}" class="scroll-mt-24">
                    <x-card class="flex flex-wrap items-end justify-between gap-4 !p-4">
                        <div class="min-w-0">
                            <h3 class="!mb-0 wrap-break-word">
                                @if ($name !== null)
                                    <a class="inline-flex min-h-11 items-center" href="{{ route('catalog.show', [$row->area->value, $row->catalog_id]) }}">{{ $name }}</a>
                                @else
                                    {{ $row->catalog_id }}
                                @endif
                            </h3>
                            <p class="!mb-0 text-sm text-muted">{{ $name === null ? 'Brak rekordu w katalogu' : $row->catalog_id }}</p>
                        </div>
                        <div class="flex flex-wrap items-end gap-3">
                            <form method="POST" action="{{ route('aquarium.equipment.update', [$tankId, $row->id]) }}" class="!m-0">
                                @csrf
                                @method('PATCH')
                                <div class="flex flex-wrap items-end gap-3">
                                    <label class="!m-0 w-24" for="equipment-quantity-{{ $row->id }}">
                                        Ilość <span class="sr-only">· {{ $name ?? $row->catalog_id }}</span>
                                        <input id="equipment-quantity-{{ $row->id }}" type="number" name="quantity" min="1" max="{{ $maxQuantity }}" required
                                            class="min-h-11" value="{{ $rowErrors->has('quantity') && is_scalar(old('quantity')) ? old('quantity') : $row->quantity }}"
                                            @if ($rowErrors->has('quantity')) aria-invalid="true" aria-describedby="equipment-error-{{ $row->id }}" @endif>
                                    </label>
                                    <x-button type="submit" variant="secondary" :aria-label="'Zapisz ilość: '.($name ?? $row->catalog_id)">Zapisz ilość</x-button>
                                </div>
                                @if ($rowErrors->has('quantity'))
                                    <span id="equipment-error-{{ $row->id }}" role="alert" class="field-error">{{ $rowErrors->first('quantity') }}</span>
                                @endif
                            </form>
                            <form method="POST" action="{{ route('aquarium.equipment.destroy', [$tankId, $row->id]) }}" class="!m-0">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" variant="secondary" :aria-label="'Usuń: '.($name ?? $row->catalog_id)">Usuń</x-button>
                            </form>
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>
    @endif

    <div id="equipment-search" class="mt-8 scroll-mt-24">
        <h3>Dodaj sprzęt</h3>
        <form method="GET" action="{{ route('aquarium.tank', $tankId) }}#equipment-search" role="search" aria-label="Katalog sprzętu" class="flex flex-wrap items-end gap-3 !mt-0">
            <input type="hidden" name="karta" value="wyposazenie">
            <label for="equipment-phrase" class="!m-0 min-w-0 flex-1 basis-48">
                Nazwa lub ID sprzętu
                <input id="equipment-phrase" type="search" name="szukaj" value="{{ $phrase }}" maxlength="100" class="min-h-11" required>
            </label>
            <x-button type="submit" variant="secondary">
                <x-heroicon-o-magnifying-glass class="icon" aria-hidden="true" /> Szukaj
            </x-button>
        </form>

        @if ($addErrors->has('equipment') || ($addErrors->any() && $results === []))
            <x-alert type="error" role="alert">{{ $addErrors->first('equipment') ?: $addErrors->first() }}</x-alert>
        @endif

        <div aria-live="polite">
            @if ($phrase !== '' && $results === [])
                <p>Brak wyników.</p>
            @endif
        </div>
        @if ($results !== [])
            <form method="POST" action="{{ route('aquarium.equipment.store', ['szukaj' => $phrase]) }}">
                @csrf
                <input type="hidden" name="tank" value="{{ $tankId }}">
                <fieldset @if ($addErrors->has('catalog_id')) aria-describedby="equipment-choice-error" @endif>
                    <legend>Wyniki wyszukiwania</legend>
                    <div class="mb-4 space-y-2">
                        @foreach ($results as $entry)
                            <label class="!m-0 flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2">
                                <input type="radio" name="catalog_id" value="{{ $entry->id }}" class="shrink-0" required
                                    @checked(old('catalog_id') === $entry->id)
                                    @if ($addErrors->has('catalog_id')) aria-invalid="true" @endif>
                                <span class="min-w-0 wrap-break-word">{{ $entry->name }} <span class="text-muted">· {{ $entry->id }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('catalog_id', 'equipment')
                        <span id="equipment-choice-error" role="alert" class="field-error">{{ $message }}</span>
                    @enderror
                </fieldset>
                <div class="flex flex-wrap items-end gap-3">
                    <label for="equipment-add-quantity" class="!m-0 w-24">
                        Ilość
                        <input id="equipment-add-quantity" type="number" name="quantity" min="1" max="{{ $maxQuantity }}" required
                            class="min-h-11" value="{{ is_scalar(old('quantity')) ? old('quantity') : 1 }}"
                            @if ($addErrors->has('quantity')) aria-invalid="true" aria-describedby="equipment-add-quantity-error" @endif>
                    </label>
                    <x-button type="submit">Dodaj sprzęt</x-button>
                </div>
                @error('quantity', 'equipment')
                    <span id="equipment-add-quantity-error" role="alert" class="field-error">{{ $message }}</span>
                @enderror
            </form>
        @endif
    </div>
</div>
