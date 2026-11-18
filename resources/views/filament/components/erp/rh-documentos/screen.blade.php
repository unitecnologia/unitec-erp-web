@include('filament.components.erp.shared.logistica-simple-screen', [
    'pageClass' => 'erp-rh-documentos',
    'searchFields' => [
        'titulo' => 'DOCUMENTO',
        'funcionario' => 'FUNCIONÁRIO',
        'id' => 'CÓDIGO',
    ],
    'uppercaseFields' => ['titulo', 'funcionario'],
    'numericFields' => ['id'],
    'nomeSearchLabel' => 'DOCUMENTO',
    'deleteHint' => 'documento',
])
