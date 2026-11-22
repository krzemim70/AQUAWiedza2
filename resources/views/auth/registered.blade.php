@extends('layouts.public')

@section('title', 'Sprawdź skrzynkę — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Sprawdź skrzynkę</h1>
    <p>Jeśli podany adres można zarejestrować, wysłaliśmy na niego link potwierdzający. Po potwierdzeniu możesz się zalogować.</p>
    <p><a href="{{ route('verification.notice') }}">Nie dotarł link?</a></p>
</div>
@endsection
