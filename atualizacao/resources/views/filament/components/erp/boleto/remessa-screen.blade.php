@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-unidades',
    'searchFields' => [
        'id' => 'CÓDIGO',
        'agencia' => 'AGÊNCIA',
        'conta' => 'CONTA',
        'carteira' => 'CARTEIRA',
    ],
    'uppercaseColumns' => 'agencia,conta,carteira',
    'wireKeyPrefix' => 'boleto-remessa',
    'hint' => 'Remessas CNAB geradas para o banco. Duplo clique / F3 para ver os títulos.',
])
