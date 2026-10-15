@php
    $nome = $this->nfseTomadorFiscalIncompletoNome !== '' ? $this->nfseTomadorFiscalIncompletoNome : '—';
    $campos = $this->nfseTomadorFiscalIncompletoCampos;
    $lista = $campos === [] ? '' : implode(', ', $campos);
@endphp

{{-- Mesmo aviso-modal centralizado da OS; z-index elevado no CSS da NF-e/NFS-e. --}}
@include('filament.components.erp.aviso-modal', [
    'open' => $this->nfseTomadorFiscalIncompletoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-nfse-tomador-fiscal-incompleto-title',
    'title' => 'Tomador incompleto para NFS-e',
    'lines' => array_values(array_filter([
        'O cadastro do tomador <strong>'.e($nome).'</strong> não tem todos os dados obrigatórios para transmitir a NFS-e.',
        $lista !== '' ? 'Campos faltantes: <strong>'.e($lista).'</strong>.' : null,
        'Corrija o cadastro e tente novamente pelo F3.',
    ])),
    'primaryLabel' => 'Abrir cadastro',
    'primaryAction' => 'abrirCadastroNfseTomadorFiscalIncompleto',
    'secondaryLabel' => 'Fechar',
    'secondaryAction' => 'fecharNfseTomadorFiscalIncompleto',
    'escapeAction' => 'fecharNfseTomadorFiscalIncompleto',
    'backdropAction' => 'fecharNfseTomadorFiscalIncompleto',
])
