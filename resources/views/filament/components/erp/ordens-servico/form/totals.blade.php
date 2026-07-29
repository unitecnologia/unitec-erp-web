<div class="erp-os-totals">
    <div class="erp-os-totals__block">
        <div class="erp-os-totals__title">SubTotal</div>
        <div class="erp-os-totals__rows">
            <span class="erp-os-totals__label">Peças</span>
            <input type="text" readonly wire:model="subtotalPecas" class="erp-os-totals__value">
            <span class="erp-os-totals__label">Serviços</span>
            <input type="text" readonly wire:model="subtotalServicos" class="erp-os-totals__value">
            <span class="erp-os-totals__label">Geral</span>
            <input type="text" readonly wire:model="subtotalGeral" class="erp-os-totals__value">
        </div>
    </div>

    <div class="erp-os-totals__block">
        <div class="erp-os-totals__title">Descontos</div>
        <div class="erp-os-totals__rows">
            <span class="erp-os-totals__label">Peças</span>
            <input
                type="text"
                wire:model="descPecas"
                wire:blur="applyDescontoPecas"
                @disabled($this->osReadOnly())
                class="erp-os-totals__value erp-os-totals__value--edit"
            >
            <span class="erp-os-totals__label">Serviços</span>
            <input
                type="text"
                wire:model="descServicos"
                wire:blur="applyDescontoServicos"
                @disabled($this->osReadOnly())
                class="erp-os-totals__value erp-os-totals__value--edit"
            >
        </div>
    </div>

    <div class="erp-os-totals__block">
        <div class="erp-os-totals__title erp-os-totals__title--emphasis">Total</div>
        <div class="erp-os-totals__rows">
            <span class="erp-os-totals__label">Peças</span>
            <input type="text" readonly wire:model="totalPecas" class="erp-os-totals__value erp-os-totals__value--total">
            <span class="erp-os-totals__label">Serviços</span>
            <input type="text" readonly wire:model="totalServicos" class="erp-os-totals__value erp-os-totals__value--total">
            <span class="erp-os-totals__label">Geral</span>
            <input type="text" readonly wire:model="totalGeral" class="erp-os-totals__value erp-os-totals__value--total">
        </div>
    </div>
</div>
