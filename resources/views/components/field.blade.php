@props(['name', 'label', 'as' => 'input', 'type' => 'text', 'hint' => null])
@php($id = 'f-'.str_replace('.', '-', $name))
<div class="field">
    <label for="{{ $id }}">{{ $label }}</label>
    @if ($as === 'select')
        <select id="{{ $id }}" wire:model="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror {{ $attributes }}>{{ $slot }}</select>
    @elseif ($as === 'textarea')
        <textarea id="{{ $id }}" wire:model="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror {{ $attributes }}></textarea>
    @else
        <input id="{{ $id }}" type="{{ $type }}" wire:model="{{ $name }}" @error($name) aria-invalid="true" aria-describedby="{{ $id }}-error" @enderror {{ $attributes }}>
    @endif
    @if ($hint)
        <small>{{ $hint }}</small>
    @endif
    @error($name)
        <p id="{{ $id }}-error" role="alert">{{ $message }}</p>
    @enderror
</div>
