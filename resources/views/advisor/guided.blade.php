@extends('layouts.public')

@section('title', 'Kreator doradcy — Aikwarium')
@php
    use App\Domain\Advisor\Engine\Question;
    use App\Domain\Advisor\Engine\Result;
    use App\Domain\Advisor\Engine\GroupType;

    $isQuestion = $step instanceof Question;
    $isEmpty = $step instanceof Result && $step->type === GroupType::Empty;
    $compact = $isQuestion && $backUrl !== null;
    $count = count($profile);
    $profileUnit = $count === 1 ? 'parametr' : (in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) ? 'parametry' : 'parametrów');
@endphp

@section('main', $compact ? 'page-shell advisor-shell advisor-shell-compact' : 'page-shell advisor-shell')

@section('content')
    <div @class(['mx-auto max-w-3xl' => $isQuestion])>
        @unless ($compact)
            <header class="max-w-3xl">
                @if ($isQuestion)
                    <h1>Dobierzmy ryby do Twojego akwarium</h1>
                    <p class="lead">Jedno pytanie na raz. Możesz też dobrać rośliny, sprzęt lub środki do pielęgnacji.</p>
                @else
                    <h1 id="advisor-step" tabindex="-1">Twój wynik</h1>
                @endif
            </header>
        @endunless

        @if (session('advisorStatus'))
            <p class="advisor-feedback" role="status">{{ session('advisorStatus') }}</p>
        @endif
        @if (session('advisorRejection'))
            <p class="advisor-feedback" role="alert">{{ session('advisorRejection') }}</p>
        @endif

        @if ($isQuestion)
            @include('advisor._question')
        @elseif ($step instanceof Result)
            @include('advisor._result', ['result' => $step])
        @else
            @include('advisor._verification', ['verification' => $step])
        @endif

        <nav class="mt-8 flex flex-wrap gap-3" aria-label="Kroki kreatora">
            @if (! $isQuestion && $backUrl && ! $isEmpty)
                <x-button variant="secondary" :href="$backUrl"><x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz</x-button>
            @endif
            @if (! $isQuestion)
                <x-button :href="$nextAreaUrl">Dobierz kolejny obszar</x-button>
            @endif
            <x-button variant="secondary" :href="route('advisor.guided')">Zacznij od nowa</x-button>
        </nav>

        <details class="advisor-details mt-6">
            <summary>Twój profil · {{ $count }} {{ $profileUnit }}</summary>
            @if ($profile === [])
                <p>Tu znajdziesz odpowiedzi udzielone w tym przebiegu.</p>
            @else
                <dl class="advisor-profile">
                    @foreach ($profile as $row)
                        <div class="border-t border-line py-3">
                            <dt class="text-sm text-text-2">{{ $row['question'] }}</dt>
                            <dd class="font-semibold">{{ $row['answer'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </details>
    </div>

    <script>
        document.getElementById('advisor-step')?.focus({ preventScroll: true });
    </script>
@endsection
