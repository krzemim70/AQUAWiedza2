@extends('layouts.public')

@section('title', 'Kreator akwarium — Aikwarium')
@section('description', 'Kreator akwarium: dobierz litraż, ryby i bezkręgowce, rośliny, sprzęt oraz pielęgnację z porównaniem zgodności na podstawie bazy wiedzy Aikwarium.')
@section('main', 'creator-page')

@push('head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" href="{{ $bundleUrl }}" as="fetch" crossorigin>
    @livewireStyles
    @vite(['resources/js/creator.js'])
@endpush

@section('content')
    <div class="hero-glow" aria-hidden="true"></div>

    <header class="creator-header">
        <div class="creator-header-copy">
            <p class="eyebrow">Kreator akwarium</p>
            <h1>Zaplanuj swoje akwarium</h1>
            <p class="lead">Ułóż zestaw w sześciu krokach na podstawie danych katalogu Aikwarium. Szkic zapisuje się automatycznie w przeglądarce.</p>
        </div>

        @if ($hasCatalog)
            <div class="creator-header-actions" x-data>
                <span class="creator-tag tabular-nums">Wersja pakietu: {{ $bundleVersion }}</span>
            </div>
        @endif
    </header>

    @if (! $hasCatalog)
        <section class="empty-state" role="status" aria-live="polite">
            <x-heroicon-o-sparkles aria-hidden="true" />
            <h2>Katalog w przygotowaniu</h2>
            <p>Aktywna partia bazy wiedzy nie została jeszcze opublikowana. Wróć wkrótce, aby ułożyć obsadę i wyposażenie zbiornika.</p>
        </section>
    @else
        <div class="creator-layout" x-data='aquariumCreator(@json($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP))'>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div role="status" aria-live="polite" class="min-w-0 flex-1">
                    <template x-if="draftStatusNote">
                        <div class="creator-notice flex flex-wrap items-center justify-between gap-3">
                            <p class="m-0 text-sm" x-text="draftStatusNote"></p>
                            <div class="flex flex-wrap gap-2">
                                <template x-if="withdrawnItems.length > 0">
                                    <button type="button" class="creator-pill" @click="removeWithdrawnItems()">Usuń wycofane pozycje</button>
                                </template>
                                <button type="button" class="creator-pill" @click="draftStatusNote = null">Ukryj</button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <template x-if="!clearConfirmOpen">
                        <button type="button" class="creator-pill" @click="clearConfirmOpen = true">
                            <x-heroicon-o-trash class="icon" aria-hidden="true" /> Wyczyść szkic
                        </button>
                    </template>
                    <template x-if="clearConfirmOpen">
                        <div class="flex flex-wrap items-center gap-2 rounded-full border border-line bg-white px-3 py-1" role="region" aria-label="Potwierdzenie czyszczenia szkicu">
                            <span class="text-sm font-semibold text-ink">Wyczyścić szkic?</span>
                            <button type="button" class="creator-add-btn" @click="clearDraft()">Tak, wyczyść</button>
                            <button type="button" class="creator-pill" @click="clearConfirmOpen = false">Anuluj</button>
                        </div>
                    </template>
                </div>
            </div>

            <noscript>
                <div class="creator-notice creator-notice-warn" role="status">
                    <p>Kreator działa interaktywnie w przeglądarce i wymaga włączonego JavaScriptu. Możesz też skorzystać z <a href="{{ route('drop.show') }}">Szybkiego doboru ryb</a>.</p>
                </div>
            </noscript>

            <div x-show="loading" class="creator-notice" role="status" aria-live="polite">
                <p>Ładowanie katalogu gatunków, roślin, sprzętu i pielęgnacji…</p>
            </div>

            <div x-show="loadError" x-cloak class="creator-notice creator-notice-warn" role="alert">
                <p>Nie udało się pobrać pakietu katalogu. Sprawdź połączenie i spróbuj ponownie.</p>
                <button type="button" class="creator-add-btn mt-2" @click="loadBundle()">Spróbuj ponownie</button>
            </div>

            @include('creator._stepper')

            <div class="creator-workspace">
                <div class="creator-main-col">
                    @include('creator._step-tank')
                    @include('creator._step-fauna')
                    @include('creator._step-flora')
                    @include('creator._step-equipment')
                    @include('creator._step-care')
                    @include('creator._step-shopping')
                </div>

                @include('creator._live-preview')
            </div>
        </div>
    @endif

    @livewireScripts
@endsection
