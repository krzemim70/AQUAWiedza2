@extends('layouts.public')

@section('title', 'Oceń sklep — Aikwarium')

@section('content')
    <x-button :href="route('orders.show', $order->id)" variant="secondary" class="mb-6 min-h-11">
        <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wróć do zamówienia
    </x-button>
    <h1 class="wrap-break-word">Oceń sklep {{ $order->shop->name }}</h1>

    <x-card>
        <form method="POST" action="{{ route('orders.review.store', $order->id) }}" class="max-w-xl space-y-5">
            @csrf
            <div>
                <label for="rating">Ocena sklepu (wymagana)</label>
                <select id="rating" name="rating" required class="min-h-11 scroll-mt-24 focus-visible:outline-focus"
                    @error('rating') aria-invalid="true" aria-describedby="rating-error" @enderror>
                    <option value="">Wybierz ocenę</option>
                    @for ($score = 1; $score <= 5; $score++)
                        <option value="{{ $score }}" @selected(old('rating') == $score)>{{ $score }} z 5</option>
                    @endfor
                </select>
                @error('rating')
                    <p id="rating-error" class="text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="comment">Komentarz (opcjonalny)</label>
                <p id="comment-help" class="!mb-2 text-sm text-muted">Do {{ $maxLength }} znaków. Komentarz będzie publiczny. Pomiń dane osobowe.</p>
                @php($comment = old('comment'))
                <textarea id="comment" name="comment" rows="4" maxlength="{{ $maxLength }}"
                    class="scroll-mt-24 focus-visible:outline-focus" aria-describedby="comment-help{{ $errors->has('comment') ? ' comment-error' : '' }}"
                    @error('comment') aria-invalid="true" @enderror>{{ is_string($comment) ? $comment : '' }}</textarea>
                @error('comment')
                    <p id="comment-error" class="text-danger" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <x-button type="submit">Dodaj opinię</x-button>
        </form>
    </x-card>
@endsection
