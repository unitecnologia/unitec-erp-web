@php($record = $getRecord())

@if (in_array($record->status, [\App\Models\Entrega::STATUS_PENDENTE, \App\Models\Entrega::STATUS_EM_EXPEDICAO], true))
    <input
        type="checkbox"
        class="erp-expedicao__check"
        value="{{ $record->getKey() }}"
        @checked(in_array((string) $record->getKey(), $this->selecionados, true))
        wire:click.prevent.stop="toggleSelecionado({{ $record->getKey() }})"
        wire:key="exp-sel-{{ $record->getKey() }}-{{ in_array((string) $record->getKey(), $this->selecionados, true) ? '1' : '0' }}"
    >
@endif
