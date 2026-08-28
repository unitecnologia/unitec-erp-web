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
        if ($wire.etapa !== 'finalizacao') return;
        if ($wire.finalizarCartaoCanhotoAberta) return;
        const t = $event.target;
        if (t?.id === 'erp-fv-fin-cliente' || t?.closest?.('.erp-fv-fin__ajuste')) return;
        const k = $event.key || '';
        if ($event.ctrlKey || $event.altKey || $event.metaKey) return;
        // Apenas letras de atalho — nunca dígitos (digitação do Valor).
        if (k.length !== 1 || ! /[a-zA-Z]/.test(k)) return;
        const atalhos = Array.from(document.querySelectorAll('.erp-fv-fin .erp-pdv-finalizar__kbd'))
            .map((el) => (el.textContent || '').trim().toUpperCase())
            .filter((v) => v.length === 1);
        if (! atalhos.includes(k.toUpperCase())) return;
        $event.preventDefault();
        $event.stopPropagation();
        $wire.selectPagamentoByAtalho(k);
    "
    x-on:erp-fv-focus-pagamento.window="
        $nextTick(() => {
            const el = document.getElementById('erp-fv-finalizar-valor-' + ($event.detail.index ?? 0));
            el?.focus();
            el?.select?.();
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
    <div class="erp-pdv-modal__backdrop" wire:click="voltarParaVenda"></div>

    <div class="erp-pdv-modal__window erp-pdv-modal__window--finalizar">
        <header class="erp-pdv-modal__header erp-pdv-modal__header--with-close">
            <h2 id="erp-fv-fin-title">{{ $davTitulo }}</h2>
            <button type="button" class="erp-pdv-modal__close" wire:click="voltarParaVenda" title="Fechar">✕</button>
        </header>

        <div class="erp-pdv-finalizar">
            <div class="erp-pdv-finalizar__top erp-fv-fin__top">
                <div class="erp-fv-fin__cliente-row">
                    <label class="erp-pdv-finalizar__field erp-fv-fin__field--cliente erp-fv-fin__field--suggest">
                        <span class="erp-pdv-finalizar__label">Cliente</span>
                        <input
                            type="text"
                            class="erp-pdv-finalizar__input"
                            wire:model.live.debounce.250ms="clienteBusca"
                            wire:keydown.enter.prevent="confirmarClienteFinalizacao"
                            wire:keydown.escape.prevent="fecharSugestoesCliente"
                            x-on:keydown.arrow-down.prevent="$wire.moverSugestaoCliente(1)"
                            x-on:keydown.arrow-up.prevent="$wire.moverSugestaoCliente(-1)"
                            placeholder="Buscar cliente por nome, código ou CPF/CNPJ…"
                            autocomplete="off"
                            id="erp-fv-fin-cliente"
                        >
                        @if ($this->clienteSugestoesOpen && $this->clienteSugestoes !== [])
                            <ul class="erp-fv-fin__suggest erp-fv-fin__suggest--cliente" role="listbox">
                                @foreach ($this->clienteSugestoes as $index => $sug)
                                    <li wire:key="fv-fin-cli-sug-{{ $sug['id'] }}">
                                        <button
                                            type="button"
                                            id="erp-fv-fin-cliente-sug-{{ $index }}"
                                            wire:click="selecionarCliente({{ $sug['id'] }})"
                                            @class(['is-selected' => $this->selectedClienteSugestaoIndex === $index])
                                        >
                                            <span class="erp-fv-fin__suggest-code">{{ $sug['codigo'] }}</span>
                                            <span class="erp-fv-fin__suggest-nome">{{ $sug['nome'] }}</span>
                                            <span class="erp-fv-fin__suggest-credito">
                                                <span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--lim">Lim {{ $sug['limite'] ?? '0,00' }}</span>
                                                <span class="erp-fv-fin__suggest-cred erp-fv-fin__suggest-cred--util">Util {{ $sug['utilizado'] ?? '0,00' }}</span>
                                                <span @class([
                                                    'erp-fv-fin__suggest-cred',
                                                    'erp-fv-fin__suggest-cred--venc',
                                                    'is-vencido' => ($sug['tem_vencidas'] ?? false),
                                                ])>Venc {{ $sug['vencidas'] ?? '0,00' }}</span>
                                            </span>
                                            <span @class([
                                                'erp-fv-fin__suggest-doc',
                                                'is-cnpj' => ($sug['doc_tipo'] ?? '') === 'cnpj',
                                                'is-cpf' => ($sug['doc_tipo'] ?? '') === 'cpf',
                                                'is-empty' => blank($sug['cpf_cnpj'] ?? null),
                                            ])>{{ $sug['cpf_cnpj'] ?? '' }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </label>
                    <label class="erp-pdv-finalizar__field erp-fv-fin__field--vendedor">
                        <span class="erp-pdv-finalizar__label">Vendedor</span>
                        <input type="text" class="erp-pdv-finalizar__input" value="{{ $this->vendedorLabel !== '' ? $this->vendedorLabel : '—' }}" readonly tabindex="-1">
                    </label>
                </div>
                <div class="erp-pdv-finalizar__split erp-pdv-finalizar__split--single">
                    <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total-pagar">
                        <span class="erp-pdv-finalizar__label">Total à Pagar:</span>
                        <span class="erp-pdv-finalizar__total-pagar-value" aria-live="polite">{{ $this->formatMoney($totalLiquido) }}</span>
                    </label>
                </div>
            </div>

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
                                @endphp
                                <tr
                                    wire:key="fv-meio-{{ $meio['id'] }}"
                                    wire:click="selectPagamentoRow({{ $index }})"
                                    id="erp-fv-finalizar-row-{{ $index }}"
                                    data-icone="{{ $icone }}"
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
                                        <span class="erp-pdv-finalizar__valor-wrap">
                                            <span class="erp-pdv-finalizar__valor-rs">R$</span>
                                            <input
                                                id="erp-fv-finalizar-valor-{{ $index }}"
                                                type="text"
                                                wire:model.blur="meiosPagamento.{{ $index }}.valor"
                                                wire:focus="selectPagamentoRow({{ $index }})"
                                                wire:keydown.enter.prevent="confirmarValorPagamentoLinha($event.target.value)"
                                                class="erp-pdv-finalizar__grid-input"
                                                data-mask="money-br"
                                                inputmode="decimal"
                                                autocomplete="off"
                                            >
                                        </span>
                                    </td>
                                    <td
                                        class="erp-pdv__grid-col-center erp-pdv-finalizar__atalho"
                                        wire:click.stop="selectPagamentoByAtalho('{{ $meio['atalho'] }}')"
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
                                <span class="erp-pdv-finalizar__money-value">{{ $this->formatMoney($restante) }}</span>
                            </span>
                        </label>
                        <label class="erp-pdv-finalizar__field erp-pdv-finalizar__field--total erp-pdv-finalizar__field--troco">
                            <span class="erp-pdv-finalizar__label">Troco:</span>
                            <span class="erp-pdv-finalizar__money" aria-live="polite">
                                <span class="erp-pdv-finalizar__money-rs">R$</span>
                                <span class="erp-pdv-finalizar__money-value">{{ $this->formatMoney($troco) }}</span>
                            </span>
                        </label>
                    </div>
                </aside>
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

        <footer class="erp-pdv-modal__footer erp-pdv-finalizar__footer-actions erp-fv-fin__footer">
            <div class="erp-pdv-finalizar__operacao-botoes">
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--fiscal"
                    disabled
                    title="Em breve"
                    id="erp-fv-finalizar-op-nfce"
                >
                    <kbd>F4</kbd>
                    <span>NFCe Online Transmitir</span>
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--pedido"
                    wire:click="confirmarPedido"
                    wire:loading.attr="disabled"
                    @disabled($this->gravando)
                    id="erp-fv-finalizar-op-pedido"
                >
                    <kbd>F5</kbd>
                    <span wire:loading.remove wire:target="confirmarPedido">Pedido</span>
                    <span wire:loading wire:target="confirmarPedido">Gravando…</span>
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--fiscal"
                    disabled
                    title="Em breve"
                    id="erp-fv-finalizar-op-nfe"
                >
                    <kbd>F6</kbd>
                    <span>NFe</span>
                </button>
                <button
                    type="button"
                    class="erp-pdv-modal__btn erp-pdv-finalizar__operacao-btn erp-pdv-finalizar__operacao-btn--faturar"
                    wire:click="faturarPedido"
                    wire:loading.attr="disabled"
                    @disabled($this->gravando)
                    id="erp-fv-finalizar-op-faturar"
                >
                    <kbd>F8</kbd>
                    <span wire:loading.remove wire:target="faturarPedido">Faturar</span>
                    <span wire:loading wire:target="faturarPedido">Faturando…</span>
                </button>
            </div>
            <button type="button" wire:click="voltarParaVenda" class="erp-pdv-modal__btn erp-pdv-modal__btn--danger">
                <kbd>Esc</kbd> Cancelar
            </button>
        </footer>
    </div>
</div>
