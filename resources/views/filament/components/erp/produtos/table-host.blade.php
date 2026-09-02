<div class="erp-list-grid-area">
    <livewire:erp.product-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :view-filter="$this->viewFilter"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-product-list-table-host"
    />
</div>
