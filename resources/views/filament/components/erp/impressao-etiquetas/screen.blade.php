@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-impressao-etiquetas',
    'searchFields' => [
        'q' => 'PRODUTO',
    ],
    'showFieldDropdown' => false,
    'uppercaseColumns' => '',
    'wireKeyPrefix' => 'impressao-etiquetas',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use Imprimir no rodapé.',
])
