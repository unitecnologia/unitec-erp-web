@include('filament.components.erp.aviso-modal', [
    'open' => $this->produtoImpostoPadraoRestoreConfirmOpen,
    'tone' => 'warning',
    'titleId' => 'erp-prod-imp-restore-title',
    'title' => 'Restaurar Imposto Padrão',
    'lines' => [
        'Substituir os impostos <strong>desta aba</strong> pelos valores do Imposto Padrão da empresa?',
        'Alterações manuais neste produto serão perdidas nesta aba. Os demais produtos <strong>não</strong> são alterados.',
    ],
    'hint' => 'Confirme apenas se deseja realinhar este produto ao padrão da empresa.',
    'primaryLabel' => 'Sim, restaurar',
    'primaryAction' => 'confirmarRestaurarImpostoPadraoProduto',
    'secondaryLabel' => 'Não',
    'secondaryAction' => 'cancelarRestaurarImpostoPadraoProduto',
    'escapeAction' => 'cancelarRestaurarImpostoPadraoProduto',
    'backdropAction' => 'cancelarRestaurarImpostoPadraoProduto',
])
