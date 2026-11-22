<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} — Aikwarium</title>
    @if (Vite::isRunningHot() || Vite::manifestHash())
        <link rel="stylesheet" href="{{ Vite::asset('resources/css/app.css') }}">
    @endif
</head>
<body class="bg-white">
    <main class="m-auto w-full max-w-3xl px-4 py-12 sm:px-6">
        <x-card>
            <h1 class="text-balance">{{ $heading }}</h1>
            <p class="lead">{{ $description }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                @yield('action')
                <x-button :href="route('home')">Strona główna</x-button>
                <x-button variant="secondary" :href="route('page', 'kontakt')">Kontakt</x-button>
            </div>
        </x-card>
    </main>
</body>
</html>
