@if ($this->osNfceEmitirModalOpen)
    @php
        $conferencia = is_array($this->osNfceEmitirConferencia) ? $this->osNfceEmitirConferencia : [];
        $itens = is_array($conferencia['itens'] ?? null) ? $conferencia['itens'] : [];
        $subtotal = (float) ($conferencia['subtotal'] ?? 0);
        $descontoPecas = (float) ($conferencia['desconto_pecas'] ?? 0);
        $descontoNfce = (float) ($conferencia['desconto'] ?? 0);
        $acrescimoNfce = (float) ($conferencia['acrescimo'] ?? 0);
        $totalNfce = (float) ($conferencia['total'] ?? 0);
    @endphp
    <div
        class="erp-lookup-modal erp-os-nfce-emitir-modal"
        wire:keydown.escape.window="cancelarOsNfceEmitirModal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-os-nfce-emitir-cpf')?.focus())"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="cancelarOsNfceEmitirModal"></div>

        <div
            class="erp-lookup-modal__window erp-os-nfce-emitir-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-os-nfce-emitir-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-os-nfce-emitir-title">NFC-e Peças — Conferência</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="cancelarOsNfceEmitirModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-os-nfce-emitir-modal__body">
                <section class="erp-os-nfce-emitir-modal__conferencia" aria-label="Peças da NFC-e">
                    <div class="erp-os-nfce-emitir-modal__conferencia-head">
                        <h3 class="erp-os-nfce-emitir-modal__section-title">Peças (somente leitura)</h3>
                        <p class="erp-os-nfce-emitir-modal__conferencia-hint">Valores iguais aos que serão enviados à SEFAZ.</p>
                    </div>

                    <div class="erp-os-nfce-emitir-modal__grid-wrap">
                        <table class="erp-os-nfce-emitir-modal__grid">
                            <thead>
                                <tr>
                                    <th scope="col">Descrição</th>
                                    <th scope="col" class="is-num">Qtd</th>
                                    <th scope="col" class="is-num">Unitário</th>
                                    <th scope="col" class="is-num">Desconto</th>
                                    <th scope="col" class="is-num">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($itens as $item)
                                    <tr>
                                        <td class="is-desc" title="{{ $item['descricao'] ?? '' }}">{{ $item['descricao'] ?? '—' }}</td>
                                        <td class="is-num">{{ number_format((float) ($item['quantidade'] ?? 0), 3, ',', '.') }}</td>
                                        <td class="is-num">{{ number_format((float) ($item['valor_unitario'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="is-num">{{ number_format((float) ($item['desconto'] ?? 0), 2, ',', '.') }}</td>
                                        <td class="is-num">{{ number_format((float) ($item['total'] ?? 0), 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="is-empty">Nenhuma peça para emitir.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <dl class="erp-os-nfce-emitir-modal__totais">
                        <div class="erp-os-nfce-emitir-modal__totais-row">
                            <dt>Subtotal</dt>
                            <dd>R$ {{ number_format($subtotal, 2, ',', '.') }}</dd>
                        </div>
                        <div class="erp-os-nfce-emitir-modal__totais-row">
                            <dt title="Já refletidos no total de cada peça">Descontos das peças</dt>
                            <dd>R$ {{ number_format($descontoPecas, 2, ',', '.') }}</dd>
                        </div>
                        @if ($descontoNfce > 0.0001)
                            <div class="erp-os-nfce-emitir-modal__totais-row">
                                <dt>Desconto NFC-e</dt>
                                <dd>R$ {{ number_format($descontoNfce, 2, ',', '.') }}</dd>
                            </div>
                        @endif
                        @if ($acrescimoNfce > 0.0001)
                            <div class="erp-os-nfce-emitir-modal__totais-row">
                                <dt>Acréscimo</dt>
                                <dd>R$ {{ number_format($acrescimoNfce, 2, ',', '.') }}</dd>
                            </div>
                        @endif
                        <div class="erp-os-nfce-emitir-modal__totais-row is-total">
                            <dt>Total NFC-e</dt>
                            <dd>R$ {{ number_format($totalNfce, 2, ',', '.') }}</dd>
                        </div>
                    </dl>
                </section>

                <p class="erp-os-nfce-emitir-modal__hint">
                    Informe CPF e nome se quiser identificar o consumidor. Deixe em branco para consumidor não identificado.
                    NFC-e não aceita CNPJ — para pessoa jurídica use F10 | NF-e Peças.
                </p>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-cpf">CPF (opcional)</label>
                    <input
                        id="erp-os-nfce-emitir-cpf"
                        type="text"
                        class="erp-os-nfce-emitir-modal__input"
                        wire:model="osNfceEmitirCpf"
                        inputmode="numeric"
                        autocomplete="off"
                        maxlength="14"
                        placeholder="000.000.000-00"
                        data-mask="cpf"
                    >
                </div>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-nome">Nome (opcional)</label>
                    <input
                        id="erp-os-nfce-emitir-nome"
                        type="text"
                        class="erp-os-nfce-emitir-modal__input"
                        wire:model="osNfceEmitirNome"
                        autocomplete="off"
                        maxlength="60"
                        placeholder="CONSUMIDOR ou nome completo"
                    >
                </div>

                <div class="erp-os-nfce-emitir-modal__field">
                    <label class="erp-os-nfce-emitir-modal__label" for="erp-os-nfce-emitir-obs">Observações da NFC-e (opcional)</label>
                    <textarea
                        id="erp-os-nfce-emitir-obs"
                        class="erp-os-nfce-emitir-modal__textarea"
                        wire:model="osNfceEmitirObservacoes"
                        rows="3"
                        maxlength="500"
                        placeholder="Informações adicionais (infCpl)"
                    ></textarea>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-os-nfce-emitir-modal__actions">
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                    data-erp-key="F5"
                    wire:click="confirmarOsNfceEmitirModal"
                    wire:loading.attr="disabled"
                    wire:target="confirmarOsNfceEmitirModal"
                >
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="confirmarOsNfceEmitirModal">Transmitir</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="confirmarOsNfceEmitirModal">Transmitindo…</span>
                </button>
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                    data-erp-key="Escape"
                    wire:click="cancelarOsNfceEmitirModal"
                    wire:loading.attr="disabled"
                    wire:target="confirmarOsNfceEmitirModal"
                >
                    <span class="erp-pcad-actions__label">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
@endif
