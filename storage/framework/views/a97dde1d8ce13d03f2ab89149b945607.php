<div
    class="erp-cfop-locate"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if (! $wire.showForm) {
            $event.preventDefault();
            $wire.handleCfopEscape();
        }
    "
    x-on:erp-focus-cfop-search.window="$el.querySelector('.erp-unidades__search-text')?.focus()"
>
    <?php echo $__env->make('filament.components.erp.shared.cadastro-list-screen', [
        'pageClass' => 'erp-unidades',
        'searchFields' => [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
        ],
        'uppercaseColumns' => 'descricao',
        'wireKeyPrefix' => 'cfop',
        'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/cfops/screen.blade.php ENDPATH**/ ?>