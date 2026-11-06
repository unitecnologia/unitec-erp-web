<div class="erp-list-grid-area">
    <livewire:erp.os-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        :highlighted-record-id="$this->highlightedRecordId"
        wire:key="erp-os-list-table-host"
    />
</div>
