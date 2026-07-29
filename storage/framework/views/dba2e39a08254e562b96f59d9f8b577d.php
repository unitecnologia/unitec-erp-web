
<?php
    $fields = $fields ?? [];
    $searchColumn = $searchColumn ?? '';
    $markedFields = $markedFields ?? null;
    $buttonLabel = $buttonLabel ?? ($fields[$searchColumn] ?? 'CAMPO');
    $wireMethod = $wireMethod ?? 'setSearchColumn';
    $wireProperty = $wireProperty ?? null;
    $showFlag = $showFlag ?? true;
    $closeOnSelect = $closeOnSelect ?? true;
    $ariaLabel = $ariaLabel ?? 'Campo de pesquisa';
    $btnClass = $btnClass ?? '';
?>

<div
    class="erp-field-dd"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="erp-field-dd__btn <?php echo e($btnClass); ?>"
        @click="open = !open"
        :aria-expanded="open.toString()"
    >
        <span><?php echo e($buttonLabel); ?></span>
        <span class="erp-field-dd__caret" aria-hidden="true">▾</span>
    </button>
    <ul class="erp-field-dd__menu" x-show="open" x-cloak x-transition.opacity.duration.75ms role="listbox" aria-label="<?php echo e($ariaLabel); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $isMarked = is_array($markedFields)
                    ? in_array((string) $value, array_map('strval', $markedFields), true)
                    : (string) $searchColumn === (string) $value;
            ?>
            <li role="option" aria-selected="<?php echo e($isMarked ? 'true' : 'false'); ?>">
                <button
                    type="button"
                    class="erp-field-dd__item <?php if($isMarked): ?> is-active <?php endif; ?>"
                    <?php if($wireProperty): ?>
                        wire:click="$set('<?php echo e($wireProperty); ?>', '<?php echo e($value); ?>')"
                    <?php else: ?>
                        wire:click="<?php echo e($wireMethod); ?>('<?php echo e($value); ?>')"
                    <?php endif; ?>
                    <?php if($closeOnSelect): ?>
                        @click="open = false"
                    <?php endif; ?>
                >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showFlag): ?>
                        <span
                            class="erp-field-dd__flag <?php if($isMarked): ?> is-on <?php endif; ?>"
                            aria-hidden="true"
                        ></span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="erp-field-dd__label"><?php echo e($label); ?></span>
                </button>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/shared/search-field-dropdown.blade.php ENDPATH**/ ?>