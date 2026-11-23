<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('icons/aikwarium-icon.svg') }}">
    <meta name="theme-color" content="#176b68">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Aikwarium">
    <title>{{ $title ?? 'Moje akwarium — Aikwarium' }}</title>
    <meta name="robots" content="noindex">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <x-header />
    <main id="main" class="page-shell aquarist-page" tabindex="-1">{{ $slot }}</main>
    <x-footer />
</body>
</html>