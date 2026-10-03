<div class="erp-orc-totals">
    <span class="erp-orc-totals__label">SUBTOTAL |</span>
    <input type="text" readonly wire:model="subtotalDisplay" class="erp-orc-totals__value">

    <span class="erp-orc-totals__label">DESCONTO NO TOTAL:</span>
    <span class="erp-orc-totals__label">%</span>
    <input
        type="text"
        wire:model="percentualDescontoDisplay"
        wire:blur="applyDescontoFromPercentual($event.target.value)"
        wire:keydown.enter.prevent="applyDescontoFromPercentual($event.target.value)"
        @disabled($this->orcamentoReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
        inputmode="decimal"
        autocomplete="off"
        aria-label="Desconto percentual do orçamento"
    >

    <span class="erp-orc-totals__label">R$</span>
    <input
        type="text"
        wire:model="descontoValorDisplay"
        wire:blur="applyDescontoFromValor($event.target.value)"
        wire:keydown.enter.prevent="applyDescontoFromValor($event.target.value)"
        @disabled($this->orcamentoReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
        inputmode="decimal"
        autocomplete="off"
        aria-label="Desconto em reais do orçamento"
    >

    <span class="erp-orc-totals__label erp-orc-totals__label--total">TOTAL |</span>
    <input type="text" readonly wire:model="totalDisplay" class="erp-orc-totals__value erp-orc-totals__value--total">
</div>
