<div class="erp-list-grid-area">
    <livewire:erp.orcamento-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-orcamento-list-table-host"
    />
</div>
