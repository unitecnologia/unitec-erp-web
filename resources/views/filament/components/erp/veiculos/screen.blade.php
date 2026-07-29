@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-veiculos',
    'searchFields' => [
        'placa' => 'PLACA',
        'descricao' => 'DESCRIÇÃO',
        'renavam' => 'RENAVAM',
        'rntc' => 'RNTC',
    ],
    'uppercaseColumns' => 'placa,descricao,rntc',
    'wireKeyPrefix' => 'veiculos',
    'hint' => 'Clique na tecla [DELETE] para excluir veículo.',
])
