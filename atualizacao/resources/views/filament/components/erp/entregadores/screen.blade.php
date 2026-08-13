@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-entregadores',
    'searchFields' => [
        'codigo' => 'CÓDIGO',
        'nome' => 'NOME',
    ],
    'uppercaseColumns' => 'nome',
    'wireKeyPrefix' => 'entregadores',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])
