@include('filament.components.erp.aviso-modal', [
    'open' => $this->pessoaCnpjAlteracoesOpen,
    'tone' => 'info',
    'icon' => 'i',
    'titleId' => 'erp-pessoa-cnpj-alt-title',
    'title' => 'Alterações encontradas no CNPJ',
    'lines' => [
        'A consulta retornou dados diferentes do cadastro atual. Selecione o que deseja atualizar:',
    ],
    'extraView' => 'filament.components.erp.pessoas.form.cnpj-alteracoes-list',
    'hint' => 'Campos vazios na consulta não apagam o cadastro. Grave o formulário para confirmar.',
    'primaryLabel' => 'Atualizar dados',
    'primaryAction' => 'aplicarAlteracoesCnpjPessoa',
    'secondaryLabel' => 'Manter cadastro',
    'secondaryAction' => 'manterCadastroCnpjPessoa',
    'escapeAction' => 'manterCadastroCnpjPessoa',
    'backdropAction' => 'manterCadastroCnpjPessoa',
])
