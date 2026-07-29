@php
    $record = $getRecord();
    $key = (string) $record->getKey();
    $marcado = in_array($key, $this->selecionados, true);
@endphp

<input
    type="checkbox"
    class="erp-fv-mon__check"
    value="{{ $key }}"
    @checked($marcado)
    wire:click.stop="alternarSelecionado({{ $record->getKey() }})"
    wire:key="fv-sel-{{ $key }}"
    @click.stop
/>
