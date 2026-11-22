@if (session('status'))
    <x-alert type="success" role="status">{{ session('status') }}</x-alert>
@endif
@if ($errors->any())
    <ul role="alert">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
@endif
