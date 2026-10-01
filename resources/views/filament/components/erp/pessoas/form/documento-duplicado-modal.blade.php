@include('filament.components.erp.aviso-modal', [
    'open' => $this->personDocumentoDuplicadoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-pessoa-doc-dup-title',
    'title' => $this->personDocumentoDuplicadoTipo.' já cadastrado',
    'lines' => array_values(array_filter([
        'Já existe um cadastro com este '.$this->personDocumentoDuplicadoTipo.'.',
        $this->personDocumentoDuplicadoCodigo !== ''
            ? '<strong>Código:</strong> '.e($this->personDocumentoDuplicadoCodigo)
            : null,
        $this->personDocumentoDuplicadoNome !== ''
            ? '<strong>Pessoa:</strong> '.e($this->personDocumentoDuplicadoNome)
            : null,
        'Não é possível gravar outro cadastro com o mesmo documento.',
    ])),
    'primaryLabel' => 'OK, continuar editando',
    'primaryAction' => 'dismissPersonDocumentoDuplicado',
    'escapeAction' => 'dismissPersonDocumentoDuplicado',
    'backdropAction' => 'dismissPersonDocumentoDuplicado',
])
