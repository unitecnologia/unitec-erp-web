<div class="erp-os-totals erp-orc-totals">
    <span class="erp-orc-totals__label">Sub. Peças</span>
    <input type="text" readonly wire:model="subtotalPecas" class="erp-orc-totals__value">

    <span class="erp-orc-totals__label">Sub. Serviços</span>
    <input type="text" readonly wire:model="subtotalServicos" class="erp-orc-totals__value">

    <span class="erp-orc-totals__label">Sub. Geral</span>
    <input type="text" readonly wire:model="subtotalGeral" class="erp-orc-totals__value">

    <span class="erp-orc-totals__label">Desc. Peças</span>
    <input
        type="text"
        wire:model="descPecas"
        wire:blur="applyDescontoPecas"
        wire:keydown.enter.prevent="applyDescontoPecas"
        data-mask="money-br"
        inputmode="decimal"
        autocomplete="off"
        @disabled($this->osReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
    >

    <span class="erp-orc-totals__label">Desc. Serviços</span>
    <input
        type="text"
        wire:model="descServicos"
        wire:blur="applyDescontoServicos"
        wire:keydown.enter.prevent="applyDescontoServicos"
        data-mask="money-br"
        inputmode="decimal"
        autocomplete="off"
        @disabled($this->osReadOnly())
        class="erp-orc-totals__value erp-orc-totals__value--edit"
    >

    <span class="erp-orc-totals__label erp-orc-totals__label--total">Total Peças</span>
    <input type="text" readonly wire:model="totalPecas" class="erp-orc-totals__value erp-orc-totals__value--total">

    <span class="erp-orc-totals__label">Total Serviços</span>
    <input type="text" readonly wire:model="totalServicos" class="erp-orc-totals__value erp-orc-totals__value--total">

    <span class="erp-orc-totals__label">Total Geral</span>
    <input type="text" readonly wire:model="totalGeral" class="erp-orc-totals__value erp-orc-totals__value--total">
</div>
