@if ($this->activeModal === 'vendas_espera')
    <div class="erp-pdv-modal" role="dialog" aria-label="Vendas em espera" wire:keydown.escape="cancelVendaEmEspera">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelVendaEmEspera"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--wide erp-pdv-vendas-espera__window">
            <header class="erp-pdv-modal__header">
                <h2>Vendas em espera</h2>
            </header>
            <div class="erp-pdv-modal__body erp-pdv-vendas-espera">
                <label class="erp-pdv-modal__label" for="erp-pdv-vendas-espera-search">Número, cliente ou operador</label>
                <input
                    id="erp-pdv-vendas-espera-search"
                    type="text"
                    wire:model.live.debounce.150ms="vendaEsperaSearch"
                    class="erp-pdv-modal__input"
                    data-erp-uppercase
                    autocomplete="off"
                >

                <div class="erp-pdv-modal__grid-scroll erp-pdv-vendas-espera__grid-scroll">
                    <table class="erp-pdv__grid erp-pdv-modal__grid erp-pdv-vendas-espera__grid">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Operador</th>
                                <th class="erp-pdv__grid-col-num">Itens</th>
                                <th class="erp-pdv__grid-col-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->vendaEsperaResults as $index => $row)
                                <tr
                                    wire:key="pdv-venda-espera-{{ $row['id'] }}"
                                    wire:click="selectVendaEsperaRow({{ $index }})"
                                    id="erp-pdv-vendas-espera-row-{{ $index }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv__grid-row--marked' => $this->selectedVendaEsperaIndex === $index,
                                    ])
                                >
                                    <td class="erp-pdv-vendas-espera__numero">#{{ $row['numero'] }}</td>
                                    <td class="erp-pdv-vendas-espera__data">{{ $row['data'] }}</td>
                                    <td class="erp-pdv-vendas-espera__hora">{{ $row['hora'] }}</td>
                                    <td class="erp-pdv-vendas-espera__cliente" title="{{ $row['cliente'] }}">{{ $row['cliente'] }}</td>
                                    <td>{{ $row['operador'] }}</td>
                                    <td class="erp-pdv__grid-col-num">{{ $row['itens'] }}</td>
                                    <td class="erp-pdv__grid-col-num">R$ {{ $row['total'] }}</td>
                                </tr>
                            @empty
                                <tr class="erp-pdv__grid-empty">
                                    <td colspan="7">Nenhuma venda em espera neste caixa.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($this->vendaEsperaExcluirId)
                    <div class="erp-pdv-modal__confirm">
                        <p>Descartar esta venda em espera? Esta ação não pode ser desfeita.</p>
                        <div>
                            <button type="button" wire:click="confirmarExcluirVendaEmEspera" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger">Descartar</button>
                            <button type="button" wire:click="$set('vendaEsperaExcluirId', null)" class="erp-pdv-modal__btn">Cancelar</button>
                        </div>
                    </div>
                @endif
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="recuperarVendaEmEspera" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" @disabled($this->selectedVendaEsperaIndex === null)>Recuperar venda</button>
                <button type="button" wire:click="requestExcluirVendaEmEspera" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger" @disabled($this->selectedVendaEsperaIndex === null)>Descartar</button>
                <button type="button" wire:click="cancelVendaEmEspera" class="erp-pdv-modal__btn">Fechar</button>
            </footer>
        </div>
    </div>
@endif
