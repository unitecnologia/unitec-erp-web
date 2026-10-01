<div class="erp-ajustes-estoque__period-inline">
    <span class="erp-ajustes-estoque__filter-title">Período</span>

    <div class="erp-ajustes-estoque__period">
        <label class="erp-ajustes-estoque__period-label">
            De
            <input type="date" data-wire-field="periodoDe" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input">
        </label>
        <label class="erp-ajustes-estoque__period-label">
            até
            <input type="date" data-wire-field="periodoAte" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input">
        </label>
        <button type="button" wire:click="applyPeriodFilter" onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-ajustes-estoque') ?? document)" class="erp-ajustes-estoque__btn">Filtrar</button>
    </div>
</div>
