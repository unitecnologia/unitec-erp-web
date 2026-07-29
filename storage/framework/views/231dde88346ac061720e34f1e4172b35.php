<?php
    $salesChart = $salesChart ?? ['defaultFrom' => '', 'defaultTo' => '', 'points' => []];
    $cashflowChart = $cashflowChart ?? ['labels' => [], 'entradas' => [], 'saidas' => []];
    $salesMixChart = $salesMixChart ?? ['labels' => [], 'values' => [], 'colors' => []];
    $fiscalDocsChart = $fiscalDocsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'count'];
    $paymentMethodsChart = $paymentMethodsChart ?? ['labels' => [], 'values' => [], 'colors' => [], 'unit' => 'money'];
?>

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
                        value="<?php echo e($salesChart['defaultFrom'] ?? ''); ?>"
                        aria-label="Data inicial do gráfico de vendas"
                    >
                </label>
                <label class="erp-dash-chart-filter__field">
                    <span class="erp-dash-chart-filter__label">até</span>
                    <input
                        type="date"
                        class="erp-dash-chart-filter__input"
                        data-erp-sales-to
                        value="<?php echo e($salesChart['defaultTo'] ?? ''); ?>"
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
        </header>
        <div class="erp-dash-panel__body erp-dash-panel__body--chart">
            <canvas id="erp-dash-cashflow-chart" aria-label="Gráfico de entradas e saídas"></canvas>
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
                <canvas id="erp-dash-fiscal-chart" aria-label="Gráfico pizza de NFe e NFCe"></canvas>
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

<script type="application/json" id="erp-dash-sales-data"><?php echo json_encode($salesChart, 15, 512) ?></script>
<script type="application/json" id="erp-dash-cashflow-data"><?php echo json_encode($cashflowChart, 15, 512) ?></script>
<script type="application/json" id="erp-dash-mix-data"><?php echo json_encode($salesMixChart, 15, 512) ?></script>
<script type="application/json" id="erp-dash-fiscal-data"><?php echo json_encode($fiscalDocsChart, 15, 512) ?></script>
<script type="application/json" id="erp-dash-payments-data"><?php echo json_encode($paymentMethodsChart, 15, 512) ?></script>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/partials/charts.blade.php ENDPATH**/ ?>