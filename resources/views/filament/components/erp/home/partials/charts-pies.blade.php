@php
    $salesMixChart = $salesMixChart ?? ['labels' => [], 'values' => [], 'colors' => []];
    $fiscalDocsChart = $fiscalDocsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'count'];
    $paymentMethodsChart = $paymentMethodsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'money'];
@endphp

<div class="erp-dash__pies">
    <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Mix do mês</h2>
            <span class="erp-dash-panel__meta">canais</span>
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
            <canvas id="erp-dash-mix-chart" aria-label="Gráfico pizza do mix de vendas"></canvas>
        </div>
    </article>

    <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Docs. eletrônicos</h2>
            <span class="erp-dash-panel__meta">mês</span>
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
            @if (! empty($fiscalDocsChart['empty']) || array_sum($fiscalDocsChart['values'] ?? []) <= 0)
                <p class="erp-dash-panel__empty">Sem documentos no mês</p>
            @else
                <canvas id="erp-dash-fiscal-chart" aria-label="Gráfico pizza de NFe, NFCe e NFSe"></canvas>
            @endif
        </div>
    </article>

    <article class="erp-dash-panel erp-dash-panel--chart erp-dash-panel--pie">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Meios de pagamento</h2>
            <span class="erp-dash-panel__meta">mês</span>
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart erp-dash-panel__body--pie">
            <canvas id="erp-dash-payments-chart" aria-label="Gráfico pizza dos meios de pagamento"></canvas>
        </div>
    </article>
</div>

<script type="application/json" id="erp-dash-mix-data">@json($salesMixChart)</script>
<script type="application/json" id="erp-dash-fiscal-data">@json($fiscalDocsChart)</script>
<script type="application/json" id="erp-dash-payments-data">@json($paymentMethodsChart)</script>
