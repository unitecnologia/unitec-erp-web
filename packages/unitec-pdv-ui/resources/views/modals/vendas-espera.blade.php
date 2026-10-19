@if ($this->activeModal === 'vendas_espera')
    <x-pdvui::modal-shell
        title="Vendas em espera"
        title-id="erp-pdv-vendas-espera-title"
        eyebrow="Cupom"
        subtitle="Recupere ou descarte vendas pausadas neste caixa."
        aria-label="Vendas em espera"
        close-action="cancelVendaEmEspera"
        window-class="erp-pdv-modal__window--wide erp-pdv-vendas-espera__window"
        wire:keydown.escape="cancelVendaEmEspera"
    >
        <x-slot:icon>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                <path d="M9 6v12M15 6v12"/>
            </svg>
        </x-slot:icon>

        <div
            class="erp-pdv-vendas-espera"
            @if ($this->selectedVendaEsperaIndex !== null) data-selected-index="{{ $this->selectedVendaEsperaIndex }}" @endif
        >
            <label class="erp-pdv-modal__label" for="erp-pdv-vendas-espera-search">Número ou identificador</label>
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
                            <th>Identificador</th>
                            <th>Número</th>
                            <th>Hora</th>
                            <th class="erp-pdv__grid-col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->vendaEsperaResults as $index => $row)
                            <tr
                                wire:key="pdv-venda-espera-{{ $row['id'] }}"
                                id="erp-pdv-vendas-espera-row-{{ $index }}"
                                onclick="window.erpPdvSelectVendaEsperaRow({{ $index }})"
                                ondblclick="window.erpPdvRecuperarVendaEsperaSelecionada()"
                                @class([
                                    'erp-pdv__grid-row',
                                    'erp-pdv__grid-row--marked' => $this->selectedVendaEsperaIndex === $index,
                                ])
                            >
                                <td class="erp-pdv-vendas-espera__cliente" title="{{ $row['cliente'] }}">{{ $row['cliente'] }}</td>
                                <td class="erp-pdv-vendas-espera__numero">{{ $row['numero'] }}</td>
                                <td class="erp-pdv-vendas-espera__hora" title="{{ $row['data'] }} {{ $row['hora'] }}">{{ $row['hora'] }}</td>
                                <td class="erp-pdv__grid-col-num">R$ {{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr class="erp-pdv__grid-empty">
                                <td colspan="4">Nenhuma venda em espera neste caixa.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->vendaEsperaResults !== [])
                @php
                    $motivoLength = mb_strlen(trim($this->vendaEsperaMotivoDescarte), 'UTF-8');
                    $minMotivo = 10;
                    $maxMotivo = 200;
                @endphp
                <div class="erp-pdv-vendas-espera__motivo">
                    <label class="erp-pdv-modal__label" for="erp-pdv-vendas-espera-motivo">Motivo do descarte</label>
                    <input
                        id="erp-pdv-vendas-espera-motivo"
                        type="text"
                        wire:model.live.debounce.150ms="vendaEsperaMotivoDescarte"
                        class="erp-pdv-modal__input"
                        data-erp-uppercase
                        autocomplete="off"
                        maxlength="{{ $maxMotivo }}"
                        placeholder="Informe o motivo (mínimo 10 caracteres)"
                    >
                    <p @class([
                        'erp-pdv-vendas-espera__counter',
                        'erp-pdv-vendas-espera__counter--ok' => $motivoLength >= $minMotivo,
                        'erp-pdv-vendas-espera__counter--warn' => $motivoLength < $minMotivo,
                    ])>
                        {{ $motivoLength }}/{{ $maxMotivo }}
                        @if ($motivoLength < $minMotivo)
                            — faltam {{ $minMotivo - $motivoLength }} caracteres
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <x-slot:footer>
            <button type="button" wire:click="cancelVendaEmEspera" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                Fechar
            </button>
            <button
                type="button"
                onclick="window.erpPdvDescartarVendaEsperaSelecionada()"
                wire:loading.attr="disabled"
                wire:target="confirmarExcluirVendaEmEsperaAt,confirmarExcluirVendaEmEspera"
                class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--danger"
                @disabled($this->vendaEsperaResults === [] || mb_strlen(trim($this->vendaEsperaMotivoDescarte)) < 10)
            >
                <span wire:loading.remove wire:target="confirmarExcluirVendaEmEsperaAt,confirmarExcluirVendaEmEspera">Descartar</span>
                <span wire:loading wire:target="confirmarExcluirVendaEmEsperaAt,confirmarExcluirVendaEmEspera">Descartando…</span>
            </button>
            <button
                type="button"
                onclick="window.erpPdvRecuperarVendaEsperaSelecionada()"
                class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary"
                @disabled($this->vendaEsperaResults === [])
            >
                Recuperar venda
            </button>
        </x-slot:footer>
    </x-pdvui::modal-shell>
@endif
