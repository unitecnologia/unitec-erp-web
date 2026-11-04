{{-- Compat: layout completo (gráficos de linha + pizzas). --}}
@php
    $salesChart = $salesChart ?? ['defaultFrom' => '', 'defaultTo' => '', 'points' => []];
    $cashflowChart = $cashflowChart ?? ['labels' => [], 'entradas' => [], 'saidas' => []];
    $salesMixChart = $salesMixChart ?? ['labels' => [], 'values' => [], 'colors' => []];
    $fiscalDocsChart = $fiscalDocsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'count'];
    $paymentMethodsChart = $paymentMethodsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'money'];
    $visao = $visao ?? \App\Support\Erp\Dashboard\ErpDashboardScope::VISAO_EMPRESA;
@endphp

<section class="erp-dash__charts">
    @include('filament.components.erp.home.partials.charts-main', [
        'salesChart' => $salesChart,
        'cashflowChart' => $cashflowChart,
        'visao' => $visao,
    ])

    @include('filament.components.erp.home.partials.charts-pies', [
        'salesMixChart' => $salesMixChart,
        'fiscalDocsChart' => $fiscalDocsChart,
        'paymentMethodsChart' => $paymentMethodsChart,
    ])
</section>
