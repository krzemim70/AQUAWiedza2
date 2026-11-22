@extends('layouts.public')

@section('title', 'Moje akwarium — Aikwarium')

@section('content')
    <h1>Moje akwarium</h1>
    <p>Panel akwarysty w budowie.</p>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Wyloguj</button>
    </form>
@endsection
