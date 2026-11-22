@extends('layouts.public')

@section('title', $title.' — Aikwarium')
@section('description', $title.' — Aikwarium. Ta strona jest w przygotowaniu.')

@section('content')
    <div class="page-hero">
        <h1>{{ $title }}</h1>
        <p class="lead">Ta strona jest w przygotowaniu. Wróć tu wkrótce.</p>
        <p><a href="{{ route('home') }}">Wróć na stronę główną</a></p>
    </div>
    @if ($cookies !== [])
        <section aria-labelledby="cookies-title" class="max-w-4xl">
            <x-card>
                <h2 id="cookies-title">Ciasteczka</h2>
                <p>Używamy ciasteczek do działania sesji, ochrony formularzy i obsługi limitu szybkiego doboru.</p>
                <table class="w-full table-fixed text-left text-sm sm:text-base">
                    <thead class="border-b border-line">
                        <tr>
                            <th scope="col" class="w-[34%] pb-2 pr-2">Nazwa</th>
                            <th scope="col" class="pb-2 pr-2">Cel</th>
                            <th scope="col" class="w-1/5 pb-2">Czas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line align-top">
                        @foreach ($cookies as [$name, $purpose, $duration])
                            <tr>
                                <th scope="row" class="py-3 pr-2 font-medium [overflow-wrap:anywhere]">{{ $name }}</th>
                                <td class="py-3 pr-2">{{ $purpose }}</td>
                                <td class="py-3">{{ $duration }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </section>
    @endif
@endsection
