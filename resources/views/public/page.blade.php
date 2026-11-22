@extends('layouts.public')

@section('title', $title.' — Aikwarium')
@section('description', $title.' — Aikwarium. Ta strona jest w przygotowaniu.')

@section('content')
    <div class="page-hero">
        <h1>{{ $title }}</h1>
        <p class="lead">Ta strona jest w przygotowaniu. Wróć tu wkrótce.</p>
        <p><a href="{{ route('home') }}">Wróć na stronę główną</a></p>
    </div>
@endsection
