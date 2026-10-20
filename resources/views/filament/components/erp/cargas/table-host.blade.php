<div class="erp-list-grid-area">
    <livewire:erp.carga-list-table
        :status-filter="$this->statusFilter"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :local-search="$this->localSearch"
        :search-fields-active="$this->activeSearchFields()"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        :sort-column="$this->sortColumn"
        :sort-direction="$this->sortDirection"
        :selecionados="$this->selecionados"
        :highlighted-record-id="$this->highlightedRecordId"
        wire:key="erp-carga-list-table-host"
    />
</div>
