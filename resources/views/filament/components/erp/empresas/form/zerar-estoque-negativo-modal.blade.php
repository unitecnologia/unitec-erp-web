@include('filament.components.erp.aviso-modal', [
    'open' => $this->zerarEstoqueNegativoModalOpen,
    'tone' => 'warning',
    'titleId' => 'erp-zerar-estoque-title',
    'title' => 'BLOQUEAR ESTOQUE NEGATIVO',
    'lines' => [
        'Existem <strong>'.$this->zerarEstoqueNegativoModalCount.'</strong> produto(s) com estoque negativo.',
        'Para ativar o bloqueio, o sistema precisa zerar esses saldos.',
        'Deseja zerar o estoque negativo agora?',
    ],
    'hint' => 'Se clicar em Não, o parâmetro não será ativado.',
    'primaryLabel' => 'Sim, zerar e ativar',
    'primaryAction' => 'confirmZerarEstoqueNegativoModal',
    'secondaryLabel' => 'Não',
    'secondaryAction' => 'cancelZerarEstoqueNegativoModal',
    'escapeAction' => 'handleZerarEstoqueNegativoModalEscape',
    'backdropAction' => 'cancelZerarEstoqueNegativoModal',
])
