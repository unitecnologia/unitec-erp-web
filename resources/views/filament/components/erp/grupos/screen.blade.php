@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-grupos',
    'searchFields' => [
        'codigo' => 'CÓDIGO',
        'nome' => 'DESCRIÇÃO',
    ],
    'uppercaseColumns' => 'nome',
    'wireKeyPrefix' => 'grupos',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
