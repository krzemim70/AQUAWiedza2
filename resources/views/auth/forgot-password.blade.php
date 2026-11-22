@extends('layouts.public')

@section('title', 'Reset hasła — Aikwarium')

@section('content')
    <h1>Reset hasła</h1>
    @include('auth._messages')
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <p><label>E-mail <input type="email" name="email" value="{{ old('email') }}" required></label></p>
        <p><button type="submit">Wyślij link</button></p>
    </form>
@endsection
