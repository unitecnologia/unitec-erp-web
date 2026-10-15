@php
    $nome = $this->osClienteFiscalIncompletoNome !== '' ? $this->osClienteFiscalIncompletoNome : '—';
    $campos = $this->osClienteFiscalIncompletoCampos;
    $lista = $campos === [] ? '' : implode(', ', $campos);
    $documento = $this->osClienteFiscalIncompletoDocumento !== '' ? $this->osClienteFiscalIncompletoDocumento : 'NF-e';
    $atalho = $this->osClienteFiscalIncompletoAtalho !== '' ? $this->osClienteFiscalIncompletoAtalho : 'F7';
@endphp

{{-- Mesmo aviso-modal centralizado da OS/Monitor; textos adaptados ao fornecedor. --}}
@include('filament.components.erp.aviso-modal', [
    'open' => $this->osClienteFiscalIncompletoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-devcompra-nfe-fornecedor-incompleto-title',
    'title' => 'Fornecedor incompleto para '.$documento,
    'lines' => array_values(array_filter([
        'O cadastro do fornecedor <strong>'.e($nome).'</strong> não tem todos os dados obrigatórios para emitir a '.$documento.'.',
        $lista !== '' ? 'Campos faltantes: <strong>'.e($lista).'</strong>.' : null,
        'Corrija o cadastro e tente novamente pelo '.$atalho.'.',
    ])),
    'primaryLabel' => 'Abrir cadastro',
    'primaryAction' => 'abrirCadastroClienteFiscalIncompleto',
    'secondaryLabel' => 'Fechar',
    'secondaryAction' => 'fecharOsClienteFiscalIncompleto',
    'escapeAction' => 'fecharOsClienteFiscalIncompleto',
    'backdropAction' => 'fecharOsClienteFiscalIncompleto',
])
