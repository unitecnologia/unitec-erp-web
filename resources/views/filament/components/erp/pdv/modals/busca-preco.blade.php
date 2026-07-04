@if ($this->activeModal === 'busca_preco')
    <div class="erp-pdv-modal" role="dialog" aria-label="Busca preço">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelBuscaPreco"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+L — Busca Preço</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <label class="erp-pdv-modal__label" for="erp-pdv-busca-preco-search">Código, barras ou descrição</label>
                <input
                    id="erp-pdv-busca-preco-search"
                    type="text"
                    wire:model.live.debounce.150ms="buscaPrecoSearch"
                    wire:keydown.enter.prevent="confirmBuscaPreco"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >
                @if ($this->buscaPrecoResult)
                    <dl class="erp-pdv-busca-preco__details">
                        <div><dt>Código</dt><dd>{{ $this->buscaPrecoResult['codigo'] ?? '—' }}</dd></div>
                        <div><dt>Descrição</dt><dd>{{ $this->buscaPrecoResult['descricao'] ?? '—' }}</dd></div>
                        <div><dt>Referência</dt><dd>{{ $this->buscaPrecoResult['referencia'] ?? '' }}</dd></div>
                        <div><dt>Barras</dt><dd>{{ $this->buscaPrecoResult['codigo_barras'] ?? '' }}</dd></div>
                        <div><dt>Unidade</dt><dd>{{ $this->buscaPrecoResult['unidade'] ?? 'UN' }}</dd></div>
                        <div><dt>Estoque</dt>
                            <dd>
                                @php $estoque = (float) ($this->buscaPrecoResult['estoque'] ?? 0); @endphp
                                {{ fmod($estoque, 1.0) === 0.0 ? (int) $estoque : number_format($estoque, 3, ',', '') }}
                            </dd>
                        </div>
                        <div class="erp-pdv-busca-preco__preco"><dt>Preço</dt><dd>R$ {{ $this->buscaPrecoPrecoVendaFormatado }}</dd></div>
                        @if ($this->buscaPrecoPrecoTabelaFormatado)
                            <div class="erp-pdv-busca-preco__preco"><dt>Tab. Preço</dt><dd>R$ {{ $this->buscaPrecoPrecoTabelaFormatado }}</dd></div>
                        @endif
                        @if ($this->buscaPrecoResult['em_promocao'] ?? false)
                            <div><dt>Promoção</dt><dd>Sim</dd></div>
                        @endif
                    </dl>
                @else
                    <p class="erp-pdv-modal__hint">Informe um termo para consultar o preço.</p>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmBuscaPreco" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Fechar</button>
            </footer>
        </div>
    </div>
@endif
