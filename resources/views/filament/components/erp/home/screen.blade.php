@php
    $dash = $this->dashboardData;
    $visao = $dash['visao'] ?? \App\Support\Erp\Dashboard\ErpDashboardScope::VISAO_EMPRESA;
@endphp

<div
    wire:key="erp-dash-{{ $visao }}"
    @class([
        'erp-dash',
        'erp-dash--has-visao' => $this->showDashboardVisaoToggle,
    ])
>
    @if ($this->showDashboardVisaoToggle)
        <div class="erp-dash__visao-bar" role="group" aria-label="Visão do dashboard">
            @if (! empty($dash['visaoLabel']))
                <span class="erp-dash__visao-label" role="status">{{ $dash['visaoLabel'] }}</span>
            @endif

            <div class="erp-dash__visao-toggle">
                <button
                    type="button"
                    wire:click="setDashboardVisao('empresa')"
                    @class(['erp-dash__visao-btn', 'is-active' => $visao === 'empresa'])
                    @if ($visao === 'empresa') aria-pressed="true" @endif
                >
                    Empresa
                </button>
                <button
                    type="button"
                    wire:click="setDashboardVisao('grupo')"
                    @class(['erp-dash__visao-btn', 'is-active' => $visao === 'grupo'])
                    @if ($visao === 'grupo') aria-pressed="true" @endif
                >
                    Grupo
                </button>
            </div>
        </div>
    @endif

    @include('filament.components.erp.home.partials.kpi-cards', [
        'kpis' => $dash['kpis'],
    ])

    <div wire:init="loadDashboardHeavy">
        @if ($this->dashboardHeavyReady)
            @include('filament.components.erp.home.partials.gauges', [
                'gauges' => $dash['gauges'] ?? [],
                'sellerGauges' => $dash['sellerGauges'] ?? [],
                'visao' => $visao,
            ])

            <div class="erp-dash__layout">
                <div class="erp-dash__main">
                    @include('filament.components.erp.home.partials.charts', [
                        'salesChart' => $dash['salesChart'],
                        'cashflowChart' => $dash['cashflowChart'],
                        'salesMixChart' => $dash['salesMixChart'] ?? [],
                        'fiscalDocsChart' => $dash['fiscalDocsChart'] ?? [],
                        'paymentMethodsChart' => $dash['paymentMethodsChart'] ?? [],
                        'visao' => $visao,
                    ])

                    <div class="erp-dash__sales-row">
                        @include('filament.components.erp.home.partials.sales-list', [
                            'recentSales' => $dash['recentSales'],
                        ])

                        @include('filament.components.erp.home.partials.highlights', [
                            'highlights' => $dash['highlights'] ?? [],
                        ])
                    </div>
                </div>

                @include('filament.components.erp.home.partials.alerts-sidebar', [
                    'alerts' => $dash['alerts'],
                ])
            </div>
        @endif
    </div>
</div>
