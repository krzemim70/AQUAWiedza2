@extends('layouts.public')

@section('title', 'Zakończenie obserwacji — Aikwarium')

@push('head')
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
<div class="auth-card">
    <h1>Zakończenie obserwacji</h1>
    <p role="status" aria-live="polite">{{ $removed ? 'Obserwacja została zakończona.' : 'Link dotyczy nieaktywnej obserwacji.' }}</p>
    <x-button variant="secondary" :href="route('home')">Wróć na stronę główną</x-button>
</div>
@endsection
