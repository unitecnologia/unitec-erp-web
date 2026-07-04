@if ($this->activeModal === 'consulta_venda')
    <div class="erp-pdv-modal" role="dialog" aria-label="Consulta de venda">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelConsultaVenda"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide">
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
                    <div class="erp-pdv-modal__grid-scroll">
                        <table class="erp-pdv__grid erp-pdv-modal__grid">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Forma</th>
                                    <th class="erp-pdv__grid-col-num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->consultaVendaResults as $index => $row)
                                    <tr
                                        wire:click="selectConsultaVendaRow({{ $index }})"
                                        wire:key="pdv-consulta-venda-{{ $row['venda_id'] ?? $index }}"
                                        id="erp-pdv-consulta-venda-row-{{ $index }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv__grid-row--selected' => $this->selectedConsultaVendaIndex === $index,
                                        ])
                                    >
                                        <td>{{ $row['numero'] ?? '—' }}</td>
                                        <td>{{ $row['forma'] ?? '—' }}</td>
                                        <td class="erp-pdv__grid-col-num">{{ $row['total'] ?? '0,00' }}</td>
                                    </tr>
                                @empty
                                    <tr class="erp-pdv__grid-empty">
                                        <td colspan="3">Nenhuma venda neste caixa.</td>
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
                            Cliente: {{ $this->consultaVendaDetalhe['cliente'] ?? '—' }} |
                            Total: R$ {{ $this->consultaVendaDetalhe['total'] ?? '0,00' }}
                        </p>

                        <div class="erp-pdv-consulta-venda__section">
                            <strong>Itens</strong>
                            <ul class="erp-pdv-consulta-venda__items">
                                @foreach ($this->consultaVendaDetalhe['itens'] ?? [] as $item)
                                    <li>
                                        {{ $item['descricao'] ?? '—' }}
                                        ({{ number_format((float) ($item['quantidade'] ?? 0), 3, ',', '.') }})
                                        — R$ {{ $item['total'] ?? '0,00' }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if (! empty($this->consultaVendaDetalhe['pagamentos']))
                            <div class="erp-pdv-consulta-venda__section">
                                <strong>Pagamentos</strong>
                                <ul class="erp-pdv-consulta-venda__items">
                                    @foreach ($this->consultaVendaDetalhe['pagamentos'] as $pag)
                                        <li>{{ $pag['forma'] ?? '—' }} — R$ {{ $pag['valor'] ?? '0,00' }}</li>
                                    @endforeach
                                </ul>
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
                <button type="button" wire:click="cancelConsultaVenda" class="erp-pdv-modal__btn">Fechar</button>
            </footer>
        </div>
    </div>
@endif
