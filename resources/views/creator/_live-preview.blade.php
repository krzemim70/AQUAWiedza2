<aside class="creator-preview-desktop" aria-label="Podgląd zbiornika na żywo">
    <div class="creator-preview-card">
        <div class="creator-preview-head">
            <h2 class="text-lg font-bold">Podgląd zbiornika</h2>
            <span class="creator-preview-badge tabular-nums" x-text="displayVolumeText"></span>
        </div>

        <div class="creator-svg-frame">
            <svg
                role="img"
                aria-label="Schemat wybranego zbiornika i stref"
                viewBox="0 0 320 200"
                class="creator-tank-svg"
            >
                <title>Schemat wybranego zbiornika i stref</title>
                <defs>
                    <linearGradient id="creator-water-grad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#6fd3c6" stop-opacity="0.28" />
                        <stop offset="100%" stop-color="#176b68" stop-opacity="0.45" />
                    </linearGradient>
                </defs>

                {{-- Woda --}}
                <rect
                    :x="svgLayout.x"
                    :y="svgLayout.waterY"
                    :width="svgLayout.w"
                    :height="svgLayout.waterH"
                    :rx="svgLayout.isCylinder ? 16 : 4"
                    fill="url(#creator-water-grad)"
                />

                {{-- Strefy wody: Górna, Środkowa, Dolna --}}
                @foreach ([0, 1, 2] as $zonePos)
                    <g>
                        <rect
                            :x="svgLayout.x"
                            :y="svgLayout.zones[{{ $zonePos }}].y"
                            :width="svgLayout.w"
                            :height="svgLayout.zones[{{ $zonePos }}].h"
                            :fill="svgLayout.zones[{{ $zonePos }}].count > 0 ? '#6fd3c6' : 'transparent'"
                            :fill-opacity="svgLayout.zones[{{ $zonePos }}].count > 0 ? '0.16' : '0'"
                        />
                        @if ($zonePos > 0)
                            <line
                                :x1="svgLayout.x + 6"
                                :y1="svgLayout.zones[{{ $zonePos }}].y"
                                :x2="svgLayout.x + svgLayout.w - 6"
                                :y2="svgLayout.zones[{{ $zonePos }}].y"
                                stroke="#a9c7c0"
                                stroke-opacity="0.3"
                                stroke-dasharray="3 4"
                            />
                        @endif
                        <text
                            :x="svgLayout.x + 10"
                            :y="svgLayout.zones[{{ $zonePos }}].y + svgLayout.zones[{{ $zonePos }}].h / 2 + 4"
                            font-size="11"
                            font-weight="600"
                            :fill="svgLayout.zones[{{ $zonePos }}].count > 0 ? '#6fd3c6' : '#a9c7c0'"
                            x-text="svgLayout.zones[{{ $zonePos }}].count > 0 ? svgLayout.zones[{{ $zonePos }}].label + ' (' + svgLayout.zones[{{ $zonePos }}].count + ')' : svgLayout.zones[{{ $zonePos }}].label"
                        ></text>
                        <circle
                            :cx="svgLayout.x + svgLayout.w - 14"
                            :cy="svgLayout.zones[{{ $zonePos }}].y + svgLayout.zones[{{ $zonePos }}].h / 2"
                            r="4"
                            fill="#6fd3c6"
                            x-show="svgLayout.zones[{{ $zonePos }}].count > 0"
                        />
                    </g>
                @endforeach

                {{-- Sylwetki roślin przy wybranej florze --}}
                <g x-show="svgLayout.hasFlora" fill="#6fd3c6" fill-opacity="0.42">
                    <path :d="'M ' + (svgLayout.x + svgLayout.w - 42) + ' ' + svgLayout.substrateY + ' q -8 -24 2 -40 q 8 18 -2 40 Z'" />
                    <path :d="'M ' + (svgLayout.x + svgLayout.w - 28) + ' ' + svgLayout.substrateY + ' q -6 -18 4 -30 q 6 14 -4 30 Z'" />
                </g>

                {{-- Podłoże --}}
                <rect
                    :x="svgLayout.x"
                    :y="svgLayout.substrateY"
                    :width="svgLayout.w"
                    :height="svgLayout.substrateH"
                    fill="#1f4a46"
                />

                {{-- Obrys szkła --}}
                <rect
                    :x="svgLayout.x"
                    :y="svgLayout.y"
                    :width="svgLayout.w"
                    :height="svgLayout.h"
                    :rx="svgLayout.isCylinder ? 18 : 6"
                    fill="none"
                    stroke="#6fd3c6"
                    stroke-width="2"
                />
            </svg>
        </div>

        <div class="creator-preview-status" role="status" aria-live="polite">
            <p class="m-0 font-semibold">
                <strong class="tabular-nums" x-text="totalSelectedSpecies"></strong>
                <span x-text="totalSelectedSpecies === 1 ? 'pozycja' : 'pozycji'"></span>
                (<span class="tabular-nums" x-text="totalSelectedUnits"></span> szt.)
            </p>
            <p class="mt-1 mb-0 text-xs text-dark-muted" x-text="compatibilityVerdictLabel"></p>
            <p class="mt-1 mb-0 text-xs text-dark-muted tabular-nums">
                Fauna: <span x-text="selectedCountForArea('fauna')"></span> ·
                Flora: <span x-text="selectedCountForArea('flora')"></span> ·
                Sprzęt: <span x-text="selectedCountForArea('equipment')"></span> ·
                Pielęgnacja: <span x-text="selectedCountForArea('care')"></span>
            </p>
        </div>

        <template x-if="items.length > 0">
            <ul class="creator-preview-list" aria-label="Wybrane pozycje w podglądzie">
                <template x-for="item in flatSelectedItems" :key="'preview-item-' + item.area + ':' + item.id">
                    <li class="creator-preview-row">
                        <div class="min-w-0 flex-1">
                            <div class="creator-preview-row-name font-semibold" x-text="item.name"></div>
                            <div class="creator-preview-row-muted tabular-nums" x-text="item.groupLabel + ' · ' + item.qty + ' szt.'"></div>
                        </div>
                        <button
                            type="button"
                            class="creator-preview-remove"
                            :aria-label="'Usuń z zestawu: ' + item.name"
                            @click="setQty(item.area, item.id, 0)"
                        >
                            <x-heroicon-o-x-mark class="icon" aria-hidden="true" />
                        </button>
                    </li>
                </template>
            </ul>
        </template>

        <div class="mt-4 flex flex-wrap gap-2" x-show="step !== 6">
            <button type="button" class="button button-light w-full" @click="goToStep(6)">
                Przejdź do podsumowania
            </button>
        </div>
    </div>
</aside>

{{-- Dolny pasek mobilny (< 960 px) --}}
<div class="creator-mobile-bar" role="region" aria-label="Podręczne podsumowanie kreatora">
    <div class="min-w-0">
        <div class="text-sm font-bold text-white tabular-nums" x-text="displayVolumeText"></div>
        <div class="text-xs text-dark-muted tabular-nums" x-text="totalSelectedSpecies + ' poz. · ' + totalSelectedUnits + ' szt.'"></div>
    </div>
    <button type="button" class="button button-light" @click="openMobileDialog()">
        Podgląd zestawu
    </button>
</div>

{{-- Szuflada mobilna (<dialog>) z pełnym podsumowaniem i obsługą Escape --}}
<dialog
    x-ref="previewDialog"
    class="creator-dialog"
    aria-labelledby="creator-dialog-title"
    @click="if ($event.target === $el) closeMobileDialog()"
>
    <div class="creator-dialog-body">
        <div class="creator-preview-head">
            <h2 id="creator-dialog-title" class="text-lg font-bold text-white">Podgląd zestawu</h2>
            <button
                type="button"
                class="creator-dialog-close"
                aria-label="Zamknij podgląd zestawu"
                @click="closeMobileDialog()"
            >
                <x-heroicon-o-x-mark class="icon" aria-hidden="true" />
            </button>
        </div>

        <p class="mt-0 mb-3 text-sm text-dark-muted tabular-nums" x-text="displayVolumeText + ' · ' + tank.type"></p>

        <div class="creator-preview-status">
            <p class="m-0 font-semibold">
                <strong class="tabular-nums" x-text="totalSelectedSpecies"></strong>
                <span x-text="totalSelectedSpecies === 1 ? 'pozycja' : 'pozycji'"></span>
                (<span class="tabular-nums" x-text="totalSelectedUnits"></span> szt.)
            </p>
            <p class="mt-1 mb-0 text-xs text-dark-muted" x-text="compatibilityVerdictLabel"></p>
        </div>

        <template x-if="items.length === 0">
            <p class="text-sm text-dark-muted">Nie dodano jeszcze żadnych pozycji do zestawu.</p>
        </template>

        <template x-if="items.length > 0">
            <ul class="creator-preview-list">
                <template x-for="item in flatSelectedItems" :key="'dlg-item-' + item.area + ':' + item.id">
                    <li class="creator-preview-row">
                        <div class="min-w-0 flex-1">
                            <div class="creator-preview-row-name font-semibold" x-text="item.name"></div>
                            <div class="creator-preview-row-muted tabular-nums" x-text="item.groupLabel + ' · ' + item.qty + ' szt.'"></div>
                        </div>
                        <button
                            type="button"
                            class="creator-preview-remove"
                            :aria-label="'Usuń z zestawu: ' + item.name"
                            @click="setQty(item.area, item.id, 0)"
                        >
                            <x-heroicon-o-x-mark class="icon" aria-hidden="true" />
                        </button>
                    </li>
                </template>
            </ul>
        </template>

        <div class="mt-4 flex flex-wrap gap-2">
            <button type="button" class="button button-light w-full" @click="closeMobileDialog(); goToStep(6)">
                Przejdź do podsumowania
            </button>
        </div>
    </div>
</dialog>
