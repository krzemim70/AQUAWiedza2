@extends('layouts.public')

@section('title', 'Rejestracja — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Załóż konto</h1>
    <ul>
        <li><a href="{{ route('register.create', 'akwarysta') }}">Jestem akwarystą</a></li>
        <li><a href="{{ route('register.create', 'sklep') }}">Reprezentuję sklep</a></li>
    </ul>
    <p>Masz już konto? <a href="{{ route('login') }}">Zaloguj się</a></p>
</div>
@endsection
