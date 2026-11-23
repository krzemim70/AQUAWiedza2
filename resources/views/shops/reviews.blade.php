@extends('layouts.public')

@section('title', 'Opinie o sklepie '.$shop->name.' — Aikwarium')

@section('content')
    <h1 class="wrap-break-word">Opinie o sklepie {{ $shop->name }}</h1>
    <x-shop-rating :average="$rating->average" :count="$rating->count" />

    <div class="mt-6">
        @if ($reviews->isEmpty())
            <p>Brak opublikowanych komentarzy.</p>
        @else
            <ul class="!m-0 list-none space-y-4 !p-0">
                @foreach ($reviews as $review)
                    <li>
                        <x-card>
                            <p class="flex flex-wrap items-center gap-x-3">
                                <strong>Ocena {{ $review->rating }} z 5</strong>
                                <time class="text-sm text-muted" datetime="{{ $review->created_at->toAtomString() }}">
                                    {{ $review->created_at->timezone(config('app.display_timezone'))->format('d.m.Y') }}
                                </time>
                            </p>
                            <p class="!mb-0 whitespace-pre-wrap wrap-anywhere">{{ $review->comment }}</p>
                        </x-card>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($reviews->hasPages())
            <nav aria-label="Strony opinii" class="mt-6 flex flex-wrap gap-3">
                @if ($reviews->previousPageUrl())
                    <x-button variant="secondary" :href="$reviews->previousPageUrl()">Poprzednia strona</x-button>
                @endif
                @if ($reviews->nextPageUrl())
                    <x-button variant="secondary" :href="$reviews->nextPageUrl()">Następna strona</x-button>
                @endif
            </nav>
        @endif
    </div>
@endsection
