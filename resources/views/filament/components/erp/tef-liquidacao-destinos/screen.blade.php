@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-documentos',
    'searchFields' => [
        'terminal' => 'TERMINAL',
        'conta' => 'CONTA BANCO',
        'cnpj' => 'ADQUIRENTE',
        'id' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['terminal', 'conta'],
    'numericFields' => ['id', 'cnpj'],
    'nomeSearchLabel' => 'TERMINAL',
    'deleteHint' => 'destino de liquidação',
])
