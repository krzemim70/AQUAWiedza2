@props(['evidence' => []])
@if (count($evidence) > 0)
<details {{ $attributes->class(['why']) }}>
    <summary>Dlaczego?</summary>
    <ul>
        @foreach ($evidence as $item)
            <li>{{ $item->text() }}@if ($item->batch !== null) <small>(partia bazy {{ $item->batch }})</small>@endif</li>
        @endforeach
    </ul>
</details>
@endif
