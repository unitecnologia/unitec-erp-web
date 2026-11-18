@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-epis',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'epi' => 'EPI',
        'ca' => 'CA',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario', 'epi'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'entrega (cancelamento com histórico)',
])
