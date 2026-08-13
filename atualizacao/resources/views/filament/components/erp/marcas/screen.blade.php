@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-marcas',
    'searchFields' => [
        'codigo' => 'CÓDIGO',
        'nome' => 'DESCRIÇÃO',
    ],
    'uppercaseColumns' => 'nome',
    'wireKeyPrefix' => 'marcas',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
