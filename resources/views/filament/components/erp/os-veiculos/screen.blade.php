@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-veiculos',
    'searchFields' => [
        'placa' => 'PLACA',
        'descricao' => 'DESCRIÇÃO',
        'marca' => 'MARCA',
        'modelo' => 'MODELO',
        'chassi' => 'CHASSI',
        'renavam' => 'RENAVAM',
        'cor' => 'COR',
        'cidade' => 'CIDADE',
    ],
    'uppercaseColumns' => 'placa,descricao,marca,modelo,chassi,renavam,cor,cidade',
    'wireKeyPrefix' => 'os-veiculos',
    'hint' => 'Clique na tecla [DELETE] para excluir veículo.',
])
