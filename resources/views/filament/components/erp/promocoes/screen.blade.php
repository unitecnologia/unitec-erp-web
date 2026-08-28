@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-unidades',
    'searchFields' => [
        'descricao' => 'DESCRIÇÃO',
    ],
    'uppercaseColumns' => 'descricao',
    'wireKeyPrefix' => 'promocoes',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
