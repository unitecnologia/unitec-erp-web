@php
    $kpis = $kpis ?? [];
@endphp

<section
    class="erp-dash__kpis"
    aria-label="Indicadores"
    style="--erp-kpi-cols: {{ max(1, count($kpis)) }};"
>
    @foreach ($kpis as $kpi)
        @php
            $hasAction = filled($kpi['action_url'] ?? null);
            $hasReport = filled($kpi['report_url'] ?? null);
            $tone = $kpi['tone'] ?? 'blue';
        @endphp

        @if ($hasAction)
            <a
                href="{{ $kpi['action_url'] }}"
                class="erp-dash-kpi erp-dash-kpi--link erp-dash-kpi--{{ $tone }}"
                target="_blank"
                rel="noopener noreferrer"
            >
                <span class="erp-dash-kpi__accent" aria-hidden="true"></span>
                <div class="erp-dash-kpi__icon-wrap">
                    <x-filament::icon :icon="$kpi['icon']" class="erp-dash-kpi__icon" />
                </div>
                <div class="erp-dash-kpi__body">
                    <p class="erp-dash-kpi__label">{{ $kpi['label'] }}</p>
                    <p class="erp-dash-kpi__value">{{ $kpi['value'] }}</p>
                    <p class="erp-dash-kpi__hint">{{ $kpi['hint'] ?? '' }}</p>
                    <span class="erp-dash-kpi__action">{{ $kpi['action_label'] ?? 'Clique aqui para renovar' }}</span>
                </div>
            </a>
        @else
            <article class="erp-dash-kpi erp-dash-kpi--{{ $tone }}">
                <span class="erp-dash-kpi__accent" aria-hidden="true"></span>
                @if ($hasReport)
                    <a
                        href="{{ $kpi['report_url'] }}"
                        class="erp-dash-kpi__report-btn"
                        target="_blank"
                        rel="noopener noreferrer"
                        title="{{ $kpi['report_title'] ?? 'Gerar relatório' }}"
                        aria-label="{{ $kpi['report_title'] ?? 'Gerar relatório' }}"
                    >
                        <x-filament::icon icon="heroicon-o-printer" />
                    </a>
                @endif

                <div class="erp-dash-kpi__icon-wrap">
                    <x-filament::icon :icon="$kpi['icon']" class="erp-dash-kpi__icon" />
                </div>
                <div class="erp-dash-kpi__body">
                    <p class="erp-dash-kpi__label">{{ $kpi['label'] }}</p>
                    <p class="erp-dash-kpi__value">{{ $kpi['value'] }}</p>
                    <p class="erp-dash-kpi__hint">{{ $kpi['hint'] }}</p>
                </div>
            </article>
        @endif
    @endforeach
</section>
