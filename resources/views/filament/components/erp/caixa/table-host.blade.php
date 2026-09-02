<div class="erp-list-grid-area">
    <livewire:erp.caixa-list-table
        :conta-filter="$this->contaFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-caixa-list-table-host"
    />
</div>
