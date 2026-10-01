@if ($this->fechamentoMoedasModalOpen ?? false)
    @php
        $faces = $this->fechamentoMoedasFaces();
        $totalMoedas = $this->fechamentoMoedasTotal;
    @endphp
    <div class="erp-pdv-modal erp-pdv-moedas-modal" role="dialog" aria-labelledby="erp-pdv-moedas-title" aria-modal="true">
        <div class="erp-pdv-modal__backdrop" wire:click="fecharContarMoedas"></div>
        <div class="erp-pdv-modal__window erp-pdv-moedas-modal__window">
            <header class="erp-pdv-moedas-modal__header">
                <div>
                    <p class="erp-pdv-moedas-modal__eyebrow">Fechamento</p>
                    <h2 id="erp-pdv-moedas-title">Contar moedas</h2>
                </div>
                <button type="button" class="erp-pdv-caixa-modal__x" wire:click="fecharContarMoedas" aria-label="Fechar">×</button>
            </header>

            <div class="erp-pdv-moedas-modal__body">
                <p class="erp-pdv-moedas-modal__hint">Informe só a quantidade. O total é calculado automaticamente.</p>
                <table class="erp-pdv-moedas-modal__table">
                    <thead>
                        <tr>
                            <th>Moeda</th>
                            <th>Quantidade</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($faces as $face)
                            @php
                                $key = $this->fechamentoMoedasKey($face);
                                $raw = preg_replace('/\D+/', '', (string) ($this->fechamentoMoedasQtd[$key] ?? '')) ?? '';
                                $qtd = $raw === '' ? 0 : max(0, (int) $raw);
                                $linhaTotal = round($face * $qtd, 2);
                            @endphp
                            <tr wire:key="pdv-moedas-face-{{ $key }}">
                                <td>R$ {{ \Unitec\PdvUi\Support\PdvMoney::formatBr($face) }}</td>
                                <td>
                                    <input
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        autocomplete="off"
                                        wire:model.live="fechamentoMoedasQtd.{{ $key }}"
                                        class="erp-pdv-moedas-modal__input"
                                        @if ($loop->first) id="erp-pdv-moedas-primeiro" @endif
                                    >
                                </td>
                                <td class="is-total">R$ {{ \Unitec\PdvUi\Support\PdvMoney::formatBr($linhaTotal) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong>Total em moedas</strong></td>
                            <td class="is-total"><strong>R$ {{ \Unitec\PdvUi\Support\PdvMoney::formatBr($totalMoedas) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <footer class="erp-pdv-moedas-modal__footer">
                <button type="button" wire:click="fecharContarMoedas" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--ghost">
                    Cancelar
                </button>
                <button type="button" wire:click="confirmarContarMoedas" class="erp-pdv-caixa-modal__btn erp-pdv-caixa-modal__btn--primary">
                    Confirmar
                </button>
            </footer>
        </div>
    </div>
@endif
