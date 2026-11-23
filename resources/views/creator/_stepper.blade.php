@php
    $stepperItems = [
        [1, 'Zbiornik', null],
        [2, 'Ryby i bezkręgowce', 'fauna'],
        [3, 'Rośliny', 'flora'],
        [4, 'Sprzęt', 'equipment'],
        [5, 'Pielęgnacja', 'care'],
        [6, 'Lista zakupów', null],
    ];
@endphp

<nav class="creator-stepper" aria-label="Kroki kreatora akwarium">
    <ol class="creator-stepper-list">
        @foreach ($stepperItems as [$num, $label, $area])
            <li class="creator-stepper-item">
                <button
                    type="button"
                    class="creator-step-btn"
                    :aria-current="step === {{ $num }} ? 'step' : false"
                    @click="goToStep({{ $num }})"
                >
                    <span class="creator-step-num tabular-nums" aria-hidden="true">{{ $num }}</span>
                    <span>{{ $label }}</span>
                    @if ($area !== null)
                        <span
                            class="creator-step-badge tabular-nums"
                            x-show="selectedCountForArea('{{ $area }}') > 0"
                            x-cloak
                            x-text="selectedCountForArea('{{ $area }}')"
                            aria-hidden="true"
                        ></span>
                    @endif
                </button>
            </li>
        @endforeach
    </ol>
</nav>
