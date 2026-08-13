@include('filament.components.erp.shared.cadastro-list-screen', [
    'pageClass' => 'erp-empresas',
    'searchFields' => [
        'codigo' => 'CÓDIGO',
        'fantasia' => 'FANTASIA',
        'razao_social' => 'RAZÃO',
        'cidade' => 'CIDADE',
        'cnpj' => 'CNPJ',
        'ie' => 'IE',
    ],
    'uppercaseColumns' => 'fantasia,razao_social,cidade',
    'wireKeyPrefix' => 'empresas',
    'hint' => 'Pressione Enter ou clique em Pesquisa. Use as setas para navegar na lista.',
])

<script data-navigate-track>
    if (! window.__erpEmpresaFocusBound) {
        window.__erpEmpresaFocusBound = true;

        window.Livewire.on('erp-empresa-focus-search', () => {
            document.querySelector('.erp-empresas__search-text')?.focus();
            document.querySelector('.erp-empresas__search-text')?.select?.();
        });
    }
</script>
