@extends('layouts.public')

@section('title', 'Uwagi przed dodaniem — Aikwarium')

@section('content')
    <h1>Uwagi przed dodaniem: {{ $name }}</h1>
    <p>Baza wiedzy wskazuje poniższe uwagi dla tego gatunku w Twoim zbiorniku. Decyzja należy do Ciebie.</p>
    @include('aquarium._findings', ['findings' => $report->findings])
    <form method="POST" action="{{ $action }}" class="mt-6 flex flex-wrap items-center gap-3">
        @csrf
        @foreach ($fields as $field => $value)
            @if ($value !== null)
                <input type="hidden" name="{{ $field }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="acknowledge" value="1">
        <x-button type="submit">Dodaj mimo to</x-button>
        <x-button variant="secondary" :href="$cancelUrl">Anuluj</x-button>
    </form>
    <p class="text-sm text-muted">Ocena opiera się wyłącznie na danych bazy wiedzy o gatunkach. Nie jest diagnozą i nie zastępuje porady specjalisty.</p>
@endsection
