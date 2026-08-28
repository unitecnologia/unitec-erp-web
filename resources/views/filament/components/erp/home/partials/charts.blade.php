@php
    $salesChart = $salesChart ?? ['defaultFrom' => '', 'defaultTo' => '', 'points' => []];
    $cashflowChart = $cashflowChart ?? ['labels' => [], 'entradas' => [], 'saidas' => []];
    $salesMixChart = $salesMixChart ?? ['labels' => [], 'values' => [], 'colors' => []];
    $fiscalDocsChart = $fiscalDocsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'count'];
    $paymentMethodsChart = $paymentMethodsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'money'];
    $visao = $visao ?? \App\Support\Erp\Dashboard\ErpDashboardScope::VISAO_EMPRESA;
    $cashflowGlobal = $visao === \App\Support\Erp\Dashboard\ErpDashboardScope::VISAO_GRUPO;
@endphp

<section class="erp-dash__charts">
    <article class="erp-dash-panel erp-dash-panel--chart">
        <header class="erp-dash-panel__head erp-dash-panel__head--with-filter">
            <h2 class="erp-dash-panel__title">Vendas por período</h2>
            <div class="erp-dash-chart-filter" data-erp-sales-chart-filter>
                <label class="erp-dash-chart-filter__field">
                    <span class="erp-dash-chart-filter__label">de</span>
                    <input
                        type="date"
                        class="erp-dash-chart-filter__input"
                        data-erp-sales-from
                        value="{{ $salesChart['defaultFrom'] ?? '' }}"
                        aria-label="Data inicial do gráfico de vendas"
                    >
                </label>
                <label class="erp-dash-chart-filter__field">
                    <span class="erp-dash-chart-filter__label">até</span>
                    <input
                        type="date"
                        class="erp-dash-chart-filter__input"
                        data-erp-sales-to
                        value="{{ $salesChart['defaultTo'] ?? '' }}"
                        aria-label="Data final do gráfico de vendas"
                    >
                </label>
            </div>
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart">
            <canvas id="erp-dash-sales-chart" aria-label="Gráfico de vendas"></canvas>
        </div>
    </article>

    <article class="erp-dash-panel erp-dash-panel--chart">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Entradas x saídas</h2>
            @if ($cashflowGlobal)
                <span class="erp-dash-panel__meta">Grupo (empresas acessíveis)</span>
            @endif
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart">
            @if (! empty($cashflowChart['empty']) || (array_sum($cashflowChart['entradas'] ?? []) <= 0 && array_sum($cashflowChart['saidas'] ?? []) <= 0))
                <p class="erp-dash-panel__empty">Sem movimentos de caixa no período.</p>
            @else
                <canvas id="erp-dash-cashflow-chart" aria-label="Gráfico de entradas e saídas"></canvas>
            @endif
        </div>
    </article>

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
                    <canvas id="erp-dash-fiscal-chart" aria-label="Gráfico pizza de NFe e NFCe"></canvas>
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
</section>

<script type="application/json" id="erp-dash-sales-data">@json($salesChart)</script>
<script type="application/json" id="erp-dash-cashflow-data">@json($cashflowChart)</script>
<script type="application/json" id="erp-dash-mix-data">@json($salesMixChart)</script>
<script type="application/json" id="erp-dash-fiscal-data">@json($fiscalDocsChart)</script>
<script type="application/json" id="erp-dash-payments-data">@json($paymentMethodsChart)</script>
