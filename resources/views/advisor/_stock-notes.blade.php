@if (($stockNotes[$id] ?? 0) > 0)
    <p class="relative z-10 m-0 mt-1 inline-block rounded-full border border-line bg-tint px-3 py-1 text-sm font-semibold text-aqua-700">Uwagi dla Twojej obsady: {{ $stockNotes[$id] }}</p>
@endif
