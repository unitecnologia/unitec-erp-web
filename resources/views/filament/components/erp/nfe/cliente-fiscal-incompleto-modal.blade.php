@php
    $nome = $this->nfeClienteFiscalIncompletoNome !== '' ? $this->nfeClienteFiscalIncompletoNome : '—';
    $campos = $this->nfeClienteFiscalIncompletoCampos;
    $lista = $campos === [] ? '' : implode(', ', $campos);
@endphp

{{-- Mesmo aviso-modal centralizado da OS; z-index elevado no CSS da NF-e. --}}
@include('filament.components.erp.aviso-modal', [
    'open' => $this->nfeClienteFiscalIncompletoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-nfe-cliente-fiscal-incompleto-title',
    'title' => 'Cliente incompleto para NF-e',
    'lines' => array_values(array_filter([
        'O cadastro do cliente <strong>'.e($nome).'</strong> não tem todos os dados obrigatórios para transmitir a NF-e.',
        $lista !== '' ? 'Campos faltantes: <strong>'.e($lista).'</strong>.' : null,
        'Corrija o cadastro e tente novamente pelo F3.',
    ])),
    'primaryLabel' => 'Abrir cadastro',
    'primaryAction' => 'abrirCadastroNfeClienteFiscalIncompleto',
    'secondaryLabel' => 'Fechar',
    'secondaryAction' => 'fecharNfeClienteFiscalIncompleto',
    'escapeAction' => 'fecharNfeClienteFiscalIncompleto',
    'backdropAction' => 'fecharNfeClienteFiscalIncompleto',
])
