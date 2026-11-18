@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-exames',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'tipo' => 'TIPO',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario', 'tipo'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'exame (inativação com histórico)',
])
