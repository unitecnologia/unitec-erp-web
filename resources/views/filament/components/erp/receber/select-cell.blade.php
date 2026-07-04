@php
    $record = $getRecord();
    $podeMarcar = $this->clienteFilter !== 'todos'
        && is_numeric($this->clienteFilter)
        && (float) $record->saldo > 0;
@endphp

<input
    type="checkbox"
    class="erp-receber__check"
    value="{{ $record->getKey() }}"
    wire:model.live="selecionadosParaBaixa"
    wire:key="receber-baixa-{{ $record->getKey() }}"
    @disabled(! $podeMarcar)
    title="{{ $podeMarcar ? 'Marcar para baixa' : 'Selecione um cliente para marcar contas' }}"
    @click.stop
/>
