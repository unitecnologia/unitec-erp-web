@if ($this->osFaturamentoOpen)
    @php
        $totalLiquido = $this->osTotalLiquido();
        $restante = $this->osValorRestante();
        $troco = $this->osTroco();
        $osNumero = $this->osNumeroDisplay();
        $titulo = $osNumero !== ''
            ? 'Forma de Pagamento — OS '.$osNumero
            : 'Forma de Pagamento';
    @endphp
@teleport('body')

    <div
        class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-modal--payment erp-fv-fin erp-os-fin"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-os-fin-title"
        x-data
        x-on:keydown.window.capture="
            if (! document.querySelector('.erp-os-fin')) return;
            if (document.querySelector('.erp-os-fin .erp-pdv-parcelas-overlay')) return;
            const t = $event.target;
            if (t?.id === 'erp-os-fin-cliente' || t?.closest?.('.erp-fv-fin__ajuste')) return;
            const k = $event.key || '';
            if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
            if (k.length !== 1 || ! /[a-zA-Z]/.test(k)) return;
            const atalhos = Array.from(document.querySelectorAll('.erp-os-fin .erp-pdv-finalizar__kbd'))
                .map((el) => (el.textContent || '').trim().toUpperCase())
                .filter((v) => v.length === 1);
            if (! atalhos.includes(k.toUpperCase())) return;
            $event.preventDefault();
            $event.stopPropagation();
            $wire.selectOsPagamentoByAtalho(k);
        "
        x-on:erp-os-focus-finalizar-pagamento.window="
            $nextTick(() => {
                const d = $event.detail?.[0] ?? $event.detail ?? {};
                const el = document.getElementById('erp-os-finalizar-valor-' + (d.index ?? 0));
                if (d.valor != null && d.valor !== '' && el) {
                    el.value = d.valor;
                    delete el.dataset.erpMaskSynced;
                    window.ErpMasks?.apply(el, { sync: false });
                }
                el?.focus();
                el?.select?.();
            })
        "
    >
        <div class="erp-pdv-modal__backdrop" wire:click="cancelarFaturamentoOs"></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--finalizar">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-os-fin-title">{{ $titulo }}</h2>
                <button type="button" class="erp-pdv-modal__close" wire:click="cancelarFaturamentoOs" title="Fechar">✕</button>
            </header>

            <div class="erp-pdv-finalizar">
                <div class="erp-pdv-finalizar__top erp-fv-fin__top">
                    <div class="erp-fv-fin__cliente-row">
                        <label class="erp-pdv-finalizar__field erp-fv-fin__field--cliente">
                            <span class="erp-pdv-finalizar__label">Cliente</span>
                            <input
                                type="text"
                                class="erp-pdv-finalizar__input"
                                id="erp-os-fin-cliente"
                                value="{{ $this->clienteSearch !== '' ? $this->clienteSearch : '—' }}"
                                readonly
                                tabindex="-1"
                            >
                        </label>
                        <label class="erp-pdv-finalizar__field erp-fv-fin__field--vendedor">
                            <span class="erp-pdv-finalizar__label">Vendedor</span>
                            <input type="text" class="erp-pdv-finalizar__input" value="{{ $this->osVendedorLabel() }}" readonly tabindex="-1">
                        </label>
                    </div>
                    <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                            <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                            <span class="erp-pdv-finalizar__total-pagar-value" aria-live="polite">{{ $this->formatOsMoney($totalLiquido) }}</span>
                        </label>
                    </div>
                </div>

                @include('filament.components.erp.ordens-servico.form.faturamento-parcelas')

                <div class="erp-pdv-finalizar__body">
                    <div class="erp-pdv-finalizar__grid-wrap">
                        <table class="erp-pdv__grid erp-pdv-finalizar__grid">
                            <colgroup>
                                <col class="erp-pdv-finalizar__col-forma">
                                <col class="erp-pdv-finalizar__col-valor">
                                <col class="erp-pdv-finalizar__col-atalho">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th><u>F8</u> Forma de Pagamento</th>
                                    <th class="erp-pdv__grid-col-num">Valor</th>
                                    <th class="erp-pdv__grid-col-center">Atalho</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->osMeiosPagamento as $index => $meio)
                                    @php
                                        $forma = mb_strtoupper((string) ($meio['descricao'] ?? ''), 'UTF-8');
                                        $icone = match (true) {
                                            str_contains($forma, 'DINHEIRO') => 'cash',
                                            str_contains($forma, 'PIX') => 'pix',
                                            str_contains($forma, 'DÉBITO'), str_contains($forma, 'DEBITO') => 'debit',
                                            str_contains($forma, 'CRÉDITO'), str_contains($forma, 'CREDITO') => 'credit',
                                            \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::isFormaCrediario($forma) => 'wallet',
                                            str_contains($forma, 'CHEQUE') => 'cheque',
                                            str_contains($forma, 'BOLETO') => 'boleto',
                                            str_contains($forma, 'TRANSFER'), str_contains($forma, 'DEP') => 'transfer',
                                            default => 'cash',
                                        };
                                        $temValor = \App\Support\Erp\ErpMoney::parseBr($meio['valor'] ?? '0') > 0;
                                    @endphp
                                    <tr
                                        wire:key="os-meio-{{ $meio['id'] }}"
                                        wire:click="selectOsPagamento({{ $index }})"
                                        id="erp-os-finalizar-row-{{ $index }}"
                                        data-icone="{{ $icone }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv-finalizar__pay-row',
                                            'erp-pdv-finalizar__pay-row--filled' => $temValor,
                                            'erp-pdv__grid-row--selected' => $this->osPagamentoIndex === $index,
                                        ])
                                    >
                                        <td>
                                            <span class="erp-pdv-finalizar__forma">
                                                <span class="erp-pdv-finalizar__forma-icon erp-pdv-finalizar__forma-icon--{{ $icone }}" aria-hidden="true"></span>
                                                <span class="erp-pdv-finalizar__forma-nome">{{ $meio['descricao'] }}</span>
                                            </span>
                                        </td>
                                        <td class="erp-pdv__grid-col-num">
                                            <span class="erp-pdv-finalizar__valor-wrap">
                                                <span class="erp-pdv-finalizar__valor-rs">R$</span>
                                                <input
                                                    id="erp-os-finalizar-valor-{{ $index }}"
                                                    type="text"
                                                    wire:model.blur="osMeiosPagamento.{{ $index }}.valor"
                                                    wire:focus="selectOsPagamento({{ $index }})"
                                                    wire:keydown.enter.prevent="confirmarValorOsPagamento({{ $index }}, $event.target.value)"
                                                    class="erp-pdv-finalizar__grid-input"
                                                    data-mask="money-br"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                >
                                            </span>
                                        </td>
                                        <td
                                            class="erp-pdv__grid-col-center erp-pdv-finalizar__atalho"
                                            wire:click.stop="selectOsPagamentoByAtalho('{{ $meio['atalho'] }}')"
                                        >
                                            <kbd class="erp-pdv-finalizar__kbd">{{ $meio['atalho'] }}</kbd>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <aside class="erp-pdv-finalizar__totais-panel" aria-label="Totais da venda">
                        <div class="erp-pdv-finalizar__totais">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                <span class="erp-pdv-finalizar__label">Subtotal:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->subtotalGeral }}</span>
                                </span>
                            </label>
                            <div class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-fv-fin__ajuste" title="Acréscimo rateado nos itens">
                                <span class="erp-pdv-finalizar__label">Acréscimo:</span>
                                <div class="erp-fv-fin__ajuste-inputs">
                                    <label class="erp-fv-fin__ajuste-field">
                                        <span class="erp-fv-fin__ajuste-suffix">%</span>
                                        <input
                                            type="text"
                                            wire:model.live="osAcrescimoPct"
                                            class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num erp-fv-tv__input--acr"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            aria-label="Acréscimo percentual"
                                        >
                                    </label>
                                    <label class="erp-fv-fin__ajuste-field">
                                        <span class="erp-fv-fin__ajuste-suffix">R$</span>
                                        <input
                                            type="text"
                                            wire:model.live="osAcrescimoValor"
                                            class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num erp-fv-tv__input--acr"
                                            data-mask="money-br"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            aria-label="Acréscimo em reais"
                                        >
                                    </label>
                                </div>
                            </div>
                            <div class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-fv-fin__ajuste" title="Desconto rateado nos itens">
                                <span class="erp-pdv-finalizar__label">Desconto:</span>
                                <div class="erp-fv-fin__ajuste-inputs">
                                    <label class="erp-fv-fin__ajuste-field">
                                        <span class="erp-fv-fin__ajuste-suffix">%</span>
                                        <input
                                            type="text"
                                            wire:model.live="osDescontoPct"
                                            class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num erp-fv-tv__input--desc"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            aria-label="Desconto percentual"
                                        >
                                    </label>
                                    <label class="erp-fv-fin__ajuste-field">
                                        <span class="erp-fv-fin__ajuste-suffix">R$</span>
                                        <input
                                            type="text"
                                            wire:model.live="osDescontoValor"
                                            class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num erp-fv-tv__input--desc"
                                            data-mask="money-br"
                                            inputmode="decimal"
                                            autocomplete="off"
                                            aria-label="Desconto em reais"
                                        >
                                    </label>
                                </div>
                            </div>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total">
                                <span class="erp-pdv-finalizar__label">Valor Restante:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->formatOsMoney($restante) }}</span>
                                </span>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                                <span class="erp-pdv-finalizar__label">Troco:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value">{{ $this->formatOsMoney($troco) }}</span>
                                </span>
                            </label>
                        </div>
                    </aside>
                </div>
            </div>

            <footer class="erp-pdv-modal__footer erp-pdv-finalizar__footer-actions erp-fv-fin__footer">
                <div class="erp-pdv-finalizar__operacao-botoes">
                    <button
                        type="button"
                        class="erp-pdv-modal__btn erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--faturar"
                        wire:click="faturarOs"
                        wire:loading.attr="disabled"
                        id="erp-os-finalizar-op-faturar"
                    >
                        <kbd>F8</kbd>
                        <span wire:loading.remove wire:target="faturarOs">Faturar</span>
                        <span wire:loading wire:target="faturarOs">Faturando…</span>
                    </button>
                </div>
                <button type="button" wire:click="cancelarFaturamentoOs" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger">
                    <kbd>Esc</kbd> Cancelar
                </button>
            </footer>
        </div>
    </div>
@endteleport
@endif
