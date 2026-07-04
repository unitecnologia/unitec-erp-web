@php
    use App\Support\Erp\Dashboard\ErpDashboardData;

    $dash = ErpDashboardData::all();
@endphp

<div class="erp-dash">
    @include('filament.components.erp.home.partials.kpi-cards', [
        'kpis' => $dash['kpis'],
    ])
    <div class="erp-dash__layout">
        <div class="erp-dash__main">
            @include('filament.components.erp.home.partials.charts', [
                'salesChart' => $dash['salesChart'],
                'cashflowChart' => $dash['cashflowChart'],
            ])

            @include('filament.components.erp.home.partials.sales-list', [
                'recentSales' => $dash['recentSales'],
            ])
        </div>

        @include('filament.components.erp.home.partials.alerts-sidebar', [
            'alerts' => $dash['alerts'],
        ])
    </div>
</div>
