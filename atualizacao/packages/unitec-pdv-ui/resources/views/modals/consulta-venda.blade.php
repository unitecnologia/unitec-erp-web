@if ($this->activeModal === 'consulta_venda')
    <div class="erp-pdv-modal" role="dialog" aria-label="Consulta de venda" wire:keydown.escape="cancelConsultaVenda">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelConsultaVenda"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-pdv-modal__window--consulta-venda">
            <header class="erp-pdv-modal__header">
                <h2>Ctrl+O — Consulta / Estorno de Venda</h2>
            </header>
            <div class="erp-pdv-modal__body erp-pdv-consulta-venda">
                <div class="erp-pdv-consulta-venda__list">
                    <label class="erp-pdv-modal__label" for="erp-pdv-consulta-venda-search">Número ou vendedor</label>
                    <input
                        id="erp-pdv-consulta-venda-search"
                        type="text"
                        wire:model.live.debounce.150ms="consultaVendaSearch"
                        class="erp-pdv-modal__input"
                        data-erp-uppercase
                        autocomplete="off"
                    >
                    <div class="erp-pdv-modal__grid-scroll erp-pdv-consulta-venda__grid-scroll">
                        <table class="erp-pdv__grid erp-pdv__grid--consulta-venda erp-pdv-modal__grid">
                            <colgroup>
                                <col class="erp-pdv-consulta-venda__col-flag">
                                <col class="erp-pdv-consulta-venda__col-numero">
                                <col class="erp-pdv-consulta-venda__col-forma">
                                <col class="erp-pdv-consulta-venda__col-total">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th class="erp-pdv__grid-col-flag" aria-label="Marcar"></th>
                                    <th>Número</th>
                                    <th class="erp-pdv-consulta-venda__col-forma-header">FORMA DE PAGAMENTO</th>
                                    <th class="erp-pdv-consulta-venda__cell-total">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->consultaVendaResults as $index => $row)
                                    @php($isSelected = $this->isConsultaVendaRowSelected((int) $index))
                                    <tr
                                        wire:click="selectConsultaVendaRow({{ $index }})"
                                        wire:key="pdv-consulta-venda-{{ $row['venda_id'] ?? $index }}"
                                        id="erp-pdv-consulta-venda-row-{{ $index }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv__grid-row--marked' => $isSelected,
                                        ])
                                    >
                                        <td
                                            class="erp-pdv__grid-col-flag"
                                            wire:click.stop="selectConsultaVendaRow({{ $index }})"
                                        >
                                            <span
                                                class="erp-pdv-consulta-venda__checkbox-visual"
                                                aria-hidden="true"
                                            ></span>
                                            <span class="erp-pdv-consulta-venda__checkbox-label">
                                                {{ $isSelected ? 'Marcada' : 'Não marcada' }}
                                            </span>
                                        </td>
                                        <td class="erp-pdv-consulta-venda__cell-numero">{{ $row['numero'] ?? '—' }}</td>
                                        <td class="erp-pdv-consulta-venda__cell-forma" title="{{ $row['forma'] ?? '—' }}">{{ $row['forma'] ?? '—' }}</td>
                                        <td class="erp-pdv-consulta-venda__cell-total">
                                            <div class="erp-pdv-consulta-venda__total-line">
                                                <span class="erp-pdv-consulta-venda__total-currency">R$</span>
                                                <span class="erp-pdv-consulta-venda__total-value">{{ $row['total'] ?? '0,00' }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="erp-pdv__grid-empty">
                                        <td colspan="4">Nenhuma venda neste caixa.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($this->consultaVendaDetalhe)
                    <div class="erp-pdv-consulta-venda__detail">
                        <h3 class="erp-pdv-consulta-venda__detail-title">
                            Venda #{{ $this->consultaVendaDetalhe['numero'] ?? '—' }}
                        </h3>
                        <p class="erp-pdv-consulta-venda__detail-meta">
                            <span>Cliente: {{ $this->consultaVendaDetalhe['cliente'] ?? '—' }}</span>
                            <span>Total: R$ {{ $this->consultaVendaDetalhe['total'] ?? '0,00' }}</span>
                        </p>

                        <div class="erp-pdv-consulta-venda__section">
                            <strong>Itens</strong>
                            <div class="erp-pdv-consulta-venda__items-scroll">
                                <table class="erp-pdv-consulta-venda__items-grid">
                                    <thead>
                                        <tr>
                                            <th>Descrição</th>
                                            <th class="erp-pdv__grid-col-num">Qtd</th>
                                            <th class="erp-pdv__grid-col-num">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->consultaVendaDetalhe['itens'] ?? [] as $item)
                                            <tr>
                                                <td class="erp-pdv-consulta-venda__item-desc">{{ $item['descricao'] ?? '—' }}</td>
                                                <td class="erp-pdv__grid-col-num">
                                                    {{ number_format((float) ($item['quantidade'] ?? 0), 3, ',', '.') }}
                                                </td>
                                                <td class="erp-pdv__grid-col-num">R$ {{ $item['total'] ?? '0,00' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        @if (! empty($this->consultaVendaDetalhe['pagamentos']))
                            <div class="erp-pdv-consulta-venda__section">
                                <strong>Pagamentos</strong>
                                <table class="erp-pdv-consulta-venda__pagamentos-grid">
                                    <tbody>
                                        @foreach ($this->consultaVendaDetalhe['pagamentos'] as $pag)
                                            <tr>
                                                <td>{{ $pag['forma'] ?? '—' }}</td>
                                                <td class="erp-pdv__grid-col-num">R$ {{ $pag['valor'] ?? '0,00' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="erp-pdv-consulta-venda__detail erp-pdv-consulta-venda__detail--empty">
                        <p class="erp-pdv-modal__hint">Selecione uma venda para ver os detalhes.</p>
                    </div>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="imprimirConsultaVenda" class="erp-pdv-modal__btn">Imprimir</button>
                <button type="button" wire:click="requestEstornarConsultaVenda" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger">Estornar</button>
                <button type="button" wire:click="cancelConsultaVenda" class="erp-pdv-modal__btn"><kbd>Esc</kbd> Fechar</button>
            </footer>
        </div>
    </div>
@endif
