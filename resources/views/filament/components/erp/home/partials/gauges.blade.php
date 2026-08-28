@php
    $gauges = $gauges ?? [];
    $sellerGauges = $sellerGauges ?? [];
    $topSellerGauges = array_slice($sellerGauges, 0, 4);
    $gaugesCount = count($gauges);
@endphp

@if ($gaugesCount || count($sellerGauges))
<section
    class="erp-dash__gauges-row{{ count($sellerGauges) ? '' : ' erp-dash__gauges-row--no-sellers' }}"
    aria-label="Indicadores"
>
    @if (count($sellerGauges))
        <aside
            class="erp-dash__seller-panel"
            aria-label="Meta dos vendedores"
            x-data="{ open: false }"
            @keydown.escape.window="if (open) open = false"
        >
            <header class="erp-dash__seller-panel-head">
                <h2 class="erp-dash__seller-panel-title">Meta Vendedores</h2>
                @if (count($sellerGauges) > 4)
                    <button
                        type="button"
                        class="erp-dash__seller-panel-btn"
                        @click="open = true; $nextTick(() => window.erpDashRefreshGauges?.())"
                        title="Ver todos os vendedores"
                    >Todos</button>
                @endif
            </header>
            <div class="erp-dash__seller-panel-body">
                @foreach ($topSellerGauges as $gauge)
                    @php
                        $tone = $gauge['tone'] ?? 'slate';
                        $percent = max(0, (float) ($gauge['percent'] ?? 0));
                    @endphp
                    <article
                        class="erp-dash-gauge erp-dash-gauge--seller erp-dash-gauge--{{ $tone }}"
                        data-erp-gauge
                        data-percent="{{ $percent }}"
                        title="{{ ($gauge['full_name'] ?? $gauge['label'] ?? '') }} · {{ $gauge['meta_label'] ?? '' }}"
                    >
                        <div class="erp-dash-gauge__stats erp-dash-gauge__stats--sm">
                            <div class="erp-dash-gauge__stat">
                                <span>{{ $gauge['stat_left_label'] ?? 'Meta' }}</span>
                                <strong>{{ $gauge['stat_left'] ?? '—' }}</strong>
                            </div>
                            <div class="erp-dash-gauge__stat">
                                <span>{{ $gauge['stat_right_label'] ?? 'Real' }}</span>
                                <strong>{{ $gauge['stat_right'] ?? '—' }}</strong>
                            </div>
                        </div>
                        <div class="erp-dash-gauge__meter erp-dash-gauge__meter--sm" aria-hidden="true">
                            <canvas
                                class="erp-dash-gauge__canvas"
                                data-erp-gauge-canvas
                                data-compact="1"
                                data-scale="seller"
                                data-percent="{{ $percent }}"
                                data-tone="{{ $tone }}"
                                width="96"
                                height="64"
                            ></canvas>
                            <div class="erp-dash-gauge__pct erp-dash-gauge__pct--sm">{{ $gauge['display_percent'] ?? '0%' }}</div>
                        </div>
                        <p class="erp-dash-gauge__seller-name">{{ $gauge['label'] ?? '—' }}</p>
                    </article>
                @endforeach
            </div>

            <template x-teleport="body">
                <div
                    class="erp-dash-sellers-modal"
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="erp-dash-sellers-modal-title"
                >
                    <div class="erp-dash-sellers-modal__backdrop" @click="open = false"></div>
                    <div class="erp-dash-sellers-modal__window" @click.stop>
                        <div class="erp-dash-sellers-modal__titlebar">
                            <span id="erp-dash-sellers-modal-title">Meta Vendedores — todos</span>
                            <button
                                type="button"
                                class="erp-dash-sellers-modal__close"
                                @click="open = false"
                                title="Fechar"
                            >✕</button>
                        </div>
                        <div class="erp-dash-sellers-modal__body">
                            @foreach ($sellerGauges as $gauge)
                                @php
                                    $tone = $gauge['tone'] ?? 'slate';
                                    $percent = max(0, (float) ($gauge['percent'] ?? 0));
                                @endphp
                                <article
                                    class="erp-dash-gauge erp-dash-gauge--seller-lg erp-dash-gauge--{{ $tone }}"
                                    data-erp-gauge
                                    data-percent="{{ $percent }}"
                                    title="{{ ($gauge['full_name'] ?? $gauge['label'] ?? '') }} · {{ $gauge['meta_label'] ?? '' }}"
                                >
                                    <header class="erp-dash-gauge__head">
                                        <h3 class="erp-dash-gauge__title">{{ $gauge['full_name'] ?? $gauge['label'] ?? '—' }}</h3>
                                    </header>
                                    <div class="erp-dash-gauge__body">
                                        <div class="erp-dash-gauge__stats">
                                            <div class="erp-dash-gauge__stat">
                                                <span>{{ $gauge['stat_left_label'] ?? 'Meta' }}</span>
                                                <strong>{{ $gauge['stat_left'] ?? '—' }}</strong>
                                            </div>
                                            <div class="erp-dash-gauge__stat">
                                                <span>{{ $gauge['stat_right_label'] ?? 'Real' }}</span>
                                                <strong>{{ $gauge['stat_right'] ?? '—' }}</strong>
                                            </div>
                                        </div>
                                        <div class="erp-dash-gauge__meter" aria-hidden="true">
                                            <canvas
                                                class="erp-dash-gauge__canvas"
                                                data-erp-gauge-canvas
                                                data-scale="seller"
                                                data-percent="{{ $percent }}"
                                                data-tone="{{ $tone }}"
                                                width="180"
                                                height="118"
                                            ></canvas>
                                            <div class="erp-dash-gauge__pct">{{ $gauge['display_percent'] ?? '0%' }}</div>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </div>
            </template>
        </aside>
    @endif

    @if ($gaugesCount)
        <div
            class="erp-dash__gauges erp-dash__gauges--n{{ $gaugesCount }}"
            style="--erp-gauge-cols: {{ max(1, $gaugesCount) }};"
            x-data="{ healthOpen: false }"
            @keydown.escape.window="if (healthOpen) healthOpen = false"
        >
                @foreach ($gauges as $gauge)
                @php
                    $tone = $gauge['tone'] ?? 'slate';
                    $percent = (float) ($gauge['percent'] ?? 0);
                    $needle = max(0, $percent);
                    $clickable = (bool) ($gauge['clickable'] ?? false);
                    $isHealth = ($gauge['key'] ?? '') === 'saude_empresa';
                    $factors = is_array($gauge['detail']['factors'] ?? null) ? $gauge['detail']['factors'] : [];
                @endphp
                <article
                    @class([
                        'erp-dash-gauge',
                        'erp-dash-gauge--'.$tone,
                        'erp-dash-gauge--clickable' => $clickable,
                        'erp-dash-gauge--health' => $isHealth,
                    ])
                    data-erp-gauge
                    data-percent="{{ $needle }}"
                    @if ($isHealth)
                        role="button"
                        tabindex="0"
                        title="Clique para ver o detalhe da nota"
                        @click="healthOpen = true; $nextTick(() => window.erpDashRefreshGauges?.())"
                        @keydown.enter.prevent="healthOpen = true"
                        @keydown.space.prevent="healthOpen = true"
                    @endif
                >
                    <header class="erp-dash-gauge__head">
                        <h2 class="erp-dash-gauge__title">{{ $gauge['label'] }}</h2>
                    </header>
                    <div class="erp-dash-gauge__body">
                        <div class="erp-dash-gauge__stats">
                            <div class="erp-dash-gauge__stat">
                                <span>{{ $gauge['stat_left_label'] ?? 'Meta' }}</span>
                                <strong>{{ $gauge['stat_left'] ?? '—' }}</strong>
                            </div>
                            <div @class([
                                'erp-dash-gauge__stat',
                                'erp-dash-gauge__stat--'.($gauge['stat_right_tone'] ?? '') => filled($gauge['stat_right_tone'] ?? null),
                            ])>
                                <span>{{ $gauge['stat_right_label'] ?? 'Real' }}</span>
                                <strong>{{ $gauge['stat_right'] ?? '—' }}</strong>
                            </div>
                        </div>
                        <div class="erp-dash-gauge__meter" aria-hidden="true">
                            <canvas
                                class="erp-dash-gauge__canvas"
                                data-erp-gauge-canvas
                                data-percent="{{ $needle }}"
                                data-tone="{{ $tone }}"
                                width="160"
                                height="104"
                            ></canvas>
                            <div class="erp-dash-gauge__pct">{{ $gauge['display_percent'] ?? '0%' }}</div>
                        </div>
                        @if ($isHealth && filled($gauge['meta_label'] ?? null))
                            <p class="erp-dash-gauge__health-msg">{{ $gauge['meta_label'] }}</p>
                        @endif
                    </div>
                </article>
                @endforeach

            @php
                $healthGauge = collect($gauges)->firstWhere('key', 'saude_empresa');
                $healthFactors = is_array($healthGauge['detail']['factors'] ?? null) ? $healthGauge['detail']['factors'] : [];
                $healthMessage = (string) ($healthGauge['detail']['message'] ?? $healthGauge['meta_label'] ?? '');
                $healthStatus = (string) ($healthGauge['detail']['status'] ?? $healthGauge['value_label'] ?? '');
                $healthPercent = (string) ($healthGauge['display_percent'] ?? '');
            @endphp

            @if ($healthGauge)
                <template x-teleport="body">
                    <div
                        class="erp-dash-health-modal"
                        x-show="healthOpen"
                        x-cloak
                        x-transition.opacity
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="erp-dash-health-modal-title"
                    >
                        <div class="erp-dash-health-modal__backdrop" @click="healthOpen = false"></div>
                        <div class="erp-dash-health-modal__window" @click.stop>
                            <div class="erp-dash-health-modal__titlebar">
                                <span id="erp-dash-health-modal-title">{{ $healthGauge['detail']['modal_title'] ?? (($healthGauge['label'] ?? 'Saúde da Empresa').' — detalhe') }}</span>
                                <button
                                    type="button"
                                    class="erp-dash-health-modal__close"
                                    @click="healthOpen = false"
                                    title="Fechar"
                                >✕</button>
                            </div>
                            <div class="erp-dash-health-modal__summary">
                                <strong>{{ $healthPercent }}</strong>
                                <span>{{ $healthStatus }}</span>
                                <p>{{ $healthMessage }}</p>
                            </div>
                            <div class="erp-dash-health-modal__body">
                                @foreach ($healthFactors as $factor)
                                    @php
                                        $fTone = $factor['tone'] ?? 'slate';
                                        $fPct = max(0, min(100, (float) ($factor['percent'] ?? 0)));
                                    @endphp
                                    <div class="erp-dash-health-factor erp-dash-health-factor--{{ $fTone }}">
                                        <div class="erp-dash-health-factor__row">
                                            <span class="erp-dash-health-factor__label">{{ $factor['label'] ?? '—' }}</span>
                                            <span class="erp-dash-health-factor__pct">{{ number_format($fPct, 1, ',', '') }}%</span>
                                            <span class="erp-dash-health-factor__weight">peso {{ (int) ($factor['weight'] ?? 0) }}</span>
                                        </div>
                                        <div class="erp-dash-health-factor__bar" aria-hidden="true">
                                            <span style="width: {{ $fPct }}%;"></span>
                                        </div>
                                        @if (filled($factor['hint'] ?? null))
                                            <p class="erp-dash-health-factor__hint">{{ $factor['hint'] }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
    @endif
</section>
@endif
