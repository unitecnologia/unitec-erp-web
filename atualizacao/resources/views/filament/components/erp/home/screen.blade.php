@php
    use App\Support\Erp\Dashboard\ErpDashboardData;

    $dash = ErpDashboardData::all();
@endphp

<div class="erp-dash">
    @include('filament.components.erp.home.partials.kpi-cards', [
        'kpis' => $dash['kpis'],
    ])

    @include('filament.components.erp.home.partials.gauges', [
        'gauges' => $dash['gauges'] ?? [],
        'sellerGauges' => $dash['sellerGauges'] ?? [],
    ])

    <div class="erp-dash__layout">
        <div class="erp-dash__main">
            @include('filament.components.erp.home.partials.charts', [
                'salesChart' => $dash['salesChart'],
                'cashflowChart' => $dash['cashflowChart'],
                'salesMixChart' => $dash['salesMixChart'] ?? [],
                'fiscalDocsChart' => $dash['fiscalDocsChart'] ?? [],
                'paymentMethodsChart' => $dash['paymentMethodsChart'] ?? [],
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
</div>
