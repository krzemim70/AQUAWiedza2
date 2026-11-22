<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ?? 'Moje akwarium — Aikwarium' }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <header>
            <nav aria-label="Moje akwarium">
                <a href="{{ route('aquarium.index') }}">Moje akwarium</a>
                <a href="{{ route('aquarium.redfield') }}">Kalkulator Redfielda</a>
                <a href="{{ route('advisor.tank') }}">Doradca</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Wyloguj</button>
                </form>
            </nav>
        </header>
        <main>{{ $slot }}</main>
    </body>
</html>
