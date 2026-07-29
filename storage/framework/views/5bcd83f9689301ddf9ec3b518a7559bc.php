<?php
    $gauges = $gauges ?? [];
    $sellerGauges = $sellerGauges ?? [];
    $topSellerGauges = array_slice($sellerGauges, 0, 4);
    $gaugesCount = count($gauges);
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gaugesCount || count($sellerGauges)): ?>
<section
    class="erp-dash__gauges-row<?php echo e(count($sellerGauges) ? '' : ' erp-dash__gauges-row--no-sellers'); ?>"
    aria-label="Indicadores"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($sellerGauges)): ?>
        <aside
            class="erp-dash__seller-panel"
            aria-label="Meta dos vendedores"
            x-data="{ open: false }"
            @keydown.escape.window="if (open) open = false"
        >
            <header class="erp-dash__seller-panel-head">
                <h2 class="erp-dash__seller-panel-title">Meta Vendedores</h2>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($sellerGauges) > 4): ?>
                    <button
                        type="button"
                        class="erp-dash__seller-panel-btn"
                        @click="open = true; $nextTick(() => window.erpDashRefreshGauges?.())"
                        title="Ver todos os vendedores"
                    >Todos</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </header>
            <div class="erp-dash__seller-panel-body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $topSellerGauges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gauge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $tone = $gauge['tone'] ?? 'slate';
                        $percent = max(0, (float) ($gauge['percent'] ?? 0));
                    ?>
                    <article
                        class="erp-dash-gauge erp-dash-gauge--seller erp-dash-gauge--<?php echo e($tone); ?>"
                        data-erp-gauge
                        data-percent="<?php echo e($percent); ?>"
                        title="<?php echo e(($gauge['full_name'] ?? $gauge['label'] ?? '')); ?> · <?php echo e($gauge['meta_label'] ?? ''); ?>"
                    >
                        <div class="erp-dash-gauge__stats erp-dash-gauge__stats--sm">
                            <div class="erp-dash-gauge__stat">
                                <span><?php echo e($gauge['stat_left_label'] ?? 'Meta'); ?></span>
                                <strong><?php echo e($gauge['stat_left'] ?? '—'); ?></strong>
                            </div>
                            <div class="erp-dash-gauge__stat">
                                <span><?php echo e($gauge['stat_right_label'] ?? 'Real'); ?></span>
                                <strong><?php echo e($gauge['stat_right'] ?? '—'); ?></strong>
                            </div>
                        </div>
                        <div class="erp-dash-gauge__meter erp-dash-gauge__meter--sm" aria-hidden="true">
                            <canvas
                                class="erp-dash-gauge__canvas"
                                data-erp-gauge-canvas
                                data-compact="1"
                                data-scale="seller"
                                data-percent="<?php echo e($percent); ?>"
                                data-tone="<?php echo e($tone); ?>"
                                width="96"
                                height="64"
                            ></canvas>
                            <div class="erp-dash-gauge__pct erp-dash-gauge__pct--sm"><?php echo e($gauge['display_percent'] ?? '0%'); ?></div>
                        </div>
                        <p class="erp-dash-gauge__seller-name"><?php echo e($gauge['label'] ?? '—'); ?></p>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <template x-teleport="body">
                <div
                    class="erp-dash-sellers-modal"
                    x-show="open"
                    x-cloak
                    x-transition.opacity
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="erp-dash-sellers-modal-title"
                >
                    <div class="erp-dash-sellers-modal__backdrop" @click="open = false"></div>
                    <div class="erp-dash-sellers-modal__window" @click.stop>
                        <div class="erp-dash-sellers-modal__titlebar">
                            <span id="erp-dash-sellers-modal-title">Meta Vendedores — todos</span>
                            <button
                                type="button"
                                class="erp-dash-sellers-modal__close"
                                @click="open = false"
                                title="Fechar"
                            >✕</button>
                        </div>
                        <div class="erp-dash-sellers-modal__body">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sellerGauges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gauge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $tone = $gauge['tone'] ?? 'slate';
                                    $percent = max(0, (float) ($gauge['percent'] ?? 0));
                                ?>
                                <article
                                    class="erp-dash-gauge erp-dash-gauge--seller-lg erp-dash-gauge--<?php echo e($tone); ?>"
                                    data-erp-gauge
                                    data-percent="<?php echo e($percent); ?>"
                                    title="<?php echo e(($gauge['full_name'] ?? $gauge['label'] ?? '')); ?> · <?php echo e($gauge['meta_label'] ?? ''); ?>"
                                >
                                    <header class="erp-dash-gauge__head">
                                        <h3 class="erp-dash-gauge__title"><?php echo e($gauge['full_name'] ?? $gauge['label'] ?? '—'); ?></h3>
                                    </header>
                                    <div class="erp-dash-gauge__body">
                                        <div class="erp-dash-gauge__stats">
                                            <div class="erp-dash-gauge__stat">
                                                <span><?php echo e($gauge['stat_left_label'] ?? 'Meta'); ?></span>
                                                <strong><?php echo e($gauge['stat_left'] ?? '—'); ?></strong>
                                            </div>
                                            <div class="erp-dash-gauge__stat">
                                                <span><?php echo e($gauge['stat_right_label'] ?? 'Real'); ?></span>
                                                <strong><?php echo e($gauge['stat_right'] ?? '—'); ?></strong>
                                            </div>
                                        </div>
                                        <div class="erp-dash-gauge__meter" aria-hidden="true">
                                            <canvas
                                                class="erp-dash-gauge__canvas"
                                                data-erp-gauge-canvas
                                                data-scale="seller"
                                                data-percent="<?php echo e($percent); ?>"
                                                data-tone="<?php echo e($tone); ?>"
                                                width="180"
                                                height="118"
                                            ></canvas>
                                            <div class="erp-dash-gauge__pct"><?php echo e($gauge['display_percent'] ?? '0%'); ?></div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </template>
        </aside>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gaugesCount): ?>
        <div
            class="erp-dash__gauges erp-dash__gauges--n<?php echo e($gaugesCount); ?>"
            style="--erp-gauge-cols: <?php echo e(max(1, $gaugesCount)); ?>;"
            x-data="{ healthOpen: false }"
            @keydown.escape.window="if (healthOpen) healthOpen = false"
        >
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $gauges; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gauge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $tone = $gauge['tone'] ?? 'slate';
                    $percent = (float) ($gauge['percent'] ?? 0);
                    $needle = max(0, $percent);
                    $clickable = (bool) ($gauge['clickable'] ?? false);
                    $isHealth = ($gauge['key'] ?? '') === 'saude_empresa';
                    $factors = is_array($gauge['detail']['factors'] ?? null) ? $gauge['detail']['factors'] : [];
                ?>
                <article
                    class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'erp-dash-gauge',
                        'erp-dash-gauge--'.$tone,
                        'erp-dash-gauge--clickable' => $clickable,
                        'erp-dash-gauge--health' => $isHealth,
                    ]); ?>"
                    data-erp-gauge
                    data-percent="<?php echo e($needle); ?>"
                    <?php if($isHealth): ?>
                        role="button"
                        tabindex="0"
                        title="Clique para ver o detalhe da nota"
                        @click="healthOpen = true; $nextTick(() => window.erpDashRefreshGauges?.())"
                        @keydown.enter.prevent="healthOpen = true"
                        @keydown.space.prevent="healthOpen = true"
                    <?php endif; ?>
                >
                    <header class="erp-dash-gauge__head">
                        <h2 class="erp-dash-gauge__title"><?php echo e($gauge['label']); ?></h2>
                    </header>
                    <div class="erp-dash-gauge__body">
                        <div class="erp-dash-gauge__stats">
                            <div class="erp-dash-gauge__stat">
                                <span><?php echo e($gauge['stat_left_label'] ?? 'Meta'); ?></span>
                                <strong><?php echo e($gauge['stat_left'] ?? '—'); ?></strong>
                            </div>
                            <div class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                                'erp-dash-gauge__stat',
                                'erp-dash-gauge__stat--'.($gauge['stat_right_tone'] ?? '') => filled($gauge['stat_right_tone'] ?? null),
                            ]); ?>">
                                <span><?php echo e($gauge['stat_right_label'] ?? 'Real'); ?></span>
                                <strong><?php echo e($gauge['stat_right'] ?? '—'); ?></strong>
                            </div>
                        </div>
                        <div class="erp-dash-gauge__meter" aria-hidden="true">
                            <canvas
                                class="erp-dash-gauge__canvas"
                                data-erp-gauge-canvas
                                data-percent="<?php echo e($needle); ?>"
                                data-tone="<?php echo e($tone); ?>"
                                width="160"
                                height="104"
                            ></canvas>
                            <div class="erp-dash-gauge__pct"><?php echo e($gauge['display_percent'] ?? '0%'); ?></div>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isHealth && filled($gauge['meta_label'] ?? null)): ?>
                            <p class="erp-dash-gauge__health-msg"><?php echo e($gauge['meta_label']); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php
                $healthGauge = collect($gauges)->firstWhere('key', 'saude_empresa');
                $healthFactors = is_array($healthGauge['detail']['factors'] ?? null) ? $healthGauge['detail']['factors'] : [];
                $healthMessage = (string) ($healthGauge['detail']['message'] ?? $healthGauge['meta_label'] ?? '');
                $healthStatus = (string) ($healthGauge['detail']['status'] ?? $healthGauge['value_label'] ?? '');
                $healthPercent = (string) ($healthGauge['display_percent'] ?? '');
            ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($healthGauge): ?>
                <template x-teleport="body">
                    <div
                        class="erp-dash-health-modal"
                        x-show="healthOpen"
                        x-cloak
                        x-transition.opacity
                        role="dialog"
                        aria-modal="true"
                        aria-labelledby="erp-dash-health-modal-title"
                    >
                        <div class="erp-dash-health-modal__backdrop" @click="healthOpen = false"></div>
                        <div class="erp-dash-health-modal__window" @click.stop>
                            <div class="erp-dash-health-modal__titlebar">
                                <span id="erp-dash-health-modal-title">Saúde da Empresa — detalhe</span>
                                <button
                                    type="button"
                                    class="erp-dash-health-modal__close"
                                    @click="healthOpen = false"
                                    title="Fechar"
                                >✕</button>
                            </div>
                            <div class="erp-dash-health-modal__summary">
                                <strong><?php echo e($healthPercent); ?></strong>
                                <span><?php echo e($healthStatus); ?></span>
                                <p><?php echo e($healthMessage); ?></p>
                            </div>
                            <div class="erp-dash-health-modal__body">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $healthFactors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $factor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $fTone = $factor['tone'] ?? 'slate';
                                        $fPct = max(0, min(100, (float) ($factor['percent'] ?? 0)));
                                    ?>
                                    <div class="erp-dash-health-factor erp-dash-health-factor--<?php echo e($fTone); ?>">
                                        <div class="erp-dash-health-factor__row">
                                            <span class="erp-dash-health-factor__label"><?php echo e($factor['label'] ?? '—'); ?></span>
                                            <span class="erp-dash-health-factor__pct"><?php echo e(number_format($fPct, 1, ',', '')); ?>%</span>
                                            <span class="erp-dash-health-factor__weight">peso <?php echo e((int) ($factor['weight'] ?? 0)); ?></span>
                                        </div>
                                        <div class="erp-dash-health-factor__bar" aria-hidden="true">
                                            <span style="width: <?php echo e($fPct); ?>%;"></span>
                                        </div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($factor['hint'] ?? null)): ?>
                                            <p class="erp-dash-health-factor__hint"><?php echo e($factor['hint']); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </template>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/home/partials/gauges.blade.php ENDPATH**/ ?>