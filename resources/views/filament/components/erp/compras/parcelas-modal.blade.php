@php
    $parcelasPodeConcluir = $this->lancamentoParcelasPodeConcluir;
@endphp

@if ($this->lancamentoParcelasOpen)
    <div
        class="erp-compras-parcelas-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.cancelarLancamentoParcelas(); }
            if (($event.key === 'F2' || $event.key === 'F5' || $event.key === 'F3' || $event.key === 'F4') && ! $event.repeat) {
                $event.preventDefault();
                if ($event.key === 'F2') $wire.gerarLancamentoParcelas();
                if ($event.key === 'F3') $wire.excluirLancamentoParcelaSelecionada();
                if ($event.key === 'F4') $wire.cancelarLancamentoParcelas();
                if ($event.key === 'F5') $wire.concluirLancamentoParcelas();
            }
        "
        x-on:keydown.enter.capture="
            const field = $event.target;
            if (! field.matches('[data-erp-parcela-field]')) return;
            $event.preventDefault();
            field.removeAttribute('readonly');
            if (field.dataset.erpParcelaField === 'valor') {
                $wire.set('lancamentoParcelasRows.' + field.dataset.erpParcelaIndex + '.valor', field.value);
            }
            const fields = Array.from($el.querySelectorAll('[data-erp-parcela-field]:not(:disabled)'));
            const index = fields.indexOf(field);
            const next = fields[index + 1];
            if (next) {
                setTimeout(() => {
                    next.removeAttribute('readonly');
                    next.focus();
                    if (next.tagName === 'INPUT') next.select();
                }, 0);
            }
        "
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
                        <input type="text" wire:model.blur="lancamentoParcelasSubtotal" inputmode="decimal">
                    </label>
                    <label class="erp-compras-parcelas-modal__field">
                        <span>Entrada (Dinheiro)</span>
                        <input type="text" wire:model.blur="lancamentoParcelasEntrada" inputmode="decimal">
                    </label>
                    <label class="erp-compras-parcelas-modal__field">
                        <span>Total</span>
                        <input type="text" value="{{ $this->lancamentoParcelasTotal }}" readonly tabindex="-1">
                    </label>
                    <label class="erp-compras-parcelas-modal__field erp-compras-parcelas-modal__field--sm">
                        <span>Parcelas</span>
                        <input type="text" wire:model="lancamentoParcelasQtd" inputmode="numeric">
                    </label>
                    <label class="erp-compras-parcelas-modal__field erp-compras-parcelas-modal__field--sm">
                        <span>Intervalo</span>
                        <input type="text" wire:model="lancamentoParcelasIntervalo" inputmode="numeric">
                    </label>
                    <button
                        type="button"
                        class="erp-compras-parcelas-modal__btn erp-compras-parcelas-modal__btn--gerar"
                        wire:click="gerarLancamentoParcelas"
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
                                    $exigeCaixa = $this->lancamentoParcelaExigeSubcaixa($formaId);
                                @endphp
                                <tr
                                    wire:key="lanc-parcela-{{ $index }}"
                                    class="{{ $this->lancamentoParcelasSelectedIndex === $index ? 'is-selected' : '' }}"
                                    wire:click="selectLancamentoParcela({{ $index }})"
                                >
                                    <td>
                                        <input
                                            type="text"
                                            wire:model.live="lancamentoParcelasRows.{{ $index }}.documento"
                                            data-erp-parcela-field="documento"
                                            data-erp-parcela-index="{{ $index }}"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            wire:model.live="lancamentoParcelasRows.{{ $index }}.vencimento"
                                            data-erp-parcela-field="vencimento"
                                            data-erp-parcela-index="{{ $index }}"
                                            placeholder="dd/mm/aaaa"
                                        >
                                    </td>
                                    <td>
                                        <select
                                            wire:model.live="lancamentoParcelasRows.{{ $index }}.forma_pagamento_id"
                                            data-erp-parcela-field="forma"
                                            data-erp-parcela-index="{{ $index }}"
                                        >
                                            <option value="">Selecione…</option>
                                            @foreach ($this->lancamentoParcelasFormasOptions as $forma)
                                                <option value="{{ $forma['id'] }}">{{ $forma['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        @if ($exigeCaixa)
                                            <select
                                                wire:model.live="lancamentoParcelasRows.{{ $index }}.caixa_conta_id"
                                                data-erp-parcela-field="caixa"
                                                data-erp-parcela-index="{{ $index }}"
                                            >
                                                <option value="">Subcaixa…</option>
                                                @foreach ($this->lancamentoParcelasSubcaixasOptions as $caixa)
                                                    <option value="{{ $caixa['id'] }}">{{ $caixa['label'] }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <span class="erp-compras-parcelas-modal__caixa-na">—</span>
                                        @endif
                                    </td>
                                    <td class="is-num">
                                        <input
                                            type="text"
                                            class="is-num"
                                            wire:model.blur="lancamentoParcelasRows.{{ $index }}.valor"
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
                        wire:click="excluirLancamentoParcelaSelecionada"
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
                        wire:click="concluirLancamentoParcelas"
                        @disabled(! $parcelasPodeConcluir)
                        title="{{ $parcelasPodeConcluir ? 'Concluir e finalizar compra (F5)' : 'Informe meio de pagamento e caixa em todas as parcelas' }}"
                    >
                        <span aria-hidden="true">✓</span>
                        F5 | Concluir
                    </button>
                </div>
                <div class="erp-compras-parcelas-modal__total">
                    Total Parcelas:
                    <strong>R$ {{ $this->lancamentoParcelasTotal }}</strong>
                </div>
            </div>
        </div>
    </div>
@endif
