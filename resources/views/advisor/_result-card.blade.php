@php
    use App\Domain\Advisor\Engine\CardRole;
    use App\Domain\Advisor\Engine\Verification;
    use App\Domain\KnowledgeBase\Models\Area;
    use Illuminate\Support\Str;

    $unscored = $unscored ?? false;
    $checked = $card instanceof Verification;
    $entry = $entries[$card->area->value.':'.$card->id] ?? null;
    $photo = array_find($entry?->photos ?? [], fn ($photo) => filled($photo['url']));
    $credit = implode(' · ', array_filter([$photo['author'] ?? null, $photo['licence'] ?? null]));
    $role = $checked ? 'Sprawdzony produkt' : $card->role->label();
    $fit = $checked ? $card->verdict : ($card->fit ?? ($card->role === CardRole::Correction ? 'Ocena proponowanej korekty' : 'Zgodność z Twoimi warunkami'));
    $description = '';
    $fields = [];
    if ($full && $entry !== null) {
        $text = $entry->description ?: ($entry->fields['opis_krotki'] ?? $entry->fields['opis'] ?? '');
        $text = trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $sentences = preg_split('/(?<=[.!?])\s+(?=\p{Lu})/u', $text) ?: [];
        $description = Str::limit(implode(' ', array_slice($sentences, 0, 2)), 300, preserveWords: true);
        $fields = match ($entry->area) {
            Area::Fauna => ['Wielkość' => 'Wielkość', 'Strefa' => 'Strefa', 'Temperament' => 'Temperament', 'pH' => 'pH'],
            Area::Flora => ['Wielkość' => 'Wielkość', 'Strefa' => 'Strefa', 'CO2' => 'CO₂', 'Trudność' => 'Trudność'],
            Area::Equipment => ['kategoria_glowna' => 'Kategoria', 'rodzaj' => 'Rodzaj', 'Marka' => 'Marka', 'producent' => 'Producent'],
            Area::Care => ['kategoria_zadania' => 'Kategoria', 'rodzaj_zadania' => 'Działanie', 'obszar' => 'Zastosowanie', 'producent' => 'Producent'],
        };
    }
@endphp

<article @class(['card advisor-result-card', 'advisor-result-featured' => $full])>
    <figure>
        <img class="aspect-[8/5] w-full rounded-xl bg-tint object-cover"
            src="{{ $photo['url'] ?? asset('images/aikwarium-akwarium-800.jpg') }}"
            alt="{{ $photo ? $card->name : 'Zdjęcie zastępcze akwarium' }}"
            width="800" height="500" loading="lazy" decoding="async">
        @if ($credit !== '')
            <figcaption class="mt-2 text-sm text-muted">Zdjęcie: {{ $credit }}</figcaption>
        @endif
    </figure>
    <div class="flex min-w-0 flex-col items-start">
        @if (! $unscored)
            <span class="mb-2 text-sm font-semibold text-aqua-700">{{ $role }}</span>
        @endif
        @php($heading = $full ? 'h2' : 'h3')
        <{{ $heading }} class="advisor-card-name">
            <a class="advisor-card-link" href="{{ url('/katalog/'.$card->area->value.'/'.$card->id) }}">{{ $card->name }}</a>
        </{{ $heading }}>
        @if ($entry?->nameLatin && $entry->nameLatin !== $card->name)
            <p class="italic text-muted">{{ $entry->nameLatin }}</p>
        @endif

        <div class="advisor-fit">
            @if ($unscored)
                Ocena niedostępna
            @else
                <strong>{{ $card->percent }}%</strong> <span>{{ $fit }}</span>
            @endif
        </div>

        @if ($description !== '')
            <p class="text-text-2">{{ $description }}</p>
        @endif
        @if ($fields !== [])
            <dl class="advisor-parameters">
                @foreach ($fields as $key => $label)
                    @if (filled($entry->fields[$key] ?? null))
                        <div class="rounded-lg bg-tint px-3 py-2 text-sm">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="font-semibold">{{ $entry->fields[$key] }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        @endif

        @if ($card->reasons !== [] || $card->warnings !== [])
            @if ($full)
                <h3>{{ $checked ? 'Co pasuje i co wymaga uwagi' : 'Dlaczego pasuje' }}</h3>
            @endif
            @if ($card->reasons !== [])
                <ul class="mb-3 space-y-2 text-sm">
                    @foreach ($card->reasons as $reason)
                        <li class="flex gap-2">
                            <x-heroicon-o-check-circle class="size-5 shrink-0 text-aqua-700" aria-hidden="true" />
                            <span>{{ $reason }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($card->warnings !== [])
                <ul class="mb-3 space-y-2 text-sm">
                    @foreach ($card->warnings as $warning)
                        <li class="flex gap-2">
                            <x-heroicon-o-exclamation-triangle class="size-5 shrink-0 text-danger" aria-hidden="true" />
                            <span><strong>Uwaga:</strong> {{ $warning }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endif

        <div class="advisor-card-actions relative z-10 mt-auto w-full">
            @includeIf('advisor._card-actions', ['area' => $card->area, 'id' => $card->id, 'name' => $card->name, 'variant' => $full ? 'full' : 'compact'])
        </div>
    </div>
</article>
