@extends('errors.layout', request()->routeIs('verification.verify')
    ? ['heading' => 'Link do weryfikacji jest nieważny', 'description' => 'Poproś o nowy link do potwierdzenia adresu e-mail.']
    : ['heading' => 'Brak dostępu', 'description' => 'Nie masz dostępu do tej strony. Jeśli potrzebujesz pomocy, skontaktuj się z nami.'])

@section('action')
    @if (request()->routeIs('verification.verify'))
        <x-button :href="route('verification.notice')">Poproś o nowy link</x-button>
    @endif
@endsection
