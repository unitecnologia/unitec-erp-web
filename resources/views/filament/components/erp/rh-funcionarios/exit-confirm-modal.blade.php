@include('filament.components.erp.aviso-modal', [
    'open' => $this->rhFuncionarioExitConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-rh-func-exit-title',
    'title' => 'Sair sem salvar?',
    'lines' => [
        'Existem alterações não salvas. Deseja realmente sair e descartar os dados?',
    ],
    'hint' => 'Use F5 | Gravar para salvar antes de sair.',
    'primaryLabel' => 'Sair sem salvar',
    'primaryAction' => 'confirmRhFuncionarioExitWithoutSaving',
    'secondaryLabel' => 'Continuar editando',
    'secondaryAction' => 'dismissRhFuncionarioExitConfirm',
    'escapeAction' => 'dismissRhFuncionarioExitConfirm',
    'backdropAction' => 'dismissRhFuncionarioExitConfirm',
])
