@php($record = $getRecord())

<input
    type="checkbox"
    class="erp-fv-mon__check"
    value="{{ $record->getKey() }}"
    wire:model.live="selecionados"
    wire:key="fv-sel-{{ $record->getKey() }}"
    @click.stop
/>
