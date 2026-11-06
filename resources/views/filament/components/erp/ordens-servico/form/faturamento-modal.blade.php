@if ($this->osFaturamentoOpen)
    @php
        $totalLiquido = $this->osTotalLiquido();
        $restante = $this->osValorRestante();
        $troco = $this->osTroco();
        $osNumero = $this->osNumeroDisplay();
        $titulo = $osNumero !== ''
            ? 'Forma de Pagamento — OS '.$osNumero
            : 'Forma de Pagamento';
        $clienteLabel = $this->clienteSearch !== '' ? $this->clienteSearch : '—';
    @endphp
@teleport('body')

    <div
        class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-modal--payment erp-fv-fin erp-os-fin"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-os-fin-title"
        x-data
        {{-- Paridade TV: atalhos de meio em window.capture (antes do input Valor digitar a letra). --}}
        x-on:keydown.window.capture="
            if (window.__erpOsFinClosing) return;
            if ($el.style.display === 'none' || $el.getAttribute('aria-hidden') === 'true' || $el.getAttribute('data-erp-os-fin-closing') === '1') return;
            if (! $wire.osFaturamentoOpen) return;
            if ($wire.osTabelaPrazoConsulta) return;
            if ($wire.osTransporteModalOpen) return;
            const t = $event.target;
            if (
                t?.id === 'erp-os-fin-cliente'
                || t?.id === 'erp-os-finalizar-cpf'
                || t?.id === 'erp-os-finalizar-informacoes'
                || t?.closest?.('.erp-fv-fin__ajuste')
                || t?.closest?.('[data-erp-dividir-conta]')
            ) return;
            if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
            const k = ($event.key || '').toUpperCase();
            const atalhoConcluir = (
                document.querySelector('.erp-os-fin [data-erp-atalho-concluir]')?.getAttribute('data-erp-atalho-concluir')
                || @js($this->atalhoConcluirOs())
                || 'F10'
            ).toUpperCase();
            if (k === atalhoConcluir) {
                $event.preventDefault();
                $event.stopPropagation();
                if ($wire.osPersistindo) return;
                if (window.__erpOsConcluirLock) return;
                window.erpOsConcluirComFlush?.($wire);
                return;
            }
            // F9 = Transportadora (paridade TV), salvo quando F9 é o Concluir.
            if (k === 'F9' && atalhoConcluir !== 'F9') {
                $event.preventDefault();
                $event.stopPropagation();
                $wire.abrirOsTransporteModal();
                return;
            }
            if (! /^[A-Z]$/.test(k) && ! /^F([1-9]|1[0-2])$/.test(k)) return;
            const atalhos = Array.from(document.querySelectorAll('.erp-os-fin .erp-pdv-finalizar__kbd'))
                .map((el) => (el.textContent || '').trim().toUpperCase())
                .filter((v) => /^[A-Z]$/.test(v) || /^F([1-9]|1[0-2])$/.test(v));
            if (! atalhos.includes(k)) return;
            $event.preventDefault();
            $event.stopPropagation();
            // 0 rede: seleção + restante + foco locais.
            window.erpOsSelectPagamentoByAtalhoLocal?.(k, $wire);
        "
        x-on:erp-os-focus-finalizar-pagamento.window="
            const d = $event.detail?.[0] ?? $event.detail ?? {};
            const epoch = window.__erpOsPayNav?.focusEpoch ?? 0;
            $nextTick(() => {
                // Resposta atrasada após Enter→Concluir: não devolve o foco ao valor.
                if ((window.__erpOsPayNav?.focusEpoch ?? 0) !== epoch) return;
                if (window.__erpOsPayNav?.concluirFocused) return;
                window.erpOsApplyPagamentoSelection?.(Number(d.index ?? 0), {
                    focus: true,
                    fromServer: true,
                    valor: d.valor ?? null,
                });
            })
        "
        x-on:erp-os-focus-finalizar-ok.window="
            $nextTick(() => {
                window.erpOsFocusConcluirBtn?.();
            })
        "
    >
        <div
            class="erp-pdv-modal__backdrop"
            x-on:click.prevent="
                typeof window.erpOsFecharFaturamentoInstantaneo === 'function'
                    ? window.erpOsFecharFaturamentoInstantaneo($wire)
                    : $wire.voltarDoFaturamentoOs()
            "
        ></div>

        <div class="erp-pdv-modal__window erp-pdv-modal__window--finalizar">
            <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
                <h2 id="erp-os-fin-title">{{ $titulo }}</h2>
                <button
                    type="button"
                    class="erp-pdv-modal__close"
                    title="Fechar"
                    x-on:click.prevent="
                        typeof window.erpOsFecharFaturamentoInstantaneo === 'function'
                            ? window.erpOsFecharFaturamentoInstantaneo($wire)
                            : $wire.voltarDoFaturamentoOs()
                    "
                >✕</button>
            </header>

            <div class="erp-pdv-finalizar">
                <div class="erp-pdv-finalizar__top erp-fv-fin__top">
                    <div class="erp-fv-fin__cliente-row">
                        <label
                            class="erp-pdv-finalizar__field erp-fv-fin__field--cliente erp-fv-fin__field--suggest"
                            data-os-cliente-lookup-url="{{ route('erp.os.clientes-lookup') }}"
                        >
                            <span class="erp-pdv-finalizar__label">Cliente</span>
                            <input
                                type="text"
                                class="erp-pdv-finalizar__input"
                                id="erp-os-fin-cliente"
                                value="{{ $clienteLabel }}"
                                data-os-cliente-committed="{{ $clienteLabel }}"
                                placeholder="Buscar cliente por nome, código ou CPF/CNPJ…"
                                autocomplete="off"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                @disabled($this->osReadOnly())
                                x-on:focus="$el.select()"
                                x-on:mouseup.stop.prevent="$el.select()"
                                x-on:blur="
                                    $nextTick(() => {
                                        if (document.activeElement?.closest?.('#erp-os-fin-cliente-lookup-root')) return;
                                        window.erpOsFinClienteRestoreCommitted?.();
                                    })
                                "
                            >
                            <div id="erp-os-fin-cliente-lookup-root" class="erp-fv-fin__cliente-lookup-root" wire:ignore></div>
                        </label>
                        <label class="erp-pdv-finalizar__field erp-fv-fin__field--vendedor">
                            <span class="erp-pdv-finalizar__label">Vendedor</span>
                            <input type="text" class="erp-pdv-finalizar__input" value="{{ $this->osVendedorLabel() }}" readonly tabindex="-1">
                        </label>
                    </div>
                    @if ($this->osRateioPessoa)
                        <div
                            class="erp-pdv-finalizar__split"
                            data-erp-dividir-conta
                            data-erp-dividir-total="{{ number_format((float) $totalLiquido, 2, '.', '') }}"
                            x-data="erpDividirContaStepper({
                                initial: {{ (int) max(1, (int) ($this->osDividirPor ?? 1)) }},
                                total: {{ (float) $totalLiquido }},
                                wirePath: 'osDividirPor',
                            })"
                        >
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--split erp-pdv-finalizar__field--dividir">
                                <span class="erp-pdv-finalizar__label">Dividir</span>
                                <div class="erp-pdv-finalizar__dividir">
                                    <input
                                        id="erp-os-dividir-por"
                                        type="number"
                                        min="1"
                                        step="1"
                                        inputmode="numeric"
                                        value="{{ (int) max(1, (int) ($this->osDividirPor ?? 1)) }}"
                                        class="erp-pdv-finalizar__input erp-pdv-finalizar__input--num erp-pdv-finalizar__dividir-input"
                                        x-model.number="pessoas"
                                        x-on:input="onInput()"
                                        x-on:blur="onBlur()"
                                        x-on:keydown.arrow-up.stop.prevent="bump(1)"
                                        x-on:keydown.arrow-down.stop.prevent="bump(-1)"
                                        autocomplete="off"
                                        aria-label="Dividir a conta por quantidade de pessoas"
                                    >
                                    <div class="erp-pdv-finalizar__dividir-spinners" aria-hidden="true">
                                        <button type="button" tabindex="-1" class="erp-pdv-finalizar__dividir-btn" title="Aumentar" x-on:click.prevent="bump(1)">▲</button>
                                        <button type="button" tabindex="-1" class="erp-pdv-finalizar__dividir-btn" title="Diminuir" x-on:click.prevent="bump(-1)">▼</button>
                                    </div>
                                </div>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--split">
                                <span class="erp-pdv-finalizar__label">Valor por Pessoa:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value" x-text="valorTexto">{{ $this->osValorPorPessoa }}</span>
                                </span>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <span
                                    class="erp-pdv-finalizar__total-pagar-value"
                                    data-erp-dividir-total-src
                                    data-erp-os-total-pagar
                                    aria-live="polite"
                                >{{ $this->formatOsMoney($totalLiquido) }}</span>
                            </label>
                        </div>
                    @else
                        <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                                <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                                <span
                                    class="erp-pdv-finalizar__total-pagar-value"
                                    data-erp-os-total-pagar
                                    aria-live="polite"
                                >{{ $this->formatOsMoney($totalLiquido) }}</span>
                            </label>
                        </div>
                    @endif
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
                                    <th>Forma de Pagamento</th>
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
                                        $pdvFlags = [
                                            'forma' => (string) ($meio['descricao'] ?? ''),
                                            'tipo' => (string) ($meio['tipo'] ?? ''),
                                            'tipo_movimento' => (string) ($meio['tipo_movimento'] ?? ''),
                                        ];
                                        $osAPrazo = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::isFormaAPrazoPagamento($pdvFlags);
                                        $osPrecisaParcelas = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdvFlags);
                                    @endphp
                                    <tr
                                        wire:key="os-meio-{{ $meio['id'] }}"
                                        x-on:mousedown="window.erpOsApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: false })"
                                        x-on:click.prevent="$wire.selectOsPagamento({{ $index }})"
                                        id="erp-os-finalizar-row-{{ $index }}"
                                        data-icone="{{ $icone }}"
                                        data-os-a-prazo="{{ $osAPrazo ? '1' : '0' }}"
                                        data-os-precisa-parcelas="{{ $osPrecisaParcelas ? '1' : '0' }}"
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
                                            <span class="erp-pdv-finalizar__valor-wrap" wire:ignore>
                                                <span class="erp-pdv-finalizar__valor-rs">R$</span>
                                                <input
                                                    id="erp-os-finalizar-valor-{{ $index }}"
                                                    type="text"
                                                    value="{{ $meio['valor'] ?? '0,00' }}"
                                                    x-on:focus="
                                                        window.erpOsApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: false });
                                                        $el.select();
                                                    "
                                                    x-on:mouseup.stop.prevent="$el.select()"
                                                    x-on:click.stop="
                                                        window.erpOsApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: false });
                                                        $el.select();
                                                    "
                                                    x-on:keydown.enter.prevent="
                                                        if ($event.repeat) return;
                                                        $event.stopPropagation();
                                                        window.erpOsConfirmarValorEnterLocal?.({{ $index }}, $wire);
                                                    "
                                                    class="erp-pdv-finalizar__grid-input"
                                                    data-mask="money-br"
                                                    inputmode="decimal"
                                                    autocomplete="off"
                                                >
                                            </span>
                                        </td>
                                        <td
                                            class="erp-pdv__grid-col-center erp-pdv-finalizar__atalho"
                                            @if (filled($meio['atalho'] ?? null))
                                                x-on:click.stop.prevent="window.erpOsSelectPagamentoByAtalhoLocal?.('{{ $meio['atalho'] }}', $wire)"
                                            @endif
                                        >
                                            @if (filled($meio['atalho'] ?? null))
                                                <kbd class="erp-pdv-finalizar__kbd">{{ $meio['atalho'] }}</kbd>
                                            @endif
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
                                            wire:model.live.debounce.300ms="osAcrescimoPct"
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
                                            wire:model.live.debounce.300ms="osAcrescimoValor"
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
                                            wire:model.live.debounce.300ms="osDescontoPct"
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
                                            wire:model.live.debounce.300ms="osDescontoValor"
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
                                    <span class="erp-pdv-finalizar__money-value" data-erp-os-restante>{{ $this->formatOsMoney($restante) }}</span>
                                </span>
                            </label>
                            <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                                <span class="erp-pdv-finalizar__label">Troco:</span>
                                <span class="erp-pdv-finalizar__money" aria-live="polite">
                                    <span class="erp-pdv-finalizar__money-rs">R$</span>
                                    <span class="erp-pdv-finalizar__money-value" data-erp-os-troco>{{ $this->formatOsMoney($troco) }}</span>
                                </span>
                            </label>
                        </div>
                    </aside>
                </div>

                <div class="erp-pdv-finalizar__footer erp-fv-fin__nota-footer">
                    <div class="erp-pdv-finalizar__footer-main erp-pdv-finalizar__footer-row">
                        <div class="erp-pdv-finalizar__cpf-wrap">
                            <span class="erp-pdv-finalizar__section-title">CPF na Nota</span>
                            <div class="erp-pdv-finalizar__cpf-input-wrap">
                                <input
                                    id="erp-os-finalizar-cpf"
                                    type="text"
                                    value="{{ $this->osCpfNota }}"
                                    class="erp-pdv-finalizar__input erp-pdv-finalizar__input--cpf erp-pdv-finalizar__cpf-input"
                                    data-mask="cpf-cnpj"
                                    data-mask-pessoa="fisica"
                                    inputmode="numeric"
                                    maxlength="14"
                                    autocomplete="off"
                                    x-on:focus="$el.select()"
                                    x-on:mouseup.stop.prevent="$el.select()"
                                >
                            </div>
                        </div>
                        <div class="erp-pdv-finalizar__informacoes-col">
                            <span class="erp-pdv-finalizar__section-title">Informações Adicionais</span>
                            <div class="erp-pdv-finalizar__informacoes-wrap">
                                <textarea
                                    id="erp-os-finalizar-informacoes"
                                    class="erp-pdv-finalizar__informacoes"
                                    spellcheck="false"
                                >{{ $this->observacoes }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer
                class="erp-pdv-modal__footer erp-pdv-finalizar__footer-actions erp-fv-fin__footer"
                data-erp-atalho-concluir="{{ $this->atalhoConcluirOs() }}"
            >
                <div class="erp-pdv-finalizar__operacao-botoes">
                    <button
                        type="button"
                        class="erp-pdv-modal__btn erp-fv-fin__transporte-btn{{ $this->osTransportePayload() ? ' is-filled' : '' }}"
                        wire:click="abrirOsTransporteModal"
                        id="erp-os-finalizar-op-transporte"
                        title="Transportadora, volumes e peso (mesmo da NF-e de Peças) — placa do frete, não do veículo da OS"
                        aria-pressed="{{ $this->osTransportePayload() ? 'true' : 'false' }}"
                    >
                        <span>Transportadora</span>
                        @if ($resumo = $this->osTransporteResumo())
                            <span class="erp-fv-fin__transporte-resumo">{{ $resumo }}</span>
                        @endif
                    </button>
                    <button
                        type="button"
                        class="erp-pdv-modal__btn erp-pdv-modal__btn--primary erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--concluir"
                        x-on:click.prevent="
                            if ($wire.osPersistindo) return;
                            if (window.__erpOsConcluirLock) return;
                            window.erpOsConcluirComFlush?.($wire);
                        "
                        x-on:keydown.enter.prevent="
                            if ($event.repeat) return;
                            if ($wire.osPersistindo) return;
                            if (window.__erpOsConcluirLock) return;
                            $event.stopPropagation();
                            window.erpOsConcluirComFlush?.($wire);
                        "
                        wire:loading.attr="disabled"
                        wire:target="faturarOsComFlush,faturarOs"
                        @disabled($this->osPersistindo)
                        id="erp-os-finalizar-op-faturar"
                    >
                        <kbd>{{ $this->atalhoConcluirOs() }}</kbd>
                        <span
                            data-erp-os-concluir-idle
                            wire:loading.remove
                            wire:target="faturarOsComFlush,faturarOs"
                        >Concluir</span>
                        <span
                            data-erp-os-concluir-busy
                            wire:loading
                            wire:target="faturarOsComFlush,faturarOs"
                            hidden
                        >Faturando…</span>
                    </button>
                </div>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-fv-fin__voltar-btn"
                    title="Voltar à OS (preserva pagamentos digitados)"
                    x-on:click.prevent="
                        typeof window.erpOsFecharFaturamentoInstantaneo === 'function'
                            ? window.erpOsFecharFaturamentoInstantaneo($wire)
                            : $wire.voltarDoFaturamentoOs()
                    "
                >
                    <kbd>Esc</kbd> Voltar
                </button>
            </footer>
        </div>
    </div>
@endteleport
@endif
