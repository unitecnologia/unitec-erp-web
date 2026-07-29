<?php
    $buildUrl = function (array $extra = []) use ($reportUrl, $filters): string {
        $params = [];

        foreach ($filters as $key => $value) {
            if ($key === 'cols' && is_array($value)) {
                foreach ($value as $column) {
                    $params['cols'][] = $column;
                }
                continue;
            }

            if (filled($value) || $value === '0' || $value === 0) {
                $params[$key] = $value;
            }
        }

        foreach ($extra as $key => $value) {
            $params[$key] = $value;
        }

        $params = array_filter(
            $params,
            fn ($value): bool => filled($value) || is_array($value) || $value === '0' || $value === 0,
        );

        return $reportUrl . '?' . http_build_query($params);
    };
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Visualizar — <?php echo e($reportTitle); ?></title>
    <style>
        @page { margin: 10mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #c7d5e8; font-family: Arial, Helvetica, sans-serif; }
        .viewer { min-height: 100vh; display: flex; flex-direction: column; }
        .viewer__toolbar {
            display: flex; align-items: center; gap: 0.35rem; flex-wrap: wrap;
            padding: 0.35rem 0.5rem;
            background: linear-gradient(180deg, #f8fafc 0%, #dbeafe 100%);
            border-bottom: 1px solid #94a3b8;
        }
        .viewer__title { margin-right: auto; font-size: 0.82rem; font-weight: 700; color: #0f2847; }
        .viewer__btn {
            min-width: 5.5rem; padding: 0.28rem 0.65rem; border: 1px solid #94a3b8; border-radius: 4px;
            background: linear-gradient(180deg, #ffffff 0%, #eef4fb 100%);
            color: #0f172a; font-size: 0.75rem; font-weight: 700; cursor: pointer;
        }
        .viewer__btn:hover { border-color: #1e5a9e; background: #ffffff; }
        .viewer__btn--close { background: linear-gradient(180deg, #fef2f2 0%, #fee2e2 100%); border-color: #fca5a5; }
        .viewer__layout { display: grid; grid-template-columns: minmax(240px, 280px) minmax(0, 1fr); flex: 1; min-height: 0; }
        .viewer__filters { overflow: auto; padding: 0.85rem; background: #eef4fb; border-right: 1px solid #94a3b8; }
        .viewer__filters h2 { margin: 0 0 0.75rem; font-size: 0.9rem; color: #0f2847; }
        .viewer__field { margin-bottom: 0.65rem; }
        .viewer__field label { display: block; margin-bottom: 0.2rem; font-size: 0.72rem; font-weight: 700; color: #334155; }
        .viewer__field select,
        .viewer__field input[type="text"],
        .viewer__field input[type="date"] {
            width: 100%; padding: 0.35rem 0.45rem; border: 1px solid #94a3b8; border-radius: 4px; font-size: 0.78rem;
        }
        .viewer__columns { display: grid; gap: 0.25rem; margin-top: 0.35rem; }
        .viewer__columns label {
            display: flex; align-items: center; gap: 0.35rem;
            font-size: 0.72rem; font-weight: 600; color: #334155;
        }
        .viewer__actions { display: grid; gap: 0.45rem; margin-top: 0.85rem; }
        .viewer__actions .viewer__btn { width: 100%; }
        .viewer__canvas { overflow: auto; padding: 1rem; }
        .viewer__paper { width: min(297mm, 100%); margin: 0 auto; }
        @media print {
            body { background: #fff; }
            .viewer__toolbar, .viewer__filters { display: none; }
            .viewer__layout { display: block; }
            .viewer__canvas { padding: 0; overflow: visible; }
            .viewer__paper { width: 100%; }
            .tabular-doc__frame { border: none; padding: 0; }
        }
    </style>
    <?php echo $__env->make('reports.partials.tabular-document-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>
<body>
    <div class="viewer">
        <div class="viewer__toolbar">
            <span class="viewer__title">Visualizar</span>
            <button type="button" class="viewer__btn" onclick="window.print()">Imprimir</button>
            <button type="button" class="viewer__btn" onclick="savePdf()">Salvar PDF</button>
            <button type="button" class="viewer__btn" onclick="saveCsv()">Exportar CSV</button>
            <button type="button" class="viewer__btn viewer__btn--close" onclick="closePreview()">Fechar</button>
        </div>

        <div class="viewer__layout">
            <aside class="viewer__filters">
                <h2>Filtros do relatório</h2>
                <form method="get" action="<?php echo e($reportUrl); ?>" id="report-filters-form">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $filterFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $key = $field['key'];
                            $type = $field['type'] ?? 'text';
                            $value = $filters[$key] ?? ($type === 'columns' ? ($filters['cols'] ?? []) : '');
                        ?>

                        <div class="viewer__field">
                            <label for="filter-<?php echo e($key); ?>"><?php echo e($field['label']); ?></label>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($type === 'select'): ?>
                                <select id="filter-<?php echo e($key); ?>" name="<?php echo e($key); ?>">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($field['options'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionValue => $optionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($optionValue); ?>" <?php if((string) $value === (string) $optionValue): echo 'selected'; endif; ?>><?php echo e($optionLabel); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </select>
                            <?php elseif($type === 'date'): ?>
                                <input id="filter-<?php echo e($key); ?>" type="date" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                            <?php elseif($type === 'columns'): ?>
                                <div class="viewer__columns">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($field['options'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionValue => $optionLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="cols[]"
                                                value="<?php echo e($optionValue); ?>"
                                                <?php if(in_array($optionValue, is_array($value) ? $value : ($filters['cols'] ?? []), true)): echo 'checked'; endif; ?>
                                            >
                                            <span><?php echo e($optionLabel); ?></span>
                                        </label>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php else: ?>
                                <input id="filter-<?php echo e($key); ?>" type="text" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>" placeholder="Opcional">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="viewer__actions">
                        <button type="submit" class="viewer__btn">Atualizar preview</button>
                    </div>
                </form>
            </aside>

            <div class="viewer__canvas">
                <div class="viewer__paper">
                    <?php echo $__env->make('reports.partials.tabular-document-body', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        const closeUrl = <?php echo json_encode($closeUrl, 15, 512) ?>;
        const reportFiltersForm = document.getElementById('report-filters-form');
        const pdfDownloadUrl = <?php echo json_encode($buildUrl(['pdf' => 1]), 15, 512) ?>;
        const csvDownloadUrl = <?php echo json_encode($buildUrl(['csv' => 1]), 15, 512) ?>;

        if (reportFiltersForm) {
            reportFiltersForm.addEventListener('submit', function (event) {
                event.preventDefault();
                const params = new URLSearchParams();
                new FormData(reportFiltersForm).forEach(function (value, key) {
                    if (String(value).trim() !== '') {
                        params.append(key, value);
                    }
                });
                const query = params.toString();
                window.location.replace(query === '' ? reportFiltersForm.action : reportFiltersForm.action + '?' + query);
            });
        }

        function savePdf() { window.open(pdfDownloadUrl, '_blank'); }
        function saveCsv() { window.open(csvDownloadUrl, '_blank'); }

        function closePreview() {
            if (window.parent !== window) {
                window.parent.postMessage({ type: 'erp-report-close' }, '*');
                return;
            }
            window.location.href = closeUrl;
        }

        <?php if($autoPrint): ?>
        window.addEventListener('load', function () { window.print(); });
        <?php endif; ?>
    </script>
</body>
</html>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/reports/tabular.blade.php ENDPATH**/ ?>