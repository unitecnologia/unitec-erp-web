@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-documentos',
    'searchFields' => [
        'id' => 'CÓDIGO',
        'terminal' => 'TERMINAL',
        'cnpj' => 'ADQUIRENTE',
        'externo' => 'ID EXTERNO',
    ],
    'uppercaseFields' => ['terminal', 'externo'],
    'numericFields' => ['id', 'cnpj'],
    'nomeSearchLabel' => 'TERMINAL',
    'deleteHint' => 'lote (cancela o controle)',
])
