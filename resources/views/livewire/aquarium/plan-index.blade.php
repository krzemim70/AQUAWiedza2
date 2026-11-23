<div>
    <p><a href="{{ route('aquarium.index') }}">&larr; Moje akwarium</a></p>
    <h1>Plan nowego akwarium</h1>
    <p>Zapisz wielkość i typ zbiornika oraz gatunki, które rozważasz. Plan porównuje je według danych katalogu, a po zakupie zamienia się w zbiornik z obsadą.</p>

    <section aria-labelledby="plans-heading">
        <h2 id="plans-heading">Twoje plany</h2>
        @forelse ($plans as $plan)
            <x-card class="mb-4" wire:key="plan-{{ $plan->id }}">
                <h3><a href="{{ route('plans.show', $plan->id) }}">{{ $plan->name }}</a></h3>
                <p>
                    {{ $plan->type->value }}, {{ $plan->size_band->label() }}@if ($plan->liters) ({{ $plan->liters }} l)@endif
                    · pozycji: {{ $plan->items_count }}
                    · {{ $plan->state === \App\Domain\Aquarium\Models\TankPlan::CONVERTED ? 'zamieniony w zbiornik' : 'w przygotowaniu' }}
                </p>
                <x-button type="button" variant="secondary" wire:click="delete({{ $plan->id }})" wire:confirm="Usunąć plan „{{ $plan->name }}”? Zbiornik utworzony z planu zostanie.">Usuń plan</x-button>
            </x-card>
        @empty
            <p>Nie masz jeszcze żadnego planu.</p>
        @endforelse
    </section>

    <section aria-labelledby="new-plan-heading">
        <h2 id="new-plan-heading">Nowy plan</h2>
        <div class="alert flex flex-wrap items-center justify-between gap-3">
            <p class="m-0">Wolisz dobrać obsadę, rośliny i sprzęt krok po kroku? <a href="{{ route('creator.show') }}">Otwórz kreator nowego akwarium</a></p>
        </div>
        @error('form')
            <x-alert type="error" role="alert">{{ $message }}</x-alert>
        @enderror
        <form wire:submit="create">
            <x-field name="name" label="Nazwa planu" maxlength="100" required />
            <x-field name="type" label="Typ akwarium" as="select" required>
                <option value="">Wybierz typ</option>
                @foreach ($types as $kind)
                    <option value="{{ $kind->value }}">{{ $kind->value }}</option>
                @endforeach
            </x-field>
            <x-field name="size_band" label="Wielkość zbiornika" as="select" hint="Wymagana, jeśli nie podasz litrażu.">
                <option value="">Wybierz wielkość</option>
                @foreach ($bands as $band)
                    <option value="{{ $band->value }}">{{ $band->label() }}</option>
                @endforeach
            </x-field>
            <x-field name="liters" type="number" label="Litraż (opcjonalnie)" min="1" inputmode="numeric" hint="Po podaniu litrażu wielkość zbiornika wynika z jego progów w katalogu." />
            <x-button type="submit" wire:loading.attr="disabled">Utwórz plan</x-button>
        </form>
    </section>
</div>
