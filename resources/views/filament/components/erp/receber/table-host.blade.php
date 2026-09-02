<div class="erp-list-grid-area">
    <livewire:erp.conta-receber-list-table
        :situacao-filter="$this->situacaoFilter"
        :forma-filter="$this->formaFilter"
        :cliente-filter="$this->clienteFilter"
        :search-column="$this->searchColumn"
        :local-search="$this->localSearch"
        :periodo-de-applied="$this->periodoDeApplied"
        :periodo-ate-applied="$this->periodoAteApplied"
        :skip-local-search="$this->shouldSkipContaReceberLocalSearch()"
        :per-page="(int) ($this->tableRecordsPerPage ?? 50)"
        :selecionados-para-baixa="$this->selecionadosParaBaixa"
        wire:key="erp-receber-list-table-host"
    />
</div>
