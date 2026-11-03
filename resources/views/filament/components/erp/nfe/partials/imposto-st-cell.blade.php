@props([
    'index',
    'field',
    'enabled' => false,
])
@if ($enabled)
    <td>
        <input
            type="text"
            wire:model.blur="nfeModalRows.{{ $index }}.{{ $field }}"
            wire:click.stop
            data-erp-nfe-impostos-enter
            x-on:focus="$el.removeAttribute('readonly'); $el.select()"
            x-on:click.stop="$el.removeAttribute('readonly'); $el.select()"
            @mouseup.prevent
            class="erp-nfe-lancamento-modal__cell-input erp-nfe-lancamento-modal__cell-input--num"
            autocomplete="off"
        >
    </td>
@else
    <td class="erp-nfe-lancamento-modal__num erp-nfe-impostos__readonly" title="Não aplicável ao CST/CSOSN">—</td>
@endif
