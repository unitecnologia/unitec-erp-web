@php
    $record = $getRecord();
    $marcado = (int) $this->highlightedRecordId === (int) $record->getKey();
@endphp

<input
    type="checkbox"
    class="erp-nfe__select-check"
    value="{{ $record->getKey() }}"
    @checked($marcado)
    wire:click.prevent.stop="toggleNfeSelecionado({{ $record->getKey() }})"
    wire:key="nfe-sel-{{ $record->getKey() }}-{{ $marcado ? '1' : '0' }}"
    title="{{ $marcado ? 'Desmarcar NF-e' : 'Marcar NF-e' }}"
    @click.stop
>
