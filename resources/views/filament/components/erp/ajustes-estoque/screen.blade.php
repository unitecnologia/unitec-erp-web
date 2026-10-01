@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-ajustes-estoque',
    'searchFields' => [
        'produto' => 'PRODUTO',
        'codigo' => 'CÓDIGO',
        'data' => 'DATA',
    ],
    'uppercaseColumns' => 'produto',
    'wireKeyPrefix' => 'ajustes-estoque',
    'hint' => null,
    'beforeFiltersView' => 'filament.components.erp.ajustes-estoque.period-block',
    'beforeFiltersInline' => true,
])
