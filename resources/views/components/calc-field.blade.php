@props(['name', 'label', 'value' => '', 'hint' => null, 'as' => 'input', 'srSuffix' => null])
@php
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $id = 'c-'.str_replace('.', '-', $key);
    $error = $errors->first($key);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="field">
    <label for="{{ $id }}">{{ $label }}@if ($srSuffix)<span class="sr-only"> {{ $srSuffix }}</span>@endif</label>
    @if ($as === 'select')
        <select id="{{ $id }}" name="{{ $name }}" @if ($error) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes }}>{{ $slot }}</select>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="text" autocomplete="off" value="{{ $value }}" @if ($error) aria-invalid="true" @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif {{ $attributes->merge(['inputmode' => 'decimal']) }}>
    @endif
    @if ($hint)
        <small id="{{ $id }}-hint">{{ $hint }}</small>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="field-error">{{ $error }}</p>
    @endif
</div>
