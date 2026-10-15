<div class="erp-list-grid-area">
    <livewire:erp.compra-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :local-search-de="$this->localSearchDe"
        :local-search-ate="$this->localSearchAte"
        :search-fields-active="$this->searchFieldsActive"
        :local-search-by-field="$this->localSearchByField"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-compra-list-table-host"
    />
</div>
