@include('filament.components.erp.aviso-modal', [
    'open' => $this->productReplicaPrecosOpen,
    'tone' => 'warning',
    'icon' => '!',
    'titleId' => 'erp-produto-replica-precos-title',
    'title' => 'Replicar preços nas filiais?',
    'lines' => [
        'Os preços desta empresa podem ser aplicados em outras filiais.',
        'Marque as empresas que devem receber os mesmos valores:',
    ],
    'extraView' => 'filament.components.erp.produtos.form.replica-precos-list',
    'hint' => 'Se escolher “Só nesta empresa”, as demais filiais ficam inalteradas.',
    'primaryLabel' => 'Aplicar nas selecionadas',
    'primaryAction' => 'confirmarReplicaPrecosEmpresas',
    'secondaryLabel' => 'Só nesta empresa',
    'secondaryAction' => 'cancelarReplicaPrecosEmpresas',
    'escapeAction' => 'cancelarReplicaPrecosEmpresas',
    'backdropAction' => 'cancelarReplicaPrecosEmpresas',
])
