<?php
    use App\Support\Erp\Dashboard\ErpDashboardData;

    $dash = ErpDashboardData::all();
?>

<div class="erp-dash">
    <?php echo $__env->make('filament.components.erp.home.partials.kpi-cards', [
        'kpis' => $dash['kpis'],
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('filament.components.erp.home.partials.gauges', [
        'gauges' => $dash['gauges'] ?? [],
        'sellerGauges' => $dash['sellerGauges'] ?? [],
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="erp-dash__layout">
        <div class="erp-dash__main">
            <?php echo $__env->make('filament.components.erp.home.partials.charts', [
                'salesChart' => $dash['salesChart'],
                'cashflowChart' => $dash['cashflowChart'],
                'salesMixChart' => $dash['salesMixChart'] ?? [],
                'fiscalDocsChart' => $dash['fiscalDocsChart'] ?? [],
                'paymentMethodsChart' => $dash['paymentMethodsChart'] ?? [],
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="erp-dash__sales-row">
                <?php echo $__env->make('filament.components.erp.home.partials.sales-list', [
                    'recentSales' => $dash['recentSales'],
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <?php echo $__env->make('filament.components.erp.home.partials.highlights', [
                    'highlights' => $dash['highlights'] ?? [],
                ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>
        </div>

        <?php echo $__env->make('filament.components.erp.home.partials.alerts-sidebar', [
            'alerts' => $dash['alerts'],
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/screen.blade.php ENDPATH**/ ?>