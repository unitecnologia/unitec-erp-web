@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-ocorrencias',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'codigo' => 'CÓDIGO',
        'descricao' => 'DESCRIÇÃO',
    ],
    'uppercaseFields' => ['funcionario', 'descricao'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'ocorrência (cancelamento com histórico)',
])
