@php
    $salesChart = $salesChart ?? ['defaultFrom' => '', 'defaultTo' => '', 'points' => []];
    $cashflowChart = $cashflowChart ?? ['labels' => [], 'entradas' => [], 'saidas' => []];
@endphp

<section class="erp-dash__charts">
    <article class="erp-dash-panel">
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

    <article class="erp-dash-panel">
        <header class="erp-dash-panel__head">
            <h2 class="erp-dash-panel__title">Entradas x saídas</h2>
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart">
            <canvas id="erp-dash-cashflow-chart" aria-label="Gráfico de entradas e saídas"></canvas>
        </div>
    </article>
</section>

<script type="application/json" id="erp-dash-sales-data">@json($salesChart)</script>
<script type="application/json" id="erp-dash-cashflow-data">@json($cashflowChart)</script>
