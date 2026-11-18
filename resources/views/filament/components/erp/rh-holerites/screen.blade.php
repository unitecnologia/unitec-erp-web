@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-holerites',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'holerite (inativação — PDF preservado)',
])
