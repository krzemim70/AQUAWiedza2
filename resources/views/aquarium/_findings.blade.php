{{-- Lista ustaleń zgodności z dowodem. Zmienna: $findings (list<Finding>). Ważność podana tekstem, nie samym kolorem. --}}
<ul class="m-0 list-none space-y-3 p-0">
    @foreach ($findings as $finding)
        <li class="rounded-xl border border-line bg-white p-4">
            <p class="m-0"><strong>{{ $finding->severity->label() }}.</strong> {{ $finding->text }}</p>
            <x-why :evidence="$finding->evidence" />
        </li>
    @endforeach
</ul>
