
<?php
    $id = $id ?? '';
    $field = $field ?? '';
    $options = $options ?? [];
    $grow = $grow ?? false;
    $allowEmpty = $allowEmpty ?? true;
    $selected = (string) ($this->data[$field] ?? '');
    $selectedLabel = $selected;

    foreach ($options as $optionKey => $optionValue) {
        $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
        if ($value === $selected) {
            $selectedLabel = (string) $optionValue;
            break;
        }
    }
?>

<div
    class="<?php echo \Illuminate\Support\Arr::toCssClasses([
        'erp-prod-compact-select',
        'erp-prod-compact-select--grow' => $grow,
    ]); ?>"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        id="<?php echo e($id); ?>"
        class="erp-pcad-form__select erp-prod-compact-select__trigger"
        @click="open = ! open"
        @keydown.arrow-down.prevent="open = true"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
        aria-controls="<?php echo e($id); ?>-list"
    >
        <span class="erp-prod-compact-select__value"><?php echo e($selected !== '' ? $selectedLabel : ''); ?></span>
        <span class="erp-prod-compact-select__caret" aria-hidden="true">▾</span>
    </button>

    <ul
        id="<?php echo e($id); ?>-list"
        class="erp-prod-compact-select__menu"
        x-show="open"
        x-cloak
        x-transition.opacity.duration.75ms
        role="listbox"
        aria-labelledby="<?php echo e($id); ?>"
    >
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allowEmpty): ?>
            <li role="presentation">
                <button
                    type="button"
                    role="option"
                    aria-selected="<?php echo e($selected === '' ? 'true' : 'false'); ?>"
                    class="erp-prod-compact-select__item <?php if($selected === ''): ?> is-selected <?php endif; ?>"
                    wire:click="$set('data.<?php echo e($field); ?>', '')"
                    @click="open = false"
                >&nbsp;</button>
            </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $optionKey => $optionValue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $value = is_int($optionKey) ? (string) $optionValue : (string) $optionKey;
                $label = (string) $optionValue;
                $isSelected = $selected === $value;
            ?>
            <li role="presentation" wire:key="compact-select-<?php echo e($id); ?>-<?php echo e($value); ?>">
                <button
                    type="button"
                    role="option"
                    aria-selected="<?php echo e($isSelected ? 'true' : 'false'); ?>"
                    class="erp-prod-compact-select__item <?php if($isSelected): ?> is-selected <?php endif; ?>"
                    wire:click="$set('data.<?php echo e($field); ?>', <?php echo \Illuminate\Support\Js::from($value)->toHtml() ?>)"
                    @click="open = false"
                ><?php echo e($label); ?></button>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/compact-select.blade.php ENDPATH**/ ?>