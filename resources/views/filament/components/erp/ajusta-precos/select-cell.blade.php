@php($record = $getRecord())

<input
    type="checkbox"
    class="erp-ajusta-precos__check"
    value="{{ $record->getKey() }}"
    @checked(in_array((string) $record->getKey(), $this->selecionados, true))
    wire:click.prevent.stop="toggleSelecionado({{ $record->getKey() }})"
    wire:key="ajp-sel-{{ $record->getKey() }}-{{ in_array((string) $record->getKey(), $this->selecionados, true) ? '1' : '0' }}"
    title="Marcar para aplicação em lote"
>
