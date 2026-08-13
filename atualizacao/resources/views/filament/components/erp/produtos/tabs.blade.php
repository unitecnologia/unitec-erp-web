<div class="erp-produtos__tabs-wrap">
    <div class="erp-produtos__tabs erp-produtos__tabs--view">
        <button
            type="button"
            wire:click="setViewFilter('produtos')"
            @class(['erp-produtos__tab', 'erp-produtos__tab--active' => ! $this->isSeriaisView()])
        >Produtos</button>
        <button
            type="button"
            wire:click="setViewFilter('seriais')"
            @class(['erp-produtos__tab', 'erp-produtos__tab--active' => $this->isSeriaisView()])
        >Seriais</button>
    </div>
</div>
