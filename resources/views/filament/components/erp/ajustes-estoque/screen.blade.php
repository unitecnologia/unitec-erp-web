@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-ajustes-estoque',
    'searchFields' => [
        'produto' => 'PRODUTO',
        'codigo' => 'CÓDIGO',
        'data' => 'DATA',
    ],
    'uppercaseColumns' => 'produto',
    'wireKeyPrefix' => 'ajustes-estoque',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Informe o período quando necessário.',
    'beforeFiltersView' => 'filament.components.erp.ajustes-estoque.period-block',
])
