<div class="erp-pessoas-window">
    <header class="erp-pessoas-window__titlebar">
        <span class="erp-pessoas-window__title">Cadastro de Pessoas</span>
        <button
            type="button"
            class="erp-pessoas-window__close"
            wire:click="cancelForm"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-pessoas-window__body">
        <?php echo $__env->make('filament.components.erp.pessoas.form.shell', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('filament.components.erp.pessoas.form.action-bar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/pessoas/form/window.blade.php ENDPATH**/ ?>