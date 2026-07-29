@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-unidades',
    'searchFields' => [
        'arquivo_nome' => 'ARQUIVO',
        'id' => 'CÓDIGO',
        'arquivo_numero' => 'Nº ARQUIVO',
    ],
    'uppercaseColumns' => 'arquivo_nome',
    'wireKeyPrefix' => 'boleto-retorno',
    'hint' => 'Arquivos de retorno do banco. F3 para detalhar ocorrências dos títulos.',
])
