@extends('layouts.public')

@section('title', 'Zgody — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Zaakceptuj aktualne dokumenty</h1>
    <p>Aby korzystać z serwisu, potwierdź zapoznanie się z aktualnymi dokumentami.</p>
    @include('auth._messages')
    <form method="POST" action="{{ route('consents') }}">
        @csrf
        <p><label><input type="checkbox" name="terms" value="1" required> Akceptuję regulamin</label></p>
        <p><label><input type="checkbox" name="privacy" value="1" required> Zapoznałem(-am) się z polityką prywatności</label></p>
        <p><label><input type="checkbox" name="adult" value="1" required> Oświadczam, że jestem pełnoletni(-a)</label></p>
        <p><label><input type="checkbox" name="newsletter" value="1"> Chcę otrzymywać informacje e-mail (opcjonalnie, można wycofać w każdej chwili)</label></p>
        <p><button type="submit">Zapisz i przejdź dalej</button></p>
    </form>
</div>
@endsection
