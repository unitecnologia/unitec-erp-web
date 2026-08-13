@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-unidades',
    'searchFields' => [
        'codigo' => 'CÓDIGO',
        'descricao' => 'DESCRIÇÃO',
    ],
    'uppercaseColumns' => 'descricao',
    'wireKeyPrefix' => 'formas-pgto',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
