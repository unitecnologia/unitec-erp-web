@php
    $totalLiquido = $this->totalLiquido();
    $restante = $this->valorRestante();
    $troco = $this->troco();
    $davTitulo = $this->davNumero
        ? 'Forma de Pagamento — DAV '.$this->davNumero
        : 'Forma de Pagamento';
@endphp

<div
    class="erp-pdv-modal erp-pdv-modal--centered erp-pdv-modal--payment erp-fv-fin"
    role="dialog"
    aria-modal="true"
    aria-labelledby="erp-fv-fin-title"
    x-data
    x-on:keydown.window.capture="
        if (window.__erpFvFinClosing || window.__erpFvConcluirLock) return;
        if ($wire.etapa !== 'finalizacao') return;
        if ($wire.gravando) return;
        if ($wire.finalizarCartaoCanhotoAberta) return;
        if ($wire.fvTabelaPrazoConsulta) return;
        if ($wire.fvTransporteModalOpen) return;
        const t = $event.target;
        if (
            t?.id === 'erp-fv-fin-cliente'
            || t?.id === 'erp-fv-finalizar-cpf'
            || t?.id === 'erp-fv-finalizar-informacoes'
            || t?.closest?.('.erp-fv-fin__ajuste')
            || t?.closest?.('[data-erp-dividir-conta]')
        ) return;
        const k = ($event.key || '').toUpperCase();
        if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
        // Letras A–Z ou teclas F cadastradas — nunca dígitos (digitação do Valor).
        if (! /^[A-Z]$/.test(k) && ! /^F([1-9]|1[0-2])$/.test(k)) return;
        const atalhos = Array.from(document.querySelectorAll('.erp-fv-fin .erp-pdv-finalizar__kbd'))
            .map((el) => (el.textContent || '').trim().toUpperCase())
            .filter((v) => /^[A-Z]$/.test(v) || /^F([1-9]|1[0-2])$/.test(v));
        if (! atalhos.includes(k)) return;
        $event.preventDefault();
        $event.stopPropagation();
        // 0 rede no fluxo comum (cartão/carnê: sync em background).
        window.erpFvSelectPagamentoByAtalhoLocal?.(k, $wire);
    "
    x-on:erp-fv-focus-pagamento.window="
        const d = $event.detail?.[0] ?? $event.detail ?? {};
        const epoch = window.__erpFvPayNav?.focusEpoch ?? 0;
        $nextTick(() => {
            // Resposta Livewire atrasada não pode roubar foco após navegação local / Concluir.
            if ((window.__erpFvPayNav?.focusEpoch ?? 0) !== epoch) return;
            if (window.__erpFvPayNav?.concluirFocused) return;
            window.erpFvApplyPagamentoSelection?.(Number(d.index ?? 0), {
                focus: true,
                fromServer: true,
                valor: d.valor ?? null,
            });
        })
    "
    x-on:erp-fv-focus-finalizar-ok.window="
        $nextTick(() => {
            window.erpFvFocusConcluirBtn?.();
        })
    "
    x-on:erp-fv-focus-finalizar-parcelas.window="
        $nextTick(() => {
            const el = document.getElementById('erp-fv-parcelas-qtd');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:erp-fv-focus-finalizar-tabelas-predefinidas.window="
        $nextTick(() => {
            document.querySelector('#erp-fv-parcelas-tabelas .erp-pdv__grid-row--selected')
                ?.scrollIntoView({ block: 'nearest' });
        })
    "
    x-on:erp-pdv-focus-finalizar-cartao-canhoto.window="
        $nextTick(() => {
            const el = document.getElementById('erp-fv-canhoto-nsu');
            el?.focus();
            el?.select?.();
        })
    "
    x-on:erp-fv-scroll-cliente-sugestao.window="
        $nextTick(() => {
            document.getElementById('erp-fv-fin-cliente-sug-' + ($event.detail.index ?? 0))?.scrollIntoView({ block: 'nearest' });
        })
    "
>
    <div
        class="erp-pdv-modal__backdrop"
        x-on:click.prevent="window.erpFvFecharFinalizacaoInstantaneo?.($wire)"
    ></div>

    <div class="erp-pdv-modal__window erp-pdv-modal__window--finalizar">
        <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
            <h2 id="erp-fv-fin-title">{{ $davTitulo }}</h2>
            <button
                type="button"
                class="erp-pdv-modal__close"
                title="Fechar"
                x-on:click.prevent="window.erpFvFecharFinalizacaoInstantaneo?.($wire)"
            >✕</button>
        </header>

        <div class="erp-pdv-finalizar">
            <div class="erp-pdv-finalizar__top erp-fv-fin__top">
                <div class="erp-fv-fin__cliente-row">
                    <label
                        class="erp-pdv-finalizar__field erp-fv-fin__field--cliente erp-fv-fin__field--suggest"
                        data-fv-cliente-lookup-url="{{ route('erp.fv.clientes-lookup') }}"
                    >
                        <span class="erp-pdv-finalizar__label">Cliente</span>
                        {{--
                          Sem wire:model.live: digitar não remorpha o F4.
                          Commit só em selecionarCliente (lista) — texto livre não troca o cliente.
                        --}}
                        <input
                            type="text"
                            class="erp-pdv-finalizar__input"
                            id="erp-fv-fin-cliente"
                            value="{{ $this->clienteBusca }}"
                            data-fv-cliente-committed="{{ $this->clienteBusca }}"
                            placeholder="Buscar cliente por nome, código ou CPF/CNPJ…"
                            autocomplete="off"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="erp-fv-fin-cliente-sugestoes"
                            x-on:focus="$el.select()"
                            x-on:mouseup.stop.prevent="$el.select()"
                            x-on:blur="
                                $nextTick(() => {
                                    if (document.activeElement?.closest?.('#erp-fv-fin-cliente-lookup-root')) return;
                                    window.erpFvFinClienteRestoreCommitted?.();
                                })
                            "
                        >
                        <div id="erp-fv-fin-cliente-lookup-root" class="erp-fv-fin__cliente-lookup-root" wire:ignore></div>
                    </label>
                    <label class="erp-pdv-finalizar__field erp-fv-fin__field--vendedor">
                        <span class="erp-pdv-finalizar__label">Vendedor</span>
                        <input type="text" class="erp-pdv-finalizar__input" value="{{ $this->vendedorLabel !== '' ? $this->vendedorLabel : '—' }}" readonly tabindex="-1">
                    </label>
                </div>
                @if ($this->fvRateioPessoa)
                    <div
                        class="erp-pdv-finalizar__split"
                        data-erp-dividir-conta
                        data-erp-dividir-total="{{ number_format((float) $totalLiquido, 2, '.', '') }}"
                        x-data="erpDividirContaStepper({
                            initial: {{ (int) max(1, (int) ($this->fvDividirPor ?? 1)) }},
                            total: {{ (float) $totalLiquido }},
                            wirePath: 'fvDividirPor',
                        })"
                    >
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--split erp-pdv-finalizar__field--dividir">
                            <span class="erp-pdv-finalizar__label">Dividir</span>
                            <div class="erp-pdv-finalizar__dividir">
                                <input
                                    id="erp-fv-dividir-por"
                                    type="number"
                                    min="1"
                                    step="1"
                                    inputmode="numeric"
                                    value="{{ (int) max(1, (int) ($this->fvDividirPor ?? 1)) }}"
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
                                <span class="erp-pdv-finalizar__money-value" x-text="valorTexto">{{ $this->fvValorPorPessoa }}</span>
                            </span>
                        </label>
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                            <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                            <span
                                class="erp-pdv-finalizar__total-pagar-value"
                                data-erp-fv-total-pagar
                                data-erp-dividir-total-src
                                aria-live="polite"
                            >{{ $this->formatMoney($totalLiquido) }}</span>
                        </label>
                    </div>
                @else
                    <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                            <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                            <span
                                class="erp-pdv-finalizar__total-pagar-value"
                                data-erp-fv-total-pagar
                                aria-live="polite"
                            >{{ $this->formatMoney($totalLiquido) }}</span>
                        </label>
                    </div>
                @endif
            </div>

            @include('filament.components.erp.forca-vendas.tela-venda.finalizacao-parcelas')

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
                            @foreach ($this->meiosPagamento as $index => $meio)
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
                                        'usa_tef' => $meio['usa_tef'] ?? false,
                                    ];
                                    $fvAPrazo = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::isFormaAPrazoPagamento($pdvFlags);
                                    $fvPrecisaParcelas = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdvFlags);
                                    $fvCartao = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::isFormaCartao($pdvFlags);
                                    $fvTef = \App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper::isFormaTef($pdvFlags);
                                @endphp
                                <tr
                                    wire:key="fv-meio-{{ $meio['id'] }}"
                                    x-on:mousedown="window.erpFvApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: false })"
                                    x-on:click.prevent="$wire.selectPagamentoRow({{ $index }}, false)"
                                    id="erp-fv-finalizar-row-{{ $index }}"
                                    data-erp-fv-pay-index="{{ $index }}"
                                    data-icone="{{ $icone }}"
                                    data-fv-a-prazo="{{ $fvAPrazo ? '1' : '0' }}"
                                    data-fv-precisa-parcelas="{{ $fvPrecisaParcelas ? '1' : '0' }}"
                                    data-fv-cartao="{{ $fvCartao ? '1' : '0' }}"
                                    data-fv-tef="{{ $fvTef ? '1' : '0' }}"
                                    @class([
                                        'erp-pdv__grid-row',
                                        'erp-pdv-finalizar__pay-row',
                                        'erp-pdv-finalizar__pay-row--filled' => $temValor,
                                        'erp-pdv__grid-row--selected' => $this->selectedPagamentoIndex === $index,
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
                                            {{--
                                              Sem wire:model.blur: seta troca o foco e o blur NÃO gera request.
                                              Sem atributo wire:model o ErpMasks formata no blur mas não chama wire.set.
                                              Valor fica no DOM até flush (Enter / atalho / clique / F10).
                                            --}}
                                            <input
                                                id="erp-fv-finalizar-valor-{{ $index }}"
                                                type="text"
                                                value="{{ $meio['valor'] ?? '0,00' }}"
                                                x-on:focus="
                                                    window.erpFvApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: true });
                                                    $el.select();
                                                "
                                                {{-- mouseup do browser cancela o select() do focus; prevent restaura o destaque azul --}}
                                                x-on:mouseup.stop.prevent="$el.select()"
                                                x-on:click.stop="
                                                    window.erpFvApplyPagamentoSelection?.({{ $index }}, { focus: false, fromServer: true });
                                                    $el.select();
                                                "
                                                x-on:keydown.enter.prevent="
                                                    if ($event.repeat) return;
                                                    $event.stopPropagation();
                                                    window.erpFvConfirmarValorEnterLocal?.({{ $index }}, $wire);
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
                                            x-on:click.stop.prevent="window.erpFvSelectPagamentoByAtalhoLocal?.('{{ $meio['atalho'] }}', $wire)"
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
                                <span class="erp-pdv-finalizar__money-value">{{ $this->formatMoney($this->totalBruto()) }}</span>
                            </span>
                        </label>
                        <div class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-fv-fin__ajuste" title="Acréscimo rateado nos itens">
                            <span class="erp-pdv-finalizar__label">Acréscimo:</span>
                            <div class="erp-fv-fin__ajuste-inputs">
                                <label class="erp-fv-fin__ajuste-field">
                                    <span class="erp-fv-fin__ajuste-suffix">%</span>
                                    <input
                                        type="text"
                                        wire:model.live="acrescimoPedidoPct"
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
                                        wire:model.live="acrescimoPedidoValor"
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
                                        wire:model.live="descontoPedidoPct"
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
                                        wire:model.live="descontoPedidoValor"
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
                                <span class="erp-pdv-finalizar__money-value" data-erp-fv-restante>{{ $this->formatMoney($restante) }}</span>
                            </span>
                        </label>
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                            <span class="erp-pdv-finalizar__label">Troco:</span>
                            <span class="erp-pdv-finalizar__money" aria-live="polite">
                                <span class="erp-pdv-finalizar__money-rs">R$</span>
                                <span class="erp-pdv-finalizar__money-value" data-erp-fv-troco>{{ $this->formatMoney($troco) }}</span>
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
                            {{-- Sem wire:model.live: valor no DOM até flush (F10 / atalho / Voltar). --}}
                            <input
                                id="erp-fv-finalizar-cpf"
                                type="text"
                                value="{{ $this->cpfNota }}"
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
                            {{-- Mesma propriedade $observacoes da Observação do Pedido. --}}
                            <textarea
                                id="erp-fv-finalizar-informacoes"
                                class="erp-pdv-finalizar__informacoes"
                                spellcheck="false"
                            >{{ $this->observacoes }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($this->finalizarCartaoCanhotoAberta)
            <div class="erp-pdv-canhoto-overlay" role="dialog" aria-labelledby="erp-fv-canhoto-title">
                <div class="erp-pdv-parcelas erp-pdv-canhoto">
                    <header class="erp-pdv-parcelas__header">
                        <h3 id="erp-fv-canhoto-title">Canhoto POS | Contas a Receber</h3>
                        <button type="button" class="erp-pdv-modal__close" wire:click="cancelFinalizarCartaoCanhoto" title="Fechar">✕</button>
                    </header>

                    <div class="erp-pdv-parcelas__toolbar">
                        <div class="erp-pdv-parcelas__avulso">
                            <span class="erp-pdv-parcelas__avulso-title">Dados do canhoto</span>
                            <div class="erp-pdv-parcelas__avulso-fields">
                                <label class="erp-pdv-parcelas__field">
                                    <span>Total</span>
                                    <input type="text" value="{{ \App\Support\Erp\ErpMoney::formatBr($this->finalizarCartaoTotalValor) }}" readonly tabindex="-1">
                                </label>
                                <label class="erp-pdv-parcelas__field">
                                    <span>NSU</span>
                                    <input id="erp-fv-canhoto-nsu" type="text" wire:model="finalizarCartaoNsu" autocomplete="off">
                                </label>
                                <label class="erp-pdv-parcelas__field">
                                    <span>Autorização</span>
                                    <input id="erp-fv-canhoto-autorizacao" type="text" wire:model="finalizarCartaoAutorizacao" autocomplete="off">
                                </label>
                                <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                                    <span>Maquininha</span>
                                    <select id="erp-fv-canhoto-maquininha" wire:model="finalizarCartaoMaquininha">
                                        <option value="">— Selecione —</option>
                                        @foreach (\App\Models\CartaoMaquininha::optionsAtivas() as $nome)
                                            <option value="{{ $nome }}">{{ $nome }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="erp-pdv-parcelas__field erp-pdv-parcelas__field--bandeira">
                                    <span>Bandeira</span>
                                    <select id="erp-fv-canhoto-bandeira" wire:model="finalizarCartaoBandeira">
                                        <option value="">— Selecione —</option>
                                        @foreach (\App\Models\CartaoBandeira::optionsAtivas() as $nome)
                                            <option value="{{ $nome }}">{{ $nome }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                            <div class="erp-pdv-parcelas__avulso-fields" style="margin-top:0.5rem;">
                                <label class="erp-pdv-parcelas__field">
                                    <span>Qtd. Parcelas</span>
                                    <input id="erp-fv-canhoto-qtd" type="text" wire:model="finalizarCartaoParcelasQtd" data-mask="integer" inputmode="numeric" autocomplete="off">
                                </label>
                                <label class="erp-pdv-parcelas__field">
                                    <span>Intervalo (dias)</span>
                                    <input id="erp-fv-canhoto-intervalo" type="text" wire:model="finalizarCartaoIntervalo" data-mask="integer" inputmode="numeric" autocomplete="off">
                                </label>
                                <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="gerarParcelasCartaoCanhoto">
                                    <kbd>F2</kbd> Gerar
                                </button>
                            </div>
                        </div>

                        <div class="erp-pdv-parcelas__toolbar-actions">
                            <button type="button" class="erp-pdv-modal__btn" wire:click="cancelFinalizarCartaoCanhoto">
                                <kbd>Esc</kbd> Cancelar
                            </button>
                        </div>
                    </div>

                    <div class="erp-pdv-parcelas__grid-wrap" id="erp-fv-finalizar-cartao-canhoto">
                        <table class="erp-pdv__grid erp-pdv-parcelas__grid">
                            <thead>
                                <tr>
                                    <th>Documento</th>
                                    <th>Vencimento</th>
                                    <th class="erp-pdv__grid-col-num">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($this->finalizarCartaoParcelasRows as $index => $row)
                                    <tr
                                        wire:click="selectFinalizarCartaoParcelaRow({{ $index }})"
                                        wire:key="fv-cartao-parcela-{{ $index }}"
                                        id="erp-fv-finalizar-canhoto-row-{{ $index }}"
                                        @class([
                                            'erp-pdv__grid-row',
                                            'erp-pdv__grid-row--selected' => $this->selectedFinalizarCartaoParcelaIndex === $index,
                                        ])
                                    >
                                        <td>{{ $row['documento'] ?? '—' }}</td>
                                        <td>{{ $row['vencimento'] ?? '—' }}</td>
                                        <td class="erp-pdv__grid-col-num">R$ {{ $row['valor'] ?? '0,00' }}</td>
                                    </tr>
                                @empty
                                    <tr class="erp-pdv__grid-empty">
                                        <td colspan="3">Informe NSU/Autorização/Bandeira e use F2 | Gerar parcelas.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <footer class="erp-pdv-parcelas__footer">
                        <div class="erp-pdv-parcelas__total">
                            <span>Total Parcelas</span>
                            <strong>{{ $this->finalizarCartaoParcelasTotalLabel !== '' ? 'R$ '.$this->finalizarCartaoParcelasTotalLabel : '' }}</strong>
                        </div>
                        <div class="erp-pdv-parcelas__footer-actions">
                            <button type="button" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary" wire:click="concluirCartaoCanhoto">
                                <kbd>F7</kbd> Concluir
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
        @endif

        <footer
            class="erp-pdv-modal__footer erp-pdv-finalizar__footer-actions erp-fv-fin__footer"
            data-erp-atalho-concluir="{{ $this->atalhoConcluirVenda() }}"
        >
            <div class="erp-pdv-finalizar__operacao-botoes">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-fv-fin__transporte-btn{{ $this->fvTransportePayload() ? ' is-filled' : '' }}"
                    wire:click="abrirFvTransporteModal"
                    id="erp-fv-finalizar-op-transporte"
                    title="Transportadora, volumes e peso (mesmo da NF-e){{ $this->atalhoConcluirVenda() === 'F9' ? '' : ' — F9' }}"
                    aria-pressed="{{ $this->fvTransportePayload() ? 'true' : 'false' }}"
                >
                    <span>Transportadora</span>
                    @if ($resumo = $this->fvTransporteResumo())
                        <span class="erp-fv-fin__transporte-resumo">{{ $resumo }}</span>
                    @endif
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--concluir"
                    x-on:click.prevent="
                        if ($wire.gravando) return;
                        if (window.__erpFvConcluirLock) return;
                        window.erpFvConcluirComFlush?.($wire);
                    "
                    x-on:keydown.enter.prevent="
                        if ($event.repeat) return;
                        if ($wire.gravando) return;
                        if (window.__erpFvConcluirLock) return;
                        $event.stopPropagation();
                        window.erpFvConcluirComFlush?.($wire);
                    "
                    wire:loading.attr="disabled"
                    wire:target="faturarPedidoComFlush,faturarPedido"
                    @disabled($this->gravando)
                    id="erp-fv-finalizar-op-concluir"
                >
                    <kbd>{{ $this->atalhoConcluirVenda() }}</kbd>
                    <span
                        data-erp-fv-concluir-idle
                        wire:loading.remove
                        wire:target="faturarPedidoComFlush,faturarPedido"
                    >Concluir</span>
                    <span
                        data-erp-fv-concluir-busy
                        wire:loading
                        wire:target="faturarPedidoComFlush,faturarPedido"
                        hidden
                    >Concluindo…</span>
                </button>
            </div>
            <button
                type="button"
                class="erp-pdv-modal__btn erp-fv-fin__voltar-btn"
                title="Voltar à Tela de Venda (não cancela a venda)"
                x-on:click.prevent="window.erpFvFecharFinalizacaoInstantaneo?.($wire)"
            >
                <kbd>Esc</kbd> Voltar
            </button>
        </footer>
    </div>
</div>

@include('filament.components.erp.forca-vendas.tela-venda.finalizacao-transporte')
