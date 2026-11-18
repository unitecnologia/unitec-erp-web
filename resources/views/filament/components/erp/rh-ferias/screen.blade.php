@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-ferias',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'gozo (cancelamento com restauração de saldo)',
])
