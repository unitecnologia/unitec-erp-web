<div class="erp-list-grid-area">
    <livewire:erp.venda-list-table
        :status-filter="$this->statusFilter"
        :tipo-filter="$this->tipoFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :local-search-de="$this->localSearchDe"
        :local-search-ate="$this->localSearchAte"
        :local-search-hora-de="$this->localSearchHoraDe"
        :local-search-hora-ate="$this->localSearchHoraAte"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-venda-list-table-host"
    />
</div>
