<div class="erp-list-grid-area">
    <livewire:erp.person-list-table
        :status-filter="$this->statusFilter"
        :tipo-filter="$this->tipoFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-person-list-table-host"
    />
</div>
