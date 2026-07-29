<?php echo $__env->make('filament.components.erp.aviso-modal', [
    'open' => $this->productExitConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-produto-exit-title',
    'title' => 'Sair sem gravar?',
    'lines' => [
        'Existem alterações que ainda <strong>não foram gravadas</strong>.',
        'Se sair agora, os valores (incluindo precificação) serão perdidos.',
        'Deseja realmente sair sem salvar?',
    ],
    'hint' => 'Use F5 | Salvar para gravar antes de sair.',
    'primaryLabel' => 'Sim, sair sem gravar',
    'primaryAction' => 'confirmProductExitWithoutSaving',
    'secondaryLabel' => 'Não, continuar editando',
    'secondaryAction' => 'dismissProductExitConfirm',
    'escapeAction' => 'dismissProductExitConfirm',
    'backdropAction' => 'dismissProductExitConfirm',
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/exit-confirm-modal.blade.php ENDPATH**/ ?>