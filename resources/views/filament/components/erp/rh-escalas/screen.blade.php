@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-escalas',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'escala' => 'ESCALA',
        'codigo' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['funcionario', 'escala'],
    'numericFields' => ['codigo'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'vínculo (encerrar vigência)',
])
