<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Aikwarium')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <x-header />
    <main id="main" class="@yield('main', 'page-shell public-page')" tabindex="-1">@yield('content')</main>
    <x-footer />
</body>
</html>