@php
    $status = mb_strtoupper((string) ($this->lancamentoModalStatus ?: '—'), 'UTF-8');
    $isFechada = $status === 'FECHADA';
    $isCancelada = $status === 'CANCELADA';
    $isAlterando = $status === 'ALTERANDO';
    $canFinalize = $isAlterando || (! $isFechada && ! $isCancelada);
@endphp

@if ($this->entradaRomaneioFornecedorModalOpen)
    <div class="erp-lookup-modal erp-compras-lancamento-modal">
        <div class="erp-lookup-modal__backdrop" wire:click="cancelarEntradaRomaneio"></div>
        <div class="erp-lookup-modal__window erp-compras-lancamento-modal__window erp-compras-lancamento-modal__window--romaneio-fornecedor" role="dialog" aria-modal="true" wire:click.stop>
            <div class="erp-lookup-modal__titlebar erp-compras-lancamento-modal__titlebar">
                <span>Entrada por Romaneio</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="cancelarEntradaRomaneio" title="Fechar">✕</button>
            </div>
            <div class="erp-lookup-modal__body erp-compras-lancamento-modal__body">
                <p class="erp-compras-lancamento-modal__romaneio-intro">Selecione o fornecedor para iniciar a entrada manual.</p>
                <input
                    class="erp-compras-lancamento-modal__romaneio-start-search"
                    type="search"
                    autofocus
                    wire:model.live.debounce.250ms="entradaRomaneioFornecedorBusca"
                    placeholder="Digite ao menos 2 letras do fornecedor"
                    autocomplete="off"
                >
                <div class="erp-compras-lancamento-modal__romaneio-start-results">
                    @forelse ($this->entradaRomaneioFornecedores as $fornecedor)
                        <button type="button" wire:click="iniciarEntradaRomaneio({{ $fornecedor['id'] }})">
                            {{ $fornecedor['label'] }}
                        </button>
                    @empty
                        @if (mb_strlen(trim($this->entradaRomaneioFornecedorBusca)) >= 2)
                            <div>Nenhum fornecedor encontrado.</div>
                        @else
                            <div>Comece digitando o nome do fornecedor.</div>
                        @endif
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif

@if ($this->lancamentoModalOpen)
    <div
        class="erp-lookup-modal erp-compras-lancamento-modal"
        x-on:erp-compra-romaneio-focus-qtd.window="
            const input = document.getElementById('erp-compra-romaneio-qtd');
            input?.removeAttribute('readonly');
            input?.focus();
            input?.select();
        "
        x-on:erp-compra-romaneio-focus-valor.window="
            const input = document.getElementById('erp-compra-romaneio-valor');
            input?.removeAttribute('readonly');
            input?.focus();
            input?.select();
        "
        x-on:erp-compra-romaneio-focus-produto.window="
            const input = document.getElementById('erp-compra-romaneio-produto');
            input?.removeAttribute('readonly');
            input?.focus();
        "
        @if (! $this->productPrecificacaoOpen)
            wire:keydown.escape.window="closeCompraLancamento"
        @endif
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeCompraLancamento"></div>

        <div
            class="erp-lookup-modal__window erp-compras-lancamento-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-compras-lancamento-title"
            wire:click.stop
        >
            <div class="erp-lookup-modal__titlebar erp-compras-lancamento-modal__titlebar">
                <span id="erp-compras-lancamento-title">Lançamento de Compras</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeCompraLancamento"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-compras-lancamento-modal__body">
                <section class="erp-compras-lancamento-modal__panel erp-compras-lancamento-modal__panel--topo">
                    <div class="erp-compras-lancamento-modal__toolbar">
                        <div class="erp-compras-lancamento-modal__tool-group">
                            <button
                                type="button"
                                class="erp-compras-lancamento-modal__tool-btn erp-compras-lancamento-modal__tool-btn--ok"
                                wire:click="finalizarCompraLancamento"
                                wire:loading.attr="disabled"
                                @disabled(! $canFinalize)
                                title="{{ $canFinalize ? 'Finalizar alteração' : 'Compra já finalizada' }}"
                            >
                                <span class="erp-compras-lancamento-modal__tool-icon">✓</span>
                                <span>Finalizar</span>
                            </button>
                            @if ($this->lancamentoModalCompraId)
                                <button
                                    type="button"
                                    wire:click.stop="printCompraDanfe"
                                    data-erp-print-nota
                                    class="erp-compras-lancamento-modal__tool-btn"
                                    title="Imprimir DANFE"
                                >
                                    <span class="erp-compras-lancamento-modal__tool-icon">🖨</span>
                                    <span>DANFE</span>
                                </button>
                            @endif
                            <button
                                type="button"
                                class="erp-compras-lancamento-modal__tool-btn erp-compras-lancamento-modal__tool-btn--exit"
                                wire:click="closeCompraLancamento"
                                title="Sair"
                            >
                                <span class="erp-compras-lancamento-modal__tool-icon">✕</span>
                                <span>Sair</span>
                            </button>
                        </div>

                        <div class="erp-compras-lancamento-modal__fields">
                            <div class="erp-compras-lancamento-modal__fields-row">
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--xs">
                                    <span>Número</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['numero'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--empresa">
                                    <span>Empresa</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['empresa'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--grow">
                                    <div class="erp-compras-lancamento-modal__field-label-row">
                                        <span>Fornecedor</span>
                                        <span @class([
                                            'erp-compras-lancamento-modal__status-badge',
                                            'is-fechada' => $isFechada,
                                            'is-cancelada' => $isCancelada,
                                            'is-alterando' => $isAlterando,
                                            'is-aberta' => ! $isFechada && ! $isCancelada && ! $isAlterando,
                                        ])>{{ $status }}</span>
                                    </div>
                                    <div class="erp-compras-lancamento-modal__info" title="{{ $this->lancamentoModalHeader['fornecedor'] ?? '' }}">{{ $this->lancamentoModalHeader['fornecedor'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--uf">
                                    <span>UF</span>
                                    <div class="erp-compras-lancamento-modal__info erp-compras-lancamento-modal__info--center">{{ $this->lancamentoModalHeader['uf'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--doc">
                                    <span>CNPJ</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['cnpj'] ?? '—' }}</div>
                                </div>
                            </div>

                            <div class="erp-compras-lancamento-modal__fields-row">
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--chave">
                                    <span>Chave NFe</span>
                                    <div class="erp-compras-lancamento-modal__info erp-compras-lancamento-modal__info--mono" title="{{ $this->lancamentoModalHeader['chave'] ?? '' }}">{{ $this->lancamentoModalHeader['chave'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--sm">
                                    <span>Nota</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['nota'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--xs">
                                    <span>Modelo</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['modelo'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--xs">
                                    <span>Série</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['serie'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--date">
                                    <span>Dt. Emissão</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['data_emissao'] ?? '—' }}</div>
                                </div>
                                <div class="erp-compras-lancamento-modal__field erp-compras-lancamento-modal__field--date">
                                    <span>Dt. Entrada</span>
                                    <div class="erp-compras-lancamento-modal__info">{{ $this->lancamentoModalHeader['data_entrada'] ?? '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="erp-compras-lancamento-modal__panel erp-compras-lancamento-modal__panel--itens">
                    @if ($this->lancamentoRomaneio)
                        <div class="erp-compras-lancamento-modal__romaneio-bar">
                            <div class="erp-compras-lancamento-modal__romaneio-group">
                                <label for="erp-compra-romaneio-fornecedor">Fornecedor</label>
                                <input
                                    id="erp-compra-romaneio-fornecedor"
                                    type="search"
                                    wire:model.live.debounce.250ms="lancamentoRomaneioFornecedorBusca"
                                    placeholder="Digite o nome do fornecedor"
                                    autocomplete="off"
                                >
                                @if ($this->lancamentoRomaneioFornecedores !== [])
                                    <div class="erp-compras-lancamento-modal__romaneio-lookup">
                                        @foreach ($this->lancamentoRomaneioFornecedores as $fornecedor)
                                            <button
                                                type="button"
                                                wire:click="selectLancamentoRomaneioFornecedor({{ $fornecedor['id'] }})"
                                            >{{ $fornecedor['label'] }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <section class="erp-nfe-inclusao erp-compras-inclusao" @if ($this->lancamentoRomaneioProdutos !== []) data-lookup-open="1" @endif>
                                <div class="erp-nfe-inclusao__box">
                                    <span class="erp-nfe-inclusao__legend">Produto</span>
                                    <div class="erp-nfe-inclusao__row">
                                        <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--barcode">
                                            <span>Código / barras / nome</span>
                                            <div class="erp-nfe-inclusao__barcode-wrap">
                                                <input
                                                    id="erp-compra-romaneio-produto"
                                                    class="erp-nfe-inclusao__input erp-nfe-inclusao__input--barcode"
                                                    type="text"
                                                    wire:model.live.debounce.200ms="lancamentoRomaneioProdutoBusca"
                                                    wire:keydown.enter.prevent="confirmarLancamentoRomaneioProduto($event.target.value)"
                                                    wire:keydown.arrow-up.prevent="moveLancamentoRomaneioProdutoSelecionado(-1)"
                                                    wire:keydown.arrow-down.prevent="moveLancamentoRomaneioProdutoSelecionado(1)"
                                                    data-erp-uppercase
                                                    autocomplete="off"
                                                    placeholder="Código exato, barras ou nome — Enter"
                                                    role="combobox"
                                                    aria-expanded="{{ $this->lancamentoRomaneioProdutos !== [] ? 'true' : 'false' }}"
                                                >
                                                @if ($this->lancamentoRomaneioProdutos !== [])
                                                    <div class="erp-nfe-inclusao__suggest-wrap">
                                                        <ul class="erp-nfe-inclusao__suggest" role="listbox" aria-label="Produtos encontrados">
                                                            @foreach ($this->lancamentoRomaneioProdutos as $index => $produto)
                                                                <li wire:key="compra-romaneio-produto-{{ $produto['id'] }}">
                                                                    <button
                                                                        type="button"
                                                                        wire:click="selectLancamentoRomaneioProduto({{ $produto['id'] }})"
                                                                        @class(['is-selected' => $this->lancamentoRomaneioProdutoSelecionado === $index])
                                                                    >
                                                                        <span class="erp-nfe-inclusao__suggest-code">{{ $produto['codigo'] ?: '—' }}</span>
                                                                        <span class="erp-nfe-inclusao__suggest-nome">{{ $produto['descricao'] }}</span>
                                                                        <span class="erp-nfe-inclusao__suggest-preco">R$ {{ number_format($produto['preco_compra'] ?? 0, 2, ',', '.') }}</span>
                                                                    </button>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        </label>
                                        <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--qtd">
                                            <span>Qtde</span>
                                            <input id="erp-compra-romaneio-qtd" class="erp-nfe-inclusao__input" type="text" wire:model.live.debounce.200ms="lancamentoRomaneioQtd" wire:keydown.enter.prevent="focarLancamentoRomaneioValorAposQtd($event.target.value)" data-mask="quantity3" autocomplete="off">
                                        </label>
                                        <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--preco">
                                            <span>Vlr. compra</span>
                                            <input id="erp-compra-romaneio-valor" class="erp-nfe-inclusao__input" type="text" wire:model.live.debounce.200ms="lancamentoRomaneioValorUnitario" wire:keydown.enter.prevent="confirmarLancamentoRomaneioValor($event.target.value)" data-mask="money-br" autocomplete="off">
                                        </label>
                                        <label class="erp-nfe-inclusao__field erp-nfe-inclusao__field--total">
                                            <span>Total item</span>
                                            <input class="erp-nfe-inclusao__input erp-nfe-inclusao__input--total" type="text" value="{{ $this->lancamentoRomaneioTotalItem }}" readonly tabindex="-1">
                                        </label>
                                    </div>
                                </div>
                            </section>
                        </div>
                    @endif

                    <div class="erp-compras-lancamento-modal__main">
                        <div
                            class="erp-compras-lancamento-modal__grid-wrap"
                            x-data="{
                                marcarLinha(tr) {
                                    if (! tr) return;
                                    const body = tr.closest('tbody');
                                    if (! body) return;
                                    body.querySelectorAll('.erp-compras-lancamento-modal__row--selected')
                                        .forEach((r) => r.classList.remove('erp-compras-lancamento-modal__row--selected'));
                                    tr.classList.add('erp-compras-lancamento-modal__row--selected');
                                }
                            }"
                        >
                            <table class="erp-compras-lancamento-modal__grid">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Código</th>
                                        <th>Referência</th>
                                        <th>Produto</th>
                                        <th>Qtd.Compra</th>
                                        <th title="Valor cheio da nota">Preço. Comp.</th>
                                        <th>V. Custo</th>
                                        <th title="Margem % sobre o V. Custo">Mg%</th>
                                        <th title="Preço varejo">Varejo</th>
                                        <th title="Margem % sobre o Varejo (não sobre o custo)">Mg%</th>
                                        <th title="Preço atacado">Atacado</th>
                                        <th title="Margem % sobre o Varejo (não sobre o custo)">Mg%</th>
                                        <th title="Preço especial">Especial</th>
                                        <th title="Precificar">%</th>
                                    </tr>
                                </thead>
                                <tbody wire:key="lanc-grid-body-{{ $this->lancamentoGridEpoch }}">
                                    @forelse ($this->lancamentoModalRows as $index => $row)
                                        <tr
                                            wire:key="lancamento-row-{{ $index }}"
                                            class="erp-compras-lancamento-modal__row{{ $this->lancamentoModalItemIndex === $index ? ' erp-compras-lancamento-modal__row--selected' : '' }}"
                                            x-on:click="
                                                marcarLinha($el);
                                                $wire.selectLancamentoItem({{ $index }});
                                            "
                                        >
                                            <td>
                                                <div class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--center">
                                                    {{ $row['item'] }}
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--codigo"
                                                    title="{{ $row['codigo'] }}"
                                                >{{ $row['codigo'] }}</div>
                                            </td>
                                            <td>
                                                <div
                                                    class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--center"
                                                    title="{{ $row['referencia'] !== '' ? $row['referencia'] : '—' }}"
                                                >{{ $row['referencia'] !== '' ? $row['referencia'] : '—' }}</div>
                                            </td>
                                            <td>
                                                <div
                                                    class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--desc"
                                                    title="{{ $row['produto'] }}"
                                                >{{ $row['produto'] }}</div>
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <input
                                                        type="text"
                                                        wire:key="lancamento-qtd-{{ $index }}-e{{ $this->lancamentoGridEpoch }}"
                                                        value="{{ $row['qtd'] }}"
                                                        wire:click.stop
                                                        data-erp-lanc-enter="qtd"
                                                        data-row-index="{{ $index }}"
                                                        data-mask="quantity3"
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num"
                                                        autocomplete="off"
                                                        title="Quantidade de compra — Enter recalcula V. Custo"
                                                        x-on:focus="
                                                            $el.removeAttribute('readonly');
                                                            marcarLinha($el.closest('tr'));
                                                            if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                $wire.selectLancamentoItem({{ $index }});
                                                            }
                                                            $el.select();
                                                        "
                                                        x-on:click.stop="$el.select()"
                                                        x-on:keydown.enter.capture.prevent="
                                                            $el.removeAttribute('readonly');
                                                            window.__erpLancFocusUntil = Date.now() + 3000;
                                                            $wire.lancamentoGridEnter({{ $index }}, 'qtd', $el.value);
                                                        "
                                                        x-on:blur="
                                                            if (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating()) {
                                                                return;
                                                            }
                                                            $wire.commitQtdAndGoNext({{ $index }}, $el.value);
                                                        "
                                                        @mouseup.prevent
                                                    >
                                                @else
                                                    <div class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--num">
                                                        {{ $row['qtd'] }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <div
                                                    class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__money"
                                                    title="Valor cheio da nota"
                                                >
                                                    <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                    <span class="erp-compras-lancamento-modal__money-val">{{ $row['preco'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__money"
                                                    title="V. Custo = Preço ÷ Qtd.Compra"
                                                    data-erp-lanc-custo="{{ $row['vl_custo'] }}"
                                                >
                                                    <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                    <span class="erp-compras-lancamento-modal__money-val">{{ $row['vl_custo'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <input
                                                        type="text"
                                                        wire:key="lancamento-mg-venda-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['margem_varejo'] ?? '0' }}"
                                                        value="{{ $row['margem_varejo'] ?? $this->lancamentoMargemPctLabel($row['preco_venda'] ?? 0, $row['vl_custo'] ?? 0) }}"
                                                        wire:click.stop
                                                        data-erp-lanc-enter="mg_venda"
                                                        data-row-index="{{ $index }}"
                                                        wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'mg_venda', $event.target.value)"
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num"
                                                        autocomplete="off"
                                                        data-mask="percent-br"
                                                        title="Margem % varejo — editar recalcula o Varejo"
                                                        x-on:focus="
                                                            marcarLinha($el.closest('tr'));
                                                            if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                $wire.selectLancamentoItem({{ $index }});
                                                            }
                                                            $el.select();
                                                        "
                                                        x-on:click.stop="$el.select()"
                                                        @mouseup.prevent
                                                    >
                                                @else
                                                    <div
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--num"
                                                        title="Margem % = (Varejo ÷ V. Custo × 100) − 100"
                                                    >{{ $row['margem_varejo'] ?? $this->lancamentoMargemPctLabel($row['preco_venda'] ?? 0, $row['vl_custo'] ?? 0) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <div class="erp-compras-lancamento-modal__money erp-compras-lancamento-modal__money--field">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <input
                                                            type="text"
                                                            wire:key="lancamento-venda-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['preco_venda'] ?? '0' }}"
                                                            value="{{ $row['preco_venda'] }}"
                                                            wire:click.stop
                                                            data-erp-lanc-enter="venda"
                                                            data-row-index="{{ $index }}"
                                                            wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'venda', $event.target.value)"
                                                            class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num erp-compras-lancamento-modal__money-input"
                                                            autocomplete="off"
                                                            data-mask="money-br"
                                                            title="Preço varejo"
                                                            x-on:focus="
                                                                marcarLinha($el.closest('tr'));
                                                                if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                    $wire.selectLancamentoItem({{ $index }});
                                                                }
                                                                $el.select();
                                                            "
                                                            x-on:click.stop="$el.select()"
                                                            @mouseup.prevent
                                                        >
                                                    </div>
                                                @else
                                                    <div class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__money">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <span class="erp-compras-lancamento-modal__money-val">{{ $row['preco_venda'] }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <input
                                                        type="text"
                                                        wire:key="lancamento-mg-atacado-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['margem_atacado'] ?? '0' }}"
                                                        value="{{ $row['margem_atacado'] ?? '0,00' }}"
                                                        wire:click.stop
                                                        data-erp-lanc-enter="mg_atacado"
                                                        data-row-index="{{ $index }}"
                                                        wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'mg_atacado', $event.target.value)"
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num"
                                                        autocomplete="off"
                                                        data-mask="percent-br"
                                                        title="Margem % atacado sobre o Varejo — 0% = mesmo preço do Varejo"
                                                        x-on:focus="
                                                            marcarLinha($el.closest('tr'));
                                                            if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                $wire.selectLancamentoItem({{ $index }});
                                                            }
                                                            $el.select();
                                                        "
                                                        x-on:click.stop="$el.select()"
                                                        @mouseup.prevent
                                                    >
                                                @else
                                                    <div
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--num"
                                                        title="Margem % = (Atacado ÷ Varejo × 100) − 100"
                                                    >{{ $row['margem_atacado'] ?? $this->lancamentoMargemPctLabel($row['preco_atacado'] ?? 0, $row['vl_custo'] ?? 0) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <div class="erp-compras-lancamento-modal__money erp-compras-lancamento-modal__money--field">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <input
                                                            type="text"
                                                            wire:key="lancamento-atacado-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['preco_atacado'] ?? '0' }}"
                                                            value="{{ $row['preco_atacado'] ?? '0,00' }}"
                                                            wire:click.stop
                                                            data-erp-lanc-enter="atacado"
                                                            data-row-index="{{ $index }}"
                                                            wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'atacado', $event.target.value)"
                                                            class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num erp-compras-lancamento-modal__money-input"
                                                            autocomplete="off"
                                                            data-mask="money-br"
                                                            title="Preço atacado"
                                                            x-on:focus="
                                                                marcarLinha($el.closest('tr'));
                                                                if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                    $wire.selectLancamentoItem({{ $index }});
                                                                }
                                                                $el.select();
                                                            "
                                                            x-on:click.stop="$el.select()"
                                                            @mouseup.prevent
                                                        >
                                                    </div>
                                                @else
                                                    <div class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__money">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <span class="erp-compras-lancamento-modal__money-val">{{ $row['preco_atacado'] ?? '0,00' }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <input
                                                        type="text"
                                                        wire:key="lancamento-mg-especial-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['margem_especial'] ?? '0' }}"
                                                        value="{{ $row['margem_especial'] ?? '0,00' }}"
                                                        wire:click.stop
                                                        data-erp-lanc-enter="mg_especial"
                                                        data-row-index="{{ $index }}"
                                                        wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'mg_especial', $event.target.value)"
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num"
                                                        autocomplete="off"
                                                        data-mask="percent-br"
                                                        title="Margem % especial sobre o Varejo — 0% = mesmo preço do Varejo"
                                                        x-on:focus="
                                                            marcarLinha($el.closest('tr'));
                                                            if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                $wire.selectLancamentoItem({{ $index }});
                                                            }
                                                            $el.select();
                                                        "
                                                        x-on:click.stop="$el.select()"
                                                        @mouseup.prevent
                                                    >
                                                @else
                                                    <div
                                                        class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__cell-input--num"
                                                        title="Margem % = (Especial ÷ Varejo × 100) − 100"
                                                    >{{ $row['margem_especial'] ?? $this->lancamentoMargemPctLabel($row['preco_especial'] ?? 0, $row['vl_custo'] ?? 0) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($canFinalize)
                                                    <div class="erp-compras-lancamento-modal__money erp-compras-lancamento-modal__money--field">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <input
                                                            type="text"
                                                            wire:key="lancamento-especial-{{ $index }}-r{{ $this->lancamentoGridRevision }}-{{ $row['preco_especial'] ?? '0' }}"
                                                            value="{{ $row['preco_especial'] ?? '0,00' }}"
                                                            wire:click.stop
                                                            data-erp-lanc-enter="especial"
                                                            data-row-index="{{ $index }}"
                                                            wire:keydown.enter.prevent="lancamentoGridEnter({{ $index }}, 'especial', $event.target.value)"
                                                            class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--num erp-compras-lancamento-modal__money-input"
                                                            autocomplete="off"
                                                            data-mask="money-br"
                                                            title="Preço especial"
                                                            x-on:focus="
                                                                marcarLinha($el.closest('tr'));
                                                                if (! (window.ErpComprasLancEnter && window.ErpComprasLancEnter.isNavigating && window.ErpComprasLancEnter.isNavigating())) {
                                                                    $wire.selectLancamentoItem({{ $index }});
                                                                }
                                                                $el.select();
                                                            "
                                                            x-on:click.stop="$el.select()"
                                                            @mouseup.prevent
                                                        >
                                                    </div>
                                                @else
                                                    <div class="erp-compras-lancamento-modal__cell-input erp-compras-lancamento-modal__cell-input--readonly erp-compras-lancamento-modal__money">
                                                        <span class="erp-compras-lancamento-modal__money-rs">R$</span>
                                                        <span class="erp-compras-lancamento-modal__money-val">{{ $row['preco_especial'] ?? '0,00' }}</span>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="erp-compras-lancamento-modal__pct-btn"
                                                    title="Precificar produto"
                                                    wire:click.stop="openLancamentoPrecificacao({{ $index }})"
                                                    @disabled(! $canFinalize)
                                                >%</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="14" class="erp-compras-lancamento-modal__empty">Nenhum item encontrado para esta compra.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="erp-compras-lancamento-modal__panel erp-compras-lancamento-modal__panel--rodape">
                    <div class="erp-compras-lancamento-modal__rodape-grid">
                        <div class="erp-compras-lancamento-modal__margens">
                            <div class="erp-compras-lancamento-modal__margens-grid">
                                <div class="erp-compras-lancamento-modal__box erp-compras-lancamento-modal__box--varejo">
                                    <h3>Margem Varejo</h3>
                                    <div class="erp-compras-lancamento-modal__margem-row">
                                        <input
                                            type="text"
                                            wire:model.blur="lancamentoMargemPercentVarejo"
                                            inputmode="decimal"
                                            data-mask="percent-br"
                                            placeholder="0,00"
                                            @disabled(! $canFinalize)
                                        >
                                        <span>%</span>
                                        <div class="erp-compras-lancamento-modal__radio-row erp-compras-lancamento-modal__radio-row--inline">
                                            <span>Em:</span>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="item">
                                                Item
                                            </label>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="todos">
                                                Todos
                                            </label>
                                        </div>
                                    </div>
                                    <p class="erp-compras-lancamento-modal__formula">
                                        Compra <strong>{{ $this->lancamentoModalValorCompra }}</strong>
                                        + Margem <strong>{{ $this->lancamentoModalValorMargemVarejo }}%</strong>
                                        = Varejo <strong>{{ $this->lancamentoModalValorVarejo }}</strong>
                                    </p>
                                    <button
                                        type="button"
                                        class="erp-compras-lancamento-modal__margem-btn"
                                        wire:click="aplicarLancamentoMargem('varejo')"
                                        title="Aplicar margem varejo"
                                        @disabled(! $canFinalize)
                                    >Aplicar Varejo</button>
                                </div>

                                <div class="erp-compras-lancamento-modal__box erp-compras-lancamento-modal__box--atacado">
                                    <h3>Margem Atacado</h3>
                                    <div class="erp-compras-lancamento-modal__margem-row">
                                        <input
                                            type="text"
                                            wire:model.blur="lancamentoMargemPercentAtacado"
                                            inputmode="decimal"
                                            data-mask="percent-br"
                                            placeholder="0,00"
                                            @disabled(! $canFinalize)
                                        >
                                        <span>%</span>
                                        <div class="erp-compras-lancamento-modal__radio-row erp-compras-lancamento-modal__radio-row--inline">
                                            <span>Em:</span>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="item">
                                                Item
                                            </label>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="todos">
                                                Todos
                                            </label>
                                        </div>
                                    </div>
                                    <p class="erp-compras-lancamento-modal__formula">
                                        Varejo <strong>{{ $this->lancamentoModalValorVarejo }}</strong>
                                        + Margem <strong>{{ $this->lancamentoModalValorMargemAtacado }}%</strong>
                                        = Atacado <strong>{{ $this->lancamentoModalValorAtacado }}</strong>
                                    </p>
                                    <button
                                        type="button"
                                        class="erp-compras-lancamento-modal__margem-btn"
                                        wire:click="aplicarLancamentoMargem('atacado')"
                                        title="Aplicar margem atacado"
                                        @disabled(! $canFinalize)
                                    >Aplicar Atacado</button>
                                </div>

                                <div class="erp-compras-lancamento-modal__box erp-compras-lancamento-modal__box--especial">
                                    <h3>Margem Especial</h3>
                                    <div class="erp-compras-lancamento-modal__margem-row">
                                        <input
                                            type="text"
                                            wire:model.blur="lancamentoMargemPercentEspecial"
                                            inputmode="decimal"
                                            data-mask="percent-br"
                                            placeholder="0,00"
                                            @disabled(! $canFinalize)
                                        >
                                        <span>%</span>
                                        <div class="erp-compras-lancamento-modal__radio-row erp-compras-lancamento-modal__radio-row--inline">
                                            <span>Em:</span>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="item">
                                                Item
                                            </label>
                                            <label>
                                                <input type="radio" wire:model.live="lancamentoMargemEscopo" value="todos">
                                                Todos
                                            </label>
                                        </div>
                                    </div>
                                    <p class="erp-compras-lancamento-modal__formula">
                                        Varejo <strong>{{ $this->lancamentoModalValorVarejo }}</strong>
                                        + Margem <strong>{{ $this->lancamentoModalValorMargemEspecial }}%</strong>
                                        = Especial <strong>{{ $this->lancamentoModalValorEspecial }}</strong>
                                    </p>
                                    <button
                                        type="button"
                                        class="erp-compras-lancamento-modal__margem-btn"
                                        wire:click="aplicarLancamentoMargem('especial')"
                                        title="Aplicar margem especial"
                                        @disabled(! $canFinalize)
                                    >Aplicar Especial</button>
                                </div>
                            </div>
                        </div>

                        <div class="erp-compras-lancamento-modal__box">
                            <h3>Parâmetros</h3>
                            <label class="erp-compras-lancamento-modal__check">
                                <input type="checkbox" wire:model.live="lancamentoParamAjustaPreco">
                                Ajusta Preço de Venda
                            </label>
                            <label class="erp-compras-lancamento-modal__check">
                                <input type="checkbox" wire:model.live="lancamentoParamGerarFinanceiro">
                                Gerar Financeiro
                            </label>
                            <label class="erp-compras-lancamento-modal__check">
                                <input type="checkbox" wire:model.live="lancamentoParamGeraEstoque">
                                Gera Estoque
                            </label>
                        </div>

                        <div class="erp-compras-lancamento-modal__box erp-compras-lancamento-modal__box--totais">
                            <h3>Totais</h3>
                            @php
                                $totais = $this->lancamentoModalTotais;
                                $totaisLabels = [
                                    ['key' => 'subtotal', 'label' => 'SubTotal'],
                                    ['key' => 'frete', 'label' => 'Frete'],
                                    ['key' => 'seguro', 'label' => 'Seguro'],
                                    ['key' => 'outras', 'label' => 'Outras'],
                                    ['key' => 'desconto', 'label' => 'Desconto'],
                                    ['key' => 'base_icms', 'label' => 'Base ICMS'],
                                    ['key' => 'valor_icms', 'label' => 'Valor ICMS'],
                                    ['key' => 'base_ipi', 'label' => 'Base IPI'],
                                    ['key' => 'valor_ipi', 'label' => 'Valor IPI'],
                                    ['key' => 'base_pis', 'label' => 'Base PIS'],
                                    ['key' => 'valor_pis', 'label' => 'Valor PIS'],
                                    ['key' => 'base_cofins', 'label' => 'Base Cofins'],
                                    ['key' => 'valor_cofins', 'label' => 'Valor Cofins'],
                                    ['key' => 'base_st', 'label' => 'Base ST'],
                                    ['key' => 'valor_st', 'label' => 'Valor ST'],
                                    ['key' => 'total', 'label' => 'Total'],
                                ];
                            @endphp
                            <div class="erp-compras-lancamento-modal__totais-grid">
                                @foreach ($totaisLabels as $item)
                                    @php
                                        $isExtraEditavel = in_array($item['key'], ['frete', 'seguro', 'outras'], true);
                                        $canEditExtra = $isExtraEditavel && $canFinalize;
                                    @endphp
                                    <label class="erp-compras-lancamento-modal__total-field">
                                        <span>{{ $item['label'] }}</span>
                                        @if ($canEditExtra)
                                            <input
                                                type="text"
                                                inputmode="decimal"
                                                value="{{ $totais[$item['key']] ?? '0,00' }}"
                                                class="is-editable"
                                                data-erp-lanc-totais="{{ $item['key'] }}"
                                                wire:keydown.enter.prevent="lancamentoTotaisEnter('{{ $item['key'] }}', $event.target.value)"
                                                wire:blur="lancamentoTotaisBlur('{{ $item['key'] }}', $event.target.value)"
                                                autocomplete="off"
                                                title="Rateia no V. Custo (preço de compra) de todos os itens"
                                            >
                                        @else
                                            <input
                                                type="text"
                                                readonly
                                                tabindex="-1"
                                                value="{{ $totais[$item['key']] ?? '0,00' }}"
                                                @class(['is-total' => $item['key'] === 'total'])
                                            >
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endif

<script>
    (function () {
        if (window.__erpLancGridEnterV4) {
            return;
        }
        window.__erpLancGridEnterV4 = true;
        window.__erpLancFocusUntil = 0;

        function findInput(modal, col, index) {
            return modal.querySelector(
                'input[data-erp-lanc-enter="' + col + '"][data-row-index="' + String(index) + '"]'
            );
        }

        function proximo(modal, col, rowIndex) {
            const existe = (c, i) => !! findInput(modal, c, i);
            if (col === 'qtd') return { col: 'mg_venda', index: rowIndex };
            if (col === 'mg_venda') return { col: 'venda', index: rowIndex };
            if (col === 'venda') {
                if (existe('mg_venda', rowIndex + 1)) return { col: 'mg_venda', index: rowIndex + 1 };
                return existe('mg_atacado', 0) ? { col: 'mg_atacado', index: 0 } : null;
            }
            if (col === 'mg_atacado') return { col: 'atacado', index: rowIndex };
            if (col === 'atacado') {
                if (existe('mg_atacado', rowIndex + 1)) return { col: 'mg_atacado', index: rowIndex + 1 };
                return existe('mg_especial', 0) ? { col: 'mg_especial', index: 0 } : null;
            }
            if (col === 'mg_especial') return { col: 'especial', index: rowIndex };
            if (col === 'especial') {
                return existe('mg_especial', rowIndex + 1) ? { col: 'mg_especial', index: rowIndex + 1 } : null;
            }
            return null;
        }

        function focar(modal, col, index) {
            const input = findInput(modal, col, index);
            if (! input || input.disabled) return;
            const tr = input.closest('tr');
            const body = tr && tr.closest('tbody');
            if (body) {
                body.querySelectorAll('.erp-compras-lancamento-modal__row--selected').forEach(function (r) {
                    r.classList.remove('erp-compras-lancamento-modal__row--selected');
                });
                tr.classList.add('erp-compras-lancamento-modal__row--selected');
            }
            try {
                input.focus({ preventScroll: true });
                input.select();
            } catch (e) {
                try { input.focus(); input.select(); } catch (e2) {}
            }
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== 'NumpadEnter') return;
            var el = event.target;
            if (! el || el.tagName !== 'INPUT') return;
            if (! el.getAttribute('data-erp-lanc-enter')) return;
            var modal = el.closest('.erp-compras-lancamento-modal');
            if (! modal) return;

            event.preventDefault();

            var col = el.getAttribute('data-erp-lanc-enter');
            var rowIndex = Number(el.getAttribute('data-row-index'));
            if (! col || isNaN(rowIndex)) return;

            window.__erpLancFocusUntil = Date.now() + 2000;

            if (window.ErpMasks && el.dataset && el.dataset.mask) {
                try {
                    el.value = window.ErpMasks.finalizeMaskValue(el);
                } catch (e) {}
            }

            var next = proximo(modal, col, rowIndex);
            if (next) {
                focar(modal, next.col, next.index);
                setTimeout(function () { focar(modal, next.col, next.index); }, 0);
                setTimeout(function () { focar(modal, next.col, next.index); }, 80);
            }
        }, true);

        window.ErpComprasLancEnter = window.ErpComprasLancEnter || {};
        window.ErpComprasLancEnter.isNavigating = function () {
            return Date.now() < (window.__erpLancFocusUntil || 0);
        };
        window.ErpComprasLancEnter.version = 'v4-inline';
    })();
</script>
