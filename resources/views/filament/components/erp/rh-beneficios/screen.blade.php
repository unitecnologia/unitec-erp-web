@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-beneficios',
    'searchFields' => [
        'funcionario' => 'FUNCIONÁRIO',
        'beneficio' => 'BENEFÍCIO',
    ],
    'uppercaseFields' => ['funcionario', 'beneficio'],
    'nomeSearchLabel' => 'FUNCIONÁRIO',
    'deleteHint' => 'vínculo (cancelamento com histórico)',
])
