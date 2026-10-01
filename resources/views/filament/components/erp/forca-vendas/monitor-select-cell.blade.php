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
    wire:key="fv-sel-{{ $key }}-{{ $marcado ? '1' : '0' }}"
    x-data
    @click.stop="
        const row = $el.closest('.fi-ta-row');
        if (row) {
            row.classList.toggle('erp-row-selected', $el.checked);
        }
    "
/>
