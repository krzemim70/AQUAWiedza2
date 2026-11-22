<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Aikwarium')</title>
    {{-- Strona jest indeksowana tylko wtedy, gdy ma `@section('description')` i serwis jest indeksowalny (config seo.indexable). --}}
    @if (config('seo.indexable') && View::hasSection('description'))
        <meta name="description" content="@yield('description')">
        <link rel="canonical" href="{{ url()->current() }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="Aikwarium">
        <meta property="og:locale" content="pl_PL">
        <meta property="og:title" content="@yield('title', 'Aikwarium')">
        <meta property="og:description" content="@yield('description')">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:image" content="{{ url(View::yieldContent('image') ?: config('seo.default_image')) }}">
    @else
        <meta name="robots" content="noindex">
    @endif
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <x-header />
    <main id="main" class="@yield('main', 'page-shell public-page')" tabindex="-1">@yield('content')</main>
    <x-footer />
</body>
</html>
