@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-treinamentos',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'treinamento' => 'TREINAMENTO',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario', 'treinamento'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'participante (cancelamento com histórico)',
])
