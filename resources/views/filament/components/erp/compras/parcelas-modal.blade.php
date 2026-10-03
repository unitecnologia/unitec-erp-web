@if ($this->lancamentoParcelasOpen)
    @php
        $formasCaixaIds = $this->lancamentoParcelasFormasCaixaIds();
        $caixasPadrao = $this->lancamentoParcelasCaixaPadraoMapa();
    @endphp
    <div
        class="erp-compras-parcelas-modal"
        x-data
        x-init="const el = $el; const ligar = () => window.erpParcelasBind && window.erpParcelasBind(el); ligar(); queueMicrotask(ligar)"
        x-on:erp-compras-lancamento-fechado.window="$el.remove()"
        data-erp-formas-caixa='@json($formasCaixaIds)'
        data-erp-caixa-padrao='@json($caixasPadrao)'
    >
        <div class="erp-compras-parcelas-modal__backdrop" wire:click="cancelarLancamentoParcelas"></div>

        <div
            class="erp-compras-parcelas-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-compras-parcelas-title"
        >
            <div class="erp-compras-parcelas-modal__titlebar">
                <span id="erp-compras-parcelas-title">Contas à Pagar — Parcelas</span>
                <button
                    type="button"
                    class="erp-compras-parcelas-modal__close"
                    wire:click="cancelarLancamentoParcelas"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-compras-parcelas-modal__body">
                <div class="erp-compras-parcelas-modal__params">
                    <label class="erp-compras-parcelas-modal__field">
                        <span>SubTotal</span>
                        <input id="erp-parcela-subtotal" type="text" value="{{ $this->lancamentoParcelasSubtotal }}" data-erp-parcela-base="subtotal" inputmode="decimal" autocomplete="off">
                    </label>
                    <label class="erp-compras-parcelas-modal__field">
                        <span>Entrada (Dinheiro)</span>
                        <input id="erp-parcela-entrada" type="text" value="{{ $this->lancamentoParcelasEntrada }}" data-erp-parcela-base="entrada" inputmode="decimal" autocomplete="off">
                    </label>
                    <label class="erp-compras-parcelas-modal__field">
                        <span>Total</span>
                        <input id="erp-parcela-total" type="text" value="{{ $this->lancamentoParcelasTotal }}" readonly tabindex="-1">
                    </label>
                    <label class="erp-compras-parcelas-modal__field erp-compras-parcelas-modal__field--sm">
                        <span>Parcelas</span>
                        <input id="erp-parcela-qtd" type="text" value="{{ $this->lancamentoParcelasQtd }}" inputmode="numeric" autocomplete="off">
                    </label>
                    <label class="erp-compras-parcelas-modal__field erp-compras-parcelas-modal__field--sm">
                        <span>Intervalo</span>
                        <input id="erp-parcela-intervalo" type="text" value="{{ $this->lancamentoParcelasIntervalo }}" inputmode="numeric" autocomplete="off">
                    </label>
                    <button
                        type="button"
                        class="erp-compras-parcelas-modal__btn erp-compras-parcelas-modal__btn--gerar"
                        x-on:click.prevent="window.erpParcelasAcao($wire, 'gerar')"
                        title="Gerar parcelas (F2)"
                    >
                        <span class="erp-compras-parcelas-modal__btn-icon" aria-hidden="true">＋</span>
                        F2 | Gerar
                    </button>
                </div>

                <div class="erp-compras-parcelas-modal__grid-wrap">
                    <table class="erp-compras-parcelas-modal__grid">
                        <thead>
                            <tr>
                                <th>Documento</th>
                                <th>Vencimento</th>
                                <th>Meio de Pagamento</th>
                                <th>Caixa</th>
                                <th class="is-num">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->lancamentoParcelasRows as $index => $row)
                                @php
                                    $formaId = (int) ($row['forma_pagamento_id'] ?? 0);
                                    $exigeCaixa = in_array($formaId, $formasCaixaIds, true);
                                    $caixaId = (string) ($row['caixa_conta_id'] ?? '');
                                    if ($exigeCaixa && $caixaId === '' && isset($caixasPadrao[$formaId])) {
                                        $caixaId = (string) $caixasPadrao[$formaId];
                                    }
                                    $linhaInvalida = $this->lancamentoParcelasErroIndex === $index;
                                    $campoInvalido = $linhaInvalida ? $this->lancamentoParcelasErroCampo : '';
                                @endphp
                                <tr
                                    wire:key="lanc-parcela-{{ $index }}"
                                    data-erp-parcela-index="{{ $index }}"
                                    class="{{ $this->lancamentoParcelasSelectedIndex === $index ? 'is-selected' : '' }}{{ $linhaInvalida ? ' is-invalid' : '' }}"
                                >
                                    <td>
                                        <input
                                            type="text"
                                            value="{{ $row['documento'] ?? '' }}"
                                            autocomplete="off"
                                            data-erp-parcela-field="documento"
                                            data-erp-parcela-index="{{ $index }}"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            value="{{ $row['vencimento'] ?? '' }}"
                                            autocomplete="off"
                                            data-erp-parcela-field="vencimento"
                                            data-erp-parcela-index="{{ $index }}"
                                            placeholder="dd/mm/aaaa"
                                            @class(['is-invalid' => $campoInvalido === 'vencimento'])
                                        >
                                    </td>
                                    <td>
                                        <select
                                            autocomplete="off"
                                            data-erp-parcela-field="forma"
                                            data-erp-parcela-index="{{ $index }}"
                                            @class(['is-invalid' => $campoInvalido === 'forma'])
                                        >
                                            <option value="">Selecione…</option>
                                            @foreach ($this->lancamentoParcelasFormasOptions as $forma)
                                                <option value="{{ $forma['id'] }}" data-erp-tipo="{{ $forma['tipo'] ?? '' }}" @selected((string) $formaId === (string) $forma['id'])>{{ $forma['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <select
                                            data-erp-parcela-field="caixa"
                                            data-erp-parcela-index="{{ $index }}"
                                            @class(['is-invalid' => $campoInvalido === 'caixa'])
                                            @disabled(! $exigeCaixa)
                                            @if (! $exigeCaixa) hidden @endif
                                        >
                                            <option value="">Subcaixa…</option>
                                            @foreach ($this->lancamentoParcelasSubcaixasOptions as $caixa)
                                                <option value="{{ $caixa['id'] }}" @selected($caixaId === (string) $caixa['id'])>{{ $caixa['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <span class="erp-compras-parcelas-modal__caixa-na" data-erp-parcela-caixa-vazio @if ($exigeCaixa) hidden @endif>—</span>
                                    </td>
                                    <td class="is-num">
                                        <input
                                            type="text"
                                            @class(['is-num', 'is-invalid' => $campoInvalido === 'valor'])
                                            value="{{ $row['valor'] ?? '' }}"
                                            autocomplete="off"
                                            data-erp-parcela-field="valor"
                                            data-erp-parcela-index="{{ $index }}"
                                            inputmode="decimal"
                                            title="Ao alterar, o saldo é recalculado automaticamente na outra parcela"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="erp-compras-parcelas-modal__empty">
                                        Nenhuma parcela. Ajuste os campos e clique em Gerar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="erp-compras-parcelas-modal__footer">
                <div class="erp-compras-parcelas-modal__actions">
                    <button
                        type="button"
                        class="erp-compras-parcelas-modal__action"
                        x-on:click.prevent="window.erpParcelasAcao($wire, 'excluir')"
                        title="Excluir parcela selecionada (F3)"
                    >
                        <span aria-hidden="true">🗑</span>
                        F3 | Excluir
                    </button>
                    <button
                        type="button"
                        class="erp-compras-parcelas-modal__action erp-compras-parcelas-modal__action--cancel"
                        wire:click="cancelarLancamentoParcelas"
                        title="Cancelar (F4)"
                    >
                        <span aria-hidden="true">✕</span>
                        F4 | Cancelar
                    </button>
                    <button
                        type="button"
                        class="erp-compras-parcelas-modal__action erp-compras-parcelas-modal__action--ok"
                        x-on:click.prevent="window.erpParcelasAcao($wire, 'concluir')"
                        wire:loading.attr="disabled"
                        wire:target="concluirLancamentoParcelas"
                        @disabled($this->lancamentoFinalizando)
                        title="Concluir e finalizar compra (F5)"
                    >
                        <span aria-hidden="true">✓</span>
                        F5 | Concluir
                    </button>
                </div>
                <div class="erp-compras-parcelas-modal__total">
                    Total Parcelas:
                    <strong>R$ <span id="erp-parcela-total-parcelas">{{ $this->lancamentoParcelasTotalParcelas() }}</span></strong>
                </div>
            </div>
        </div>
    </div>
@endif
