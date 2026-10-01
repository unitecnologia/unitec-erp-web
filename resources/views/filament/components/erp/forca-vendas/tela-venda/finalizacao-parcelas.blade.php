@if ($this->fvTabelaPrazoEmConsulta)
    <div class="erp-pdv-parcelas-overlay" role="dialog" aria-labelledby="erp-fv-parcelas-title">
        <div class="erp-pdv-parcelas">
            <header class="erp-pdv-parcelas__header">
                <h3 id="erp-fv-parcelas-title">Contas Receber | Parcelas</h3>
                <button type="button" class="erp-pdv-modal__close" wire:click="cancelarFvTabelaPrazoConsulta" title="Fechar">✕</button>
            </header>

            <div class="erp-pdv-parcelas__toolbar">
                <div class="erp-pdv-parcelas__avulso">
                    <span class="erp-pdv-parcelas__avulso-title">Avulso</span>
                    <div class="erp-pdv-parcelas__avulso-fields">
                        <label class="erp-pdv-parcelas__field">
                            <span>Total</span>
                            <input type="text" value="{{ \App\Support\Erp\ErpMoney::formatBr($this->fvCrediarioTotalValor) }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-pdv-parcelas__field">
                            <span>Parcelas</span>
                            <input
                                id="erp-fv-parcelas-qtd"
                                type="text"
                                wire:model="fvParcelasQtd"
                                data-mask="integer"
                                autocomplete="off"
                                @if ($this->fvPrazoCarneTravado()) readonly tabindex="-1" @endif
                            >
                        </label>
                        <label class="erp-pdv-parcelas__field">
                            <span>Intervalo</span>
                            <input
                                id="erp-fv-parcelas-intervalo"
                                type="text"
                                wire:model="fvParcelasIntervalo"
                                data-mask="integer"
                                autocomplete="off"
                                @if ($this->fvPrazoCarneTravado()) readonly tabindex="-1" @endif
                            >
                        </label>
                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarFvParcelasCrediario" @if ($this->fvPrazoCarneTravado()) disabled @endif>
                            <kbd>F2</kbd> Gerar
                        </button>
                    </div>
                </div>

                <div class="erp-pdv-parcelas__toolbar-actions">
                    @if ($this->fvPodeEscolherTabelaPrazo())
                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--info" wire:click="abrirFvTabelasPrazoPredefinidas">
                            <kbd>F8</kbd> Tabelas
                        </button>
                    @endif
                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger" wire:click="excluirFvParcelaCrediario" @if ($this->fvPrazoCarneTravado()) disabled @endif>
                        <kbd>F3</kbd> Excluir
                    </button>
                    <button type="button" class="erp-pdv-modal__btn" wire:click="cancelarFvTabelaPrazoConsulta">
                        <kbd>F4</kbd> Cancelar
                    </button>
                </div>
            </div>

            @if ($this->fvPodeEscolherTabelaPrazo() && $this->fvTabelasPrazoListaAberta)
                <div class="erp-pdv-parcelas__tabelas" id="erp-fv-parcelas-tabelas">
                    <div class="erp-pdv-parcelas__tabelas-head">
                        <strong>Prazos pré-definidos</strong>
                        <button type="button" class="erp-pdv-parcelas__tabelas-close" wire:click="fecharFvTabelasPrazoPredefinidas" title="Fechar">✕</button>
                    </div>
                    <div class="erp-pdv-parcelas__tabelas-list">
                        <table class="erp-pdv__grid erp-pdv-parcelas__tabelas-grid">
                            <thead>
                                <tr>
                                    <th>Dias</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->fvTabelasPrazoPredefinidas as $index => $tabela)
                                    <tr
                                        wire:click="selectFvTabelaPredefinida({{ $index }})"
                                        wire:dblclick="aplicarFvTabelaPrazoPredefinida"
                                        wire:key="fv-tabela-predef-{{ $index }}-{{ $tabela['tabela_prazo_id'] }}"
                                        id="erp-fv-tabela-predef-row-{{ $index }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv__grid-row--selected' => $this->fvSelectedTabelaPredefinidaIndex === $index,
                                        ])
                                    >
                                        <td>{{ $tabela['label'] ?? $tabela['dias'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="erp-pdv-parcelas__tabelas-actions">
                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="aplicarFvTabelaPrazoPredefinida">
                            Enter | Usar tabela
                        </button>
                        <button type="button" class="erp-pdv-modal__btn" wire:click="fecharFvTabelasPrazoPredefinidas">
                            ESC | Voltar
                        </button>
                    </div>
                </div>
            @endif

            <div class="erp-pdv-parcelas__grid-wrap" id="erp-fv-finalizar-tabela-prazo">
                <table class="erp-pdv__grid erp-pdv-parcelas__grid @if ($this->fvParcelasEhCheque) erp-pdv-parcelas__grid--cheque @endif">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Vencimento</th>
                            <th class="erp-pdv__grid-col-num">Valor</th>
                            @if ($this->fvParcelasEhCheque)
                                <th>Nº Cheque</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->fvParcelasRows as $index => $row)
                            <tr
                                wire:click="selectFvParcelaRow({{ $index }})"
                                wire:key="fv-parcela-{{ $index }}"
                                id="erp-fv-finalizar-prazo-row-{{ $index }}"
                                @class([
                                    'erp-pdv__grid-row',
                                    'erp-pdv__grid-row--selected' => $this->fvSelectedParcelaIndex === $index,
                                ])
                            >
                                <td>{{ $row['documento'] ?? '—' }}</td>
                                <td>{{ $row['vencimento'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-num">R$ {{ $row['valor'] ?? '0,00' }}</td>
                                @if ($this->fvParcelasEhCheque)
                                    <td>
                                        <input
                                            type="text"
                                            class="erp-pdv-parcelas__cheque-input"
                                            id="erp-fv-parcela-cheque-{{ $index }}"
                                            wire:model.live="fvParcelasRows.{{ $index }}.numero_cheque"
                                            wire:click.stop
                                            autocomplete="off"
                                            maxlength="40"
                                            placeholder="Nº"
                                        >
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr class="erp-pdv__grid-empty">
                                <td colspan="{{ $this->fvParcelasEhCheque ? 4 : 3 }}">Avulso: Parcelas/Intervalo + F2 | Gerar — ou F8 | Tabelas pré-definidas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="erp-pdv-parcelas__footer">
                <div class="erp-pdv-parcelas__total">
                    <span>Total Parcelas</span>
                    <strong>{{ $this->fvParcelasTotalLabel !== '' ? 'R$ '.$this->fvParcelasTotalLabel : '' }}</strong>
                </div>
                <div class="erp-pdv-parcelas__footer-actions">
                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirFvParcelasCrediario">
                        <kbd>F7</kbd> Concluir
                    </button>
                </div>
            </footer>
        </div>
    </div>
@elseif (filled($this->fvTabelaPrazoLabel))
    <div class="erp-pdv-finalizar__prazo-resumo">
        <span>Parcelas:</span>
        <strong>{{ $this->fvTabelaPrazoLabel }}</strong>
    </div>
@endif
