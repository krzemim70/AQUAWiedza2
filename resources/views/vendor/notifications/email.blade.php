<x-mail::message>
<x-slot:title>{{ $subject }}</x-slot:title>

<h1>{{ $greeting ?: $subject }}</h1>

@foreach ($introLines as $line)
<p>{{ $line }}</p>

@endforeach
@isset($actionText)
<x-mail::button :url="$actionUrl" align="left">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
<p>{{ $line }}</p>

@endforeach
@isset($unsubscribeUrl)
<x-mail::button :url="$unsubscribeUrl" align="left" color="secondary">
Zakończ tę obserwację
</x-mail::button>
@endisset
@if ($salutation)
<p>{{ $salutation }}</p>
@endif
</x-mail::message>
