@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-ajustes-estoque',
    'searchFields' => [
        'numero' => 'NÚMERO',
        'origem' => 'ORIGEM',
        'destino' => 'DESTINO',
    ],
    'uppercaseColumns' => '',
    'wireKeyPrefix' => 'estoque-transf',
    'hint' => null,
    'beforeFiltersView' => 'filament.components.erp.estoque-transferencias.period-block',
    'beforeFiltersInline' => true,
])
