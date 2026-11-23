@if ($result)
    @if ($result->details['net'] !== null)
        <p><strong>Po odjęciu: {{ \App\Domain\Aquarium\Data\CalculatorResult::fmt($result->details['net'], 2) }} l</strong></p>
    @endif
    <p>To pasmo „{{ $band }}”. Pasmo zbiornika ustawiasz sam w jego ustawieniach.</p>
    @if ($saved)
        <p role="status">Zapisano litraż {{ $saved }} w zbiorniku.</p>
    @endif
    @auth
        @if ($tanks !== [])
            <form method="POST" action="{{ route('calculators.volume.save') }}">
                @csrf
                @foreach (['shape', 'length', 'width', 'diameter', 'height', 'deduct'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $in[$field] ?? '' }}">
                @endforeach
                <x-calc-field as="select" name="tank" label="Zapisz litraż w zbiorniku">
                    @foreach ($tanks as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </x-calc-field>
                <button type="submit">Zapisz {{ round($result->details['save']) }} l w zbiorniku</button>
            </form>
        @endif
    @endauth
@endif
