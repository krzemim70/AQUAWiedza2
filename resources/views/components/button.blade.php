@props(['variant' => 'primary', 'size' => 'md', 'href' => null])
@php($classes = ['button', 'button-secondary' => $variant === 'secondary', 'button-ghost' => $variant === 'ghost', 'button-light' => $variant === 'light', 'button-outline-light' => $variant === 'outline-light', 'button-lg' => $size === 'lg'])
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
<button {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
