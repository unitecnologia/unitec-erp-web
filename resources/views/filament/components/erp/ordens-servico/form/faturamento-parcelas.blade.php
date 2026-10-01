@if ($this->osTabelaPrazoEmConsulta)
    <div class="erp-pdv-parcelas-overlay" role="dialog" aria-labelledby="erp-os-parcelas-title">
        <div class="erp-pdv-parcelas">
            <header class="erp-pdv-parcelas__header">
                <h3 id="erp-os-parcelas-title">Contas Receber | Parcelas</h3>
                <button type="button" class="erp-pdv-modal__close" wire:click="cancelarOsTabelaPrazoConsulta" title="Fechar">✕</button>
            </header>

            <div class="erp-pdv-parcelas__toolbar">
                <div class="erp-pdv-parcelas__avulso">
                    <span class="erp-pdv-parcelas__avulso-title">Avulso</span>
                    <div class="erp-pdv-parcelas__avulso-fields">
                        <label class="erp-pdv-parcelas__field">
                            <span>Total</span>
                            <input type="text" value="{{ \App\Support\Erp\ErpMoney::formatBr($this->osCrediarioTotalValor) }}" readonly tabindex="-1">
                        </label>
                        <label class="erp-pdv-parcelas__field">
                            <span>Parcelas</span>
                            <input
                                id="erp-os-parcelas-qtd"
                                type="text"
                                wire:model="osParcelasQtd"
                                data-mask="integer"
                                autocomplete="off"
                            >
                        </label>
                        <label class="erp-pdv-parcelas__field">
                            <span>Intervalo</span>
                            <input
                                id="erp-os-parcelas-intervalo"
                                type="text"
                                wire:model="osParcelasIntervalo"
                                data-mask="integer"
                                autocomplete="off"
                            >
                        </label>
                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarOsParcelasCrediario">
                            <kbd>F2</kbd> Gerar
                        </button>
                    </div>
                </div>

                <div class="erp-pdv-parcelas__toolbar-actions">
                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--info" wire:click="abrirOsTabelasPrazoPredefinidas">
                        <kbd>F8</kbd> Tabelas
                    </button>
                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger" wire:click="excluirOsParcelaCrediario">
                        <kbd>F3</kbd> Excluir
                    </button>
                    <button type="button" class="erp-pdv-modal__btn" wire:click="cancelarOsTabelaPrazoConsulta">
                        <kbd>F4</kbd> Cancelar
                    </button>
                </div>
            </div>

            @if ($this->osTabelasPrazoListaAberta)
                <div class="erp-pdv-parcelas__tabelas" id="erp-os-parcelas-tabelas">
                    <div class="erp-pdv-parcelas__tabelas-head">
                        <strong>Prazos pré-definidos</strong>
                        <button type="button" class="erp-pdv-parcelas__tabelas-close" wire:click="fecharOsTabelasPrazoPredefinidas" title="Fechar">✕</button>
                    </div>
                    <div class="erp-pdv-parcelas__tabelas-list">
                        <table class="erp-pdv__grid erp-pdv-parcelas__tabelas-grid">
                            <thead>
                                <tr>
                                    <th>Dias</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->osTabelasPrazoPredefinidas as $index => $tabela)
                                    <tr
                                        wire:click="selectOsTabelaPredefinida({{ $index }})"
                                        wire:dblclick="aplicarOsTabelaPrazoPredefinida"
                                        wire:key="os-tabela-predef-{{ $index }}-{{ $tabela['tabela_prazo_id'] }}"
                                        id="erp-os-tabela-predef-row-{{ $index }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv__grid-row--selected' => $this->osSelectedTabelaPredefinidaIndex === $index,
                                        ])
                                    >
                                        <td>{{ $tabela['label'] ?? $tabela['dias'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="erp-pdv-parcelas__tabelas-actions">
                        <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="aplicarOsTabelaPrazoPredefinida">
                            Enter | Usar tabela
                        </button>
                        <button type="button" class="erp-pdv-modal__btn" wire:click="fecharOsTabelasPrazoPredefinidas">
                            ESC | Voltar
                        </button>
                    </div>
                </div>
            @endif

            <div class="erp-pdv-parcelas__grid-wrap" id="erp-os-finalizar-tabela-prazo">
                <table class="erp-pdv__grid erp-pdv-parcelas__grid @if ($this->osParcelasEhCheque) erp-pdv-parcelas__grid--cheque @endif">
                    <thead>
                        <tr>
                            <th>Documento</th>
                            <th>Vencimento</th>
                            <th class="erp-pdv__grid-col-num">Valor</th>
                            @if ($this->osParcelasEhCheque)
                                <th>Nº Cheque</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->osParcelasRows as $index => $row)
                            <tr
                                wire:click="selectOsParcelaRow({{ $index }})"
                                wire:key="os-parcela-{{ $index }}"
                                id="erp-os-finalizar-prazo-row-{{ $index }}"
                                @class([
                                    'erp-pdv__grid-row',
                                    'erp-pdv__grid-row--selected' => $this->osSelectedParcelaIndex === $index,
                                ])
                            >
                                <td>{{ $row['documento'] ?? '—' }}</td>
                                <td>{{ $row['vencimento'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-num">R$ {{ $row['valor'] ?? '0,00' }}</td>
                                @if ($this->osParcelasEhCheque)
                                    <td>
                                        <input
                                            type="text"
                                            class="erp-pdv-parcelas__cheque-input"
                                            id="erp-os-parcela-cheque-{{ $index }}"
                                            wire:model.live="osParcelasRows.{{ $index }}.numero_cheque"
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
                                <td colspan="{{ $this->osParcelasEhCheque ? 4 : 3 }}">Avulso: Parcelas/Intervalo + F2 | Gerar — ou F8 | Tabelas pré-definidas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="erp-pdv-parcelas__footer">
                <div class="erp-pdv-parcelas__total">
                    <span>Total Parcelas</span>
                    <strong>{{ $this->osParcelasTotalLabel !== '' ? 'R$ '.$this->osParcelasTotalLabel : '' }}</strong>
                </div>
                <div class="erp-pdv-parcelas__footer-actions">
                    <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirOsParcelasCrediario">
                        <kbd>F7</kbd> Concluir
                    </button>
                </div>
            </footer>
        </div>
    </div>
@elseif (filled($this->osTabelaPrazoLabel))
    <div class="erp-pdv-finalizar__prazo-resumo">
        <span>Parcelas:</span>
        <strong>{{ $this->osTabelaPrazoLabel }}</strong>
    </div>
@endif
