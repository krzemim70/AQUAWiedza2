<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true"><x-heroicon-o-sparkles /></span>Aikwarium</a>
        <nav class="site-nav" aria-label="Nawigacja główna">
            @auth
                <a href="{{ route('advisor.tank') }}" @if(request()->routeIs('advisor.*')) aria-current="page" @endif>Doradca</a>
                <a href="{{ route('aquarium.index') }}" @if(request()->routeIs('aquarium.index', 'aquarium.tank')) aria-current="page" @endif>Moje akwarium</a>
                <a href="{{ route('cart.show') }}" @if(request()->routeIs('cart.*')) aria-current="page" @endif>Koszyk</a>
                <a href="{{ route('orders.index') }}" @if(request()->routeIs('orders.*')) aria-current="page" @endif>Moje zamówienia</a>
                <a href="{{ route('aquarium.redfield') }}" @if(request()->routeIs('aquarium.redfield')) aria-current="page" @endif>Kalkulator Redfielda</a>
                <a href="{{ route('account') }}" @if(request()->routeIs('account*')) aria-current="page" @endif>Moje konto</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="nav-logout" type="submit">Wyloguj</button></form>
            @else
                <a href="{{ route('login') }}">Zaloguj się</a>
                <a class="nav-signup" href="{{ route('register') }}">Załóż konto</a>
            @endauth
        </nav>
    </div>
</header>