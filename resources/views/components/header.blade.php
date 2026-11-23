<a class="skip-link" href="#main">Przejdź do treści</a>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true"><x-heroicon-o-sparkles /></span>Aikwarium</a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" data-nav-toggle>
            <span class="sr-only">Menu</span>
            <x-heroicon-o-bars-3 class="icon-open" aria-hidden="true" />
            <x-heroicon-o-x-mark class="icon-close" aria-hidden="true" />
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Nawigacja główna">
            @auth
                <a class="advisor-nav-link" href="{{ route('advisor') }}" @if(request()->routeIs('advisor', 'advisor.*')) aria-current="page" @endif>Doradca</a>
                <a href="{{ route('creator.show') }}" @if(request()->routeIs('creator.*')) aria-current="page" @endif>Kreator</a>
                <a href="{{ route('aquarium.index') }}" @if(request()->routeIs('aquarium.index', 'aquarium.tank', 'plans.*')) aria-current="page" @endif>Moje akwarium</a>
                <a href="{{ route('calculators.index') }}" @if(request()->routeIs('calculators.*')) aria-current="page" @endif>Kalkulatory</a>
                <a href="{{ route('cart.show') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif>Koszyk</a>
                <a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>Moje zamówienia</a>
                <a href="{{ route('aquarium.redfield') }}" @if(request()->routeIs('aquarium.redfield')) aria-current="page" @endif>Kalkulator Redfielda</a>
                <a href="{{ route('account') }}" @if(request()->routeIs('account*')) aria-current="page" @endif>Moje konto</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit">Wyloguj</button></form>
            @else
                <a class="advisor-nav-link" href="{{ route('drop.show') }}" @if(request()->routeIs('drop.*')) aria-current="page" @endif>Doradca</a>
                <a href="{{ route('creator.show') }}" @if(request()->routeIs('creator.*')) aria-current="page" @endif>Kreator</a>
                <a href="{{ route('calculators.index') }}" @if(request()->routeIs('calculators.*')) aria-current="page" @endif>Kalkulatory</a>
                <a href="{{ route('page', 'dla-sklepow') }}" @if(request()->is('dla-sklepow')) aria-current="page" @endif>Dla sklepów</a>
                <a href="{{ route('login') }}">Zaloguj się</a>
                <a class="nav-signup" href="{{ route('register') }}">Załóż konto</a>
            @endauth
        </nav>
    </div>
</header>
