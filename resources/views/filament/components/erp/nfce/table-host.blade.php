<div class="erp-list-grid-area">
    <livewire:erp.nfce-list-table
        :status-filter="$this->statusFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :chave-filter-applied="$this->chaveFilterApplied"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        :selected-transmit-ids="$this->nfceSelecionadosTransmitir"
        :highlighted-record-id="$this->highlightedRecordId"
        wire:key="erp-nfce-list-table-host"
    />
</div>
