@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-uniformes',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'uniforme' => 'UNIFORME',
        'tamanho' => 'TAMANHO',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario', 'uniforme', 'tamanho'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'entrega (cancelamento com histórico)',
])
