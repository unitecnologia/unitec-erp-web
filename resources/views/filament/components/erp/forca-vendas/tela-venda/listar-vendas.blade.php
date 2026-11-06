@if ($this->fvListarVendasOpen)
    <div
        class="erp-pdv-modal erp-fv-tv-listar-vendas"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-listar-vendas-title"
    >
        <div class="erp-pdv-modal__backdrop" wire:click="fecharListarVendas"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-fv-tv-listar-vendas__window">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-fv-listar-vendas-title">Vendas — Tela de Venda</h2>
                <button
                    type="button"
                    class="erp-pdv-modal__close"
                    title="Fechar"
                    wire:click="fecharListarVendas"
                >✕</button>
            </header>

            <div class="erp-pdv-modal__body">
                <div class="erp-fv-tv-listar-vendas__filters">
                    <label class="erp-fv-tv-listar-vendas__field erp-fv-tv-listar-vendas__field--busca" for="erp-fv-listar-vendas-busca">
                        <span class="erp-fv-tv-listar-vendas__label">Localizar (DAV ou cliente)</span>
                        <input
                            id="erp-fv-listar-vendas-busca"
                            type="text"
                            wire:model.live.debounce.180ms="fvListarVendasBusca"
                            wire:keydown.escape.prevent="fecharListarVendas"
                            class="erp-fv-tv-listar-vendas__input"
                            data-erp-uppercase
                            autocomplete="off"
                            placeholder="Número do DAV ou nome do cliente…"
                        >
                    </label>
                    <div class="erp-fv-tv-listar-vendas__field erp-fv-tv-listar-vendas__field--periodo">
                        <span class="erp-fv-tv-listar-vendas__label">Período</span>
                        <div class="erp-fv-tv-listar-vendas__periodo">
                            <input
                                id="erp-fv-listar-vendas-de"
                                type="date"
                                wire:model.change="fvListarVendasDataDe"
                                data-erp-date
                                data-wire-field="fvListarVendasDataDe"
                                data-erp-date-wire="iso"
                                data-erp-date-initial="{{ $this->fvListarVendasDataDe }}"
                                wire:keydown.escape.prevent="fecharListarVendas"
                                class="erp-fv-tv-listar-vendas__input erp-fv-tv-listar-vendas__input--date"
                                title="Data inicial"
                                aria-label="Data inicial"
                            >
                            <span class="erp-fv-tv-listar-vendas__periodo-sep" aria-hidden="true">até</span>
                            <input
                                id="erp-fv-listar-vendas-ate"
                                type="date"
                                wire:model.change="fvListarVendasDataAte"
                                data-erp-date
                                data-wire-field="fvListarVendasDataAte"
                                data-erp-date-wire="iso"
                                data-erp-date-initial="{{ $this->fvListarVendasDataAte }}"
                                wire:keydown.escape.prevent="fecharListarVendas"
                                class="erp-fv-tv-listar-vendas__input erp-fv-tv-listar-vendas__input--date"
                                title="Data final"
                                aria-label="Data final"
                            >
                        </div>
                    </div>
                    <p class="erp-fv-tv-listar-vendas__hint">
                        Somente Tela de Venda desta empresa · padrão: hoje
                    </p>
                </div>

                <div class="erp-pdv-modal__grid-scroll erp-fv-tv-listar-vendas__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-fv-tv-listar-vendas__grid">
                        <colgroup>
                            <col class="erp-fv-tv-listar-vendas__col-dav">
                            <col class="erp-fv-tv-listar-vendas__col-quando">
                            <col class="erp-fv-tv-listar-vendas__col-cliente">
                            <col class="erp-fv-tv-listar-vendas__col-total">
                            <col class="erp-fv-tv-listar-vendas__col-sit">
                            <col class="erp-fv-tv-listar-vendas__col-acoes">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>DAV</th>
                                <th>Data / Hora</th>
                                <th>Cliente</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                                <th>Sit.</th>
                                <th class="erp-pdv__grid-col-center" title="Ações">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->fvListarVendasRows as $row)
                                <tr wire:key="fv-listar-venda-{{ $row['id'] }}" class="erp-pdv__grid-row">
                                    <td class="erp-fv-tv-listar-vendas__dav"><strong>{{ $row['dav'] }}</strong></td>
                                    <td class="erp-fv-tv-listar-vendas__quando">
                                        <span>{{ $row['data'] }}</span>
                                        <span class="erp-fv-tv-listar-vendas__hora">{{ $row['hora'] }}</span>
                                    </td>
                                    <td class="erp-fv-tv-listar-vendas__cliente" title="{{ $row['cliente'] }}">{{ $row['cliente'] }}</td>
                                    <td class="erp-fv-tv-listar-vendas__total">
                                        <span class="erp-fv-tv-listar-vendas__money">
                                            <span class="erp-fv-tv-listar-vendas__money-rs">R$</span>
                                            <span class="erp-fv-tv-listar-vendas__money-valor">{{ $row['total'] }}</span>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="erp-fv-tv-listar-vendas__badge erp-fv-tv-listar-vendas__badge--{{ $row['situacao_key'] }}">
                                            {{ $row['situacao'] }}
                                        </span>
                                    </td>
                                    <td class="erp-pdv__grid-col-center erp-fv-tv-listar-vendas__td-acoes">
                                        <div class="erp-fv-tv-listar-vendas__acoes">
                                            <button
                                                type="button"
                                                class="erp-fv-tv-listar-vendas__acao erp-fv-tv-listar-vendas__acao--print"
                                                title="Imprimir"
                                                aria-label="Imprimir comprovante"
                                                wire:click="imprimirFvListarVenda({{ $row['id'] }})"
                                                wire:loading.attr="disabled"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M6 9V2h12v7"/>
                                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                                    <path d="M6 14h12v8H6z"/>
                                                </svg>
                                            </button>
                                            <button
                                                type="button"
                                                class="erp-fv-tv-listar-vendas__acao erp-fv-tv-listar-vendas__acao--copy"
                                                title="Copiar Produtos"
                                                aria-label="Copiar produtos para nova venda"
                                                wire:click="solicitarCopiarProdutosFvListarVenda({{ $row['id'] }})"
                                                wire:loading.attr="disabled"
                                            >
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <rect x="9" y="9" width="13" height="13" rx="2"/>
                                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="erp-fv-tv-listar-vendas__empty">
                                        Nenhuma venda da Tela de Venda encontrada no período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->fvListarVendasHasMore)
                    <div class="erp-fv-tv-listar-vendas__more">
                        <button
                            type="button"
                            class="erp-pdv-modal__btn"
                            wire:click="carregarMaisFvListarVendas"
                            wire:loading.attr="disabled"
                        >Carregar mais</button>
                    </div>
                @endif
            </div>

            <footer class="erp-pdv-modal__footer">
                <button type="button" class="erp-pdv-modal__btn" wire:click="fecharListarVendas">
                    <kbd>Esc</kbd> Fechar
                </button>
            </footer>
        </div>
    </div>
@endif

@if ($this->fvListarCopiarConfirmOpen)
    <div
        class="erp-aviso-modal erp-aviso-modal--warning"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-listar-copiar-title"
    >
        <div class="erp-aviso-modal__backdrop" wire:click="cancelarCopiarProdutosFvListarVenda"></div>
        <div class="erp-aviso-modal__box">
            <div class="erp-aviso-modal__icon" aria-hidden="true">!</div>
            <h2 class="erp-aviso-modal__title" id="erp-fv-listar-copiar-title">Substituir itens da venda?</h2>
            <div class="erp-aviso-modal__body">
                <p class="erp-aviso-modal__text">
                    A grade atual já possui produtos. Ao confirmar, eles serão
                    <strong>substituídos</strong> pelos produtos da venda selecionada
                    (somente produto e quantidade, com preços atuais).
                </p>
            </div>
            <div class="erp-aviso-modal__actions">
                <button
                    type="button"
                    id="erp-fv-listar-copiar-confirm"
                    class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                    wire:click="confirmarCopiarProdutosFvListarVenda"
                    wire:loading.attr="disabled"
                >Sim, copiar produtos</button>
                <button
                    type="button"
                    class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                    wire:click="cancelarCopiarProdutosFvListarVenda"
                    wire:loading.attr="disabled"
                >Cancelar</button>
            </div>
        </div>
    </div>
@endif
