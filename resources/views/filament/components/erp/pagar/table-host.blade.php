<div class="erp-list-grid-area">
    <livewire:erp.conta-pagar-list-table
        :situacao-filter="$this->situacaoFilter"
        :fornecedor-filter="$this->fornecedorFilter"
        :search-fields-active="$this->searchFieldsActive"
        :local-search-by-field="$this->localSearchByField"
        :local-search-de="$this->localSearchDe"
        :local-search-ate="$this->localSearchAte"
        :skip-fornecedor-search="$this->shouldSkipContaPagarFornecedorSearch()"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        wire:key="erp-pagar-list-table-host"
    />
</div>
