@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-unidades',
    'searchFields' => [
        'sigla' => 'SIGLA',
        'descricao' => 'DESCRIÇÃO',
    ],
    'uppercaseColumns' => 'sigla,descricao',
    'wireKeyPrefix' => 'unidades',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
