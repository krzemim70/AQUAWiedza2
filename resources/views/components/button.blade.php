@props(['variant' => 'primary'])
<button {{ $attributes->class(['button', 'button-secondary' => $variant === 'secondary']) }}>{{ $slot }}</button>