<div class="erp-ajustes-estoque__filter-block">
    <span class="erp-ajustes-estoque__filter-title">Período</span>

    <label class="erp-ajustes-estoque__period-check">
        <input type="checkbox" wire:model.live="informarPeriodo">
        Informar Período
    </label>

    <div class="erp-ajustes-estoque__period">
        <label class="erp-ajustes-estoque__period-label">
            Período de
            <input type="date" data-wire-field="periodoDe" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input" @disabled(! $this->informarPeriodo)>
        </label>
        <label class="erp-ajustes-estoque__period-label">
            até
            <input type="date" data-wire-field="periodoAte" data-erp-date-wire="iso" class="erp-ajustes-estoque__period-input" @disabled(! $this->informarPeriodo)>
        </label>
        <button type="button" wire:click="applyPeriodFilter" onclick="window.ErpDatepicker?.commitAllIn(this.closest('.erp-ajustes-estoque') ?? document)" class="erp-ajustes-estoque__btn" @disabled(! $this->informarPeriodo)>Filtrar Período</button>
    </div>
</div>
