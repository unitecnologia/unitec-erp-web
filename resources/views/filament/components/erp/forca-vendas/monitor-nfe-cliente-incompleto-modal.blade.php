@php
    $loteLinhas = $this->osClienteFiscalIncompletoLoteLinhas;
@endphp

@if ($loteLinhas !== [])
    @include('filament.components.erp.aviso-modal', [
        'open' => $this->osClienteFiscalIncompletoOpen,
        'tone' => 'warning',
        'titleId' => 'erp-fv-mon-nfe-cliente-incompleto-title',
        'title' => 'Clientes incompletos para NF-e',
        'lines' => $loteLinhas,
        'primaryLabel' => 'Abrir cadastro',
        'primaryAction' => 'abrirCadastroClienteFiscalIncompleto',
        'secondaryLabel' => 'Fechar',
        'secondaryAction' => 'fecharOsClienteFiscalIncompleto',
        'escapeAction' => 'fecharOsClienteFiscalIncompleto',
        'backdropAction' => 'fecharOsClienteFiscalIncompleto',
    ])
@else
    {{-- Mesmo modal centralizado da OS (textos NF-e / Emitir NF-e). --}}
    @include('filament.components.erp.ordens-servico.os-cliente-fiscal-incompleto-modal')
@endif
