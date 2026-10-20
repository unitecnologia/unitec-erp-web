<div class="erp-list-grid-area">
    <livewire:erp.nfe-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :search-fields-active="$this->searchFieldsActive"
        :local-search-by-field="$this->localSearchByField"
        :cliente-filter="$this->clienteFilter"
        :local-search-de="$this->localSearchDe"
        :local-search-ate="$this->localSearchAte"
        :skip-local-cliente-search="$this->shouldSkipLocalSearchWhileTyping()"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        :highlighted-record-id="$this->highlightedRecordId"
        wire:key="erp-nfe-list-table-host"
    />
</div>
