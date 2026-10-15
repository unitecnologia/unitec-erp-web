@php
    $nome = $this->osClienteFiscalIncompletoNome !== '' ? $this->osClienteFiscalIncompletoNome : '—';
    $campos = $this->osClienteFiscalIncompletoCampos;
    $lista = $campos === [] ? '' : implode(', ', $campos);
    $documento = $this->osClienteFiscalIncompletoDocumento !== '' ? $this->osClienteFiscalIncompletoDocumento : 'documento fiscal';
    $atalho = $this->osClienteFiscalIncompletoAtalho !== '' ? $this->osClienteFiscalIncompletoAtalho : 'o atalho';
@endphp

@include('filament.components.erp.aviso-modal', [
    'open' => $this->osClienteFiscalIncompletoOpen,
    'tone' => 'warning',
    'titleId' => 'erp-os-cliente-fiscal-incompleto-title',
    'title' => 'Cliente incompleto para '.$documento,
    'lines' => array_values(array_filter([
        'O cadastro do cliente <strong>'.e($nome).'</strong> não tem todos os dados obrigatórios para emitir a '.$documento.'.',
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
