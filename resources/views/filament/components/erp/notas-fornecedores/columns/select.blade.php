@php
    $record = $getRecord();
    $marcado = (int) $this->highlightedRecordId === (int) $record->getKey();
@endphp

<input
    type="checkbox"
    class="erp-nf-forn__check"
    @if ($marcado) checked @endif
    wire:click.prevent.stop="alternarSelecionado({{ $record->getKey() }})"
    wire:key="nf-forn-sel-{{ $record->getKey() }}-{{ $marcado ? '1' : '0' }}"
    title="{{ $marcado ? 'Desmarcar nota' : 'Marcar nota' }}"
    aria-label="{{ $marcado ? 'Desmarcar nota' : 'Marcar nota' }}"
    @click.stop
/>
