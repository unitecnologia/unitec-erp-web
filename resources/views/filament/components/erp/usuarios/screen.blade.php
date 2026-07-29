@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-usuarios',
    'searchFields' => [
        'name' => 'NOME',
    ],
    'showFieldDropdown' => false,
    'uppercaseColumns' => 'name',
    'wireKeyPrefix' => 'usuarios',
    'hint' => 'Clique na tecla [DELETE] para excluir usuário.',
])
