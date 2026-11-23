@extends('layouts.public')

@section('title', $entry->name.' — Aikwarium')

@section('description', $metaDescription)
@section('image', $photo['url'] ?? '')

@php
    $jsonLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Strona główna', 'item' => route('home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $entry->area->label()],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $entry->name, 'item' => url()->current()],
    ]];
@endphp

@push('head')
    <script type="application/ld+json">@json($jsonLd)</script>
@endpush

@section('content')
    <nav aria-label="Ścieżka nawigacji" class="mb-6 text-sm text-muted">
        <ol class="m-0 flex list-none flex-wrap items-center gap-x-2 p-0">
            <li><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center">Strona główna</a></li>
            <li><span aria-hidden="true">›</span> Katalog</li>
            <li><span aria-hidden="true">›</span> {{ $entry->area->label() }}</li>
            <li aria-current="page"><span aria-hidden="true">›</span> {{ $entry->name }}</li>
        </ol>
    </nav>

    @if ($returnTo)
        <x-button variant="secondary" :href="$returnTo" class="mb-6">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wróć do wyników
        </x-button>
    @endif

    @include('auth._messages')

    <div class="catalog-product-grid">
        <div class="min-w-0">
            <h1 class="wrap-break-word">{{ $entry->name }}</h1>
            @if ($entry->nameLatin)
                <p class="latin-name text-lg" lang="la">{{ $entry->nameLatin }}</p>
            @endif
        </div>

        <figure class="catalog-product-photo m-0">
            @if ($photo)
                <img src="{{ $photo['url'] }}" alt="{{ $entry->name }}" decoding="async"
                    class="aspect-[4/3] w-full rounded-panel border border-line bg-tint object-contain">
                @if ($photo['author'] || $photo['licence'])
                    <figcaption class="mt-2 text-sm text-muted">
                        @if ($photo['author']) Autor zdjęcia: {{ $photo['author'] }} @endif
                        @if ($photo['author'] && $photo['licence']) · @endif
                        @if ($photo['licence']) Licencja: {{ $photo['licence'] }} @endif
                    </figcaption>
                @endif
            @else
                <img src="{{ asset('images/aikwarium-akwarium-800.jpg') }}" alt="Zdjęcie poglądowe akwarium z roślinami"
                    width="800" height="500" decoding="async" class="aspect-[4/3] w-full rounded-panel border border-line object-cover">
            @endif
        </figure>

        @if ($parameters)
            <x-card class="min-w-0">
                <h2>Parametry</h2>
                <dl class="!mb-0">
                    @foreach ($parameters as $label => $value)
                        <div>
                            <dt>{{ $label }}</dt>
                            <dd class="wrap-break-word">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
        @endif
    </div>

    <div class="mt-8">
        @include('advisor._card-actions', [
            'area' => $entry->area, 'id' => $entry->id, 'name' => $entry->name,
            'variant' => 'full', 'cardEntry' => $entry, 'productPage' => true,
            'nearbyOnly' => request()->query('oferty') === 'okolica',
        ])
    </div>
@endsection
