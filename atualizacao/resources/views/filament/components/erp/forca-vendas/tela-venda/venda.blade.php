<div class="erp-fv-tv__topbar">
    <div class="erp-fv-tv__topbar-left">
        <span class="erp-fv-tv__topbar-title">Tela de Venda</span>
        @if ($this->davNumero)
            <span class="erp-fv-tv__topbar-dav">DAV {{ $this->davNumero }}</span>
        @endif
        @if ($this->pedidoId)
            <span class="erp-fv-tv__topbar-badge">Editando</span>
        @endif
    </div>
    <span class="erp-fv-tv__topbar-meta">{{ $this->aberturaData }} · {{ $this->aberturaHora }}</span>
</div>

<div class="erp-fv-tv__oper">
    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--vendedor">
        <span>Vendedor</span>
        <div
            class="erp-fv-tv__combo"
            wire:key="fv-tv-vendedor-{{ $this->vendedorId ?? 0 }}"
            x-data="{
                open: false,
                ativo: 0,
                valor: @js($this->vendedorId),
                rotulo: @js($this->vendedorLabel !== '' ? $this->vendedorLabel : 'Selecione'),
                itens: @js(collect($this->vendedorOpcoes)->map(fn ($op) => ['id' => (int) $op['id'], 'nome' => $op['label']])->values()->all()),
                abrir() {
                    this.open = true;
                    const idx = this.itens.findIndex(o => o.id === this.valor);
                    this.ativo = idx >= 0 ? idx : 0;
                },
                mover(d) {
                    if (! this.open) { this.abrir(); return; }
                    const total = this.itens.length;
                    if (total === 0) return;
                    this.ativo = (this.ativo + d + total) % total;
                    this.$nextTick(() => this.$refs.panel?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
                },
                confirmar() {
                    const op = this.itens[this.ativo];
                    if (op) this.escolher(op.id, op.nome);
                },
                escolher(id, nome) {
                    this.valor = id;
                    this.rotulo = nome;
                    this.open = false;
                    $wire.set('vendedorId', id);
                },
            }"
            @click.outside="open = false"
            @keydown.escape.stop="open = false"
        >
            <button
                type="button"
                class="erp-fv-tv__combo-btn"
                @click="open ? open = false : abrir()"
                @keydown.arrow-down.prevent="mover(1)"
                @keydown.arrow-up.prevent="mover(-1)"
                @keydown.enter.prevent="open ? confirmar() : abrir()"
                :aria-expanded="open.toString()"
            >
                <span class="erp-fv-tv__combo-btn-label" x-text="rotulo"></span>
                <span class="erp-fv-tv__combo-btn-caret" aria-hidden="true">▾</span>
            </button>
            <div class="erp-fv-tv__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <template x-for="(op, i) in itens" :key="op.id">
                    <button
                        type="button"
                        class="erp-fv-tv__combo-item"
                        :class="{ 'is-active': i === ativo || op.id === valor }"
                        @mouseenter="ativo = i"
                        @click="escolher(op.id, op.nome)"
                        x-text="op.nome"
                    ></button>
                </template>
                <template x-if="itens.length === 0">
                    <div class="erp-fv-tv__combo-empty">Nenhum vendedor</div>
                </template>
            </div>
        </div>
    </label>

    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--caixa">
        <span>Caixa</span>
        <div
            class="erp-fv-tv__combo"
            wire:key="fv-tv-caixa-{{ $this->caixaId ?? 0 }}"
            x-data="{
                open: false,
                ativo: 0,
                valor: @js($this->caixaId),
                rotulo: @js($this->caixaLabel !== '' ? $this->caixaLabel : 'Não definido'),
                itens: @js(collect($this->caixaOpcoes)->map(fn ($op) => [
                    'id' => (int) $op['id'],
                    'nome' => $op['label'].(($op['situacao'] ?? '') === 'fechado' ? ' (fechado)' : ''),
                ])->values()->all()),
                abrir() {
                    this.open = true;
                    const idx = this.itens.findIndex(o => o.id === this.valor);
                    this.ativo = idx >= 0 ? idx : 0;
                },
                mover(d) {
                    if (! this.open) { this.abrir(); return; }
                    const total = this.itens.length;
                    if (total === 0) return;
                    this.ativo = (this.ativo + d + total) % total;
                    this.$nextTick(() => this.$refs.panel?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
                },
                confirmar() {
                    const op = this.itens[this.ativo];
                    if (op) this.escolher(op.id, op.nome);
                },
                escolher(id, nome) {
                    this.valor = id;
                    this.rotulo = nome;
                    this.open = false;
                    $wire.set('caixaId', id);
                },
            }"
            @click.outside="open = false"
            @keydown.escape.stop="open = false"
        >
            <button
                type="button"
                @class(['erp-fv-tv__combo-btn', 'is-warning' => ! $this->caixaId])
                @click="open ? open = false : abrir()"
                @keydown.arrow-down.prevent="mover(1)"
                @keydown.arrow-up.prevent="mover(-1)"
                @keydown.enter.prevent="open ? confirmar() : abrir()"
                :aria-expanded="open.toString()"
            >
                <span class="erp-fv-tv__combo-btn-label" x-text="rotulo"></span>
                <span class="erp-fv-tv__combo-btn-caret" aria-hidden="true">▾</span>
            </button>
            <div class="erp-fv-tv__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <template x-for="(op, i) in itens" :key="op.id">
                    <button
                        type="button"
                        class="erp-fv-tv__combo-item"
                        :class="{ 'is-active': i === ativo || op.id === valor }"
                        @mouseenter="ativo = i"
                        @click="escolher(op.id, op.nome)"
                        x-text="op.nome"
                    ></button>
                </template>
                <template x-if="itens.length === 0">
                    <div class="erp-fv-tv__combo-empty">Nenhum caixa</div>
                </template>
            </div>
        </div>
    </label>

    <div class="erp-fv-tv__oper-field erp-fv-tv__oper-field--estoque">
        <span>Estoque</span>
        <div @class(['erp-fv-tv__oper-static', 'is-warning' => ! $this->estoqueId])>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 9l9-6 9 6"/>
                <path d="M4 10v10h16V10"/>
                <path d="M9 20v-6h6v6"/>
            </svg>
            <span>{{ $this->estoqueLabel !== '' ? $this->estoqueLabel : 'Não definido' }}</span>
        </div>
    </div>

    <label class="erp-fv-tv__oper-field erp-fv-tv__oper-field--tabela">
        <span>Tabela</span>
        <div
            class="erp-fv-tv__combo"
            wire:key="fv-tv-tabela-{{ $this->tabelaPrecoId ?? 0 }}"
            x-data="{
                open: false,
                ativo: 0,
                valor: @js($this->tabelaPrecoId),
                rotulo: @js($this->tabelaPrecoLabel !== '' ? $this->tabelaPrecoLabel : 'Sem tabela'),
                itens: @js(collect($this->tabelaPrecoOpcoes)->map(fn ($op) => ['id' => (int) $op['id'], 'nome' => $op['label']])->values()->all()),
                abrir() {
                    this.open = true;
                    const idx = this.itens.findIndex(o => o.id === this.valor);
                    this.ativo = idx >= 0 ? idx : 0;
                },
                mover(d) {
                    if (! this.open) { this.abrir(); return; }
                    const total = this.itens.length;
                    if (total === 0) return;
                    this.ativo = (this.ativo + d + total) % total;
                    this.$nextTick(() => this.$refs.panel?.querySelector('.is-active')?.scrollIntoView({ block: 'nearest' }));
                },
                confirmar() {
                    const op = this.itens[this.ativo];
                    if (op) this.escolher(op.id, op.nome);
                },
                escolher(id, nome) {
                    this.valor = id;
                    this.rotulo = nome;
                    this.open = false;
                    this.$dispatch('fv-tv-atualizar-tabela', { id, nome });
                },
            }"
            @click.outside="open = false"
            @keydown.escape.stop="open = false"
        >
            <button
                type="button"
                class="erp-fv-tv__combo-btn"
                @click="open ? open = false : abrir()"
                @keydown.arrow-down.prevent="mover(1)"
                @keydown.arrow-up.prevent="mover(-1)"
                @keydown.enter.prevent="open ? confirmar() : abrir()"
                :aria-expanded="open.toString()"
            >
                <span class="erp-fv-tv__combo-btn-label" x-text="rotulo"></span>
                <span class="erp-fv-tv__combo-btn-caret" aria-hidden="true">▾</span>
            </button>
            <div class="erp-fv-tv__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity.duration.100ms>
                <template x-for="(op, i) in itens" :key="op.id">
                    <button
                        type="button"
                        class="erp-fv-tv__combo-item"
                        :class="{ 'is-active': i === ativo || op.id === valor }"
                        @mouseenter="ativo = i"
                        @click="escolher(op.id, op.nome)"
                        x-text="op.nome"
                    ></button>
                </template>
                <template x-if="itens.length === 0">
                    <div class="erp-fv-tv__combo-empty">Nenhuma tabela ativa</div>
                </template>
            </div>
        </div>
    </label>
</div>

<section class="erp-fv-tv__panel erp-fv-tv__panel--cliente">
    <div class="erp-fv-tv__box">
        <span class="erp-fv-tv__box-legend">Cliente</span>

        <div class="erp-fv-tv__row erp-fv-tv__row--primary">
            <label class="erp-fv-tv__field erp-fv-tv__field--cod">
                <span>Número</span>
                <input
                    class="erp-nfe__input"
                    type="text"
                    wire:model="clienteCodigo"
                    wire:keydown.enter.prevent="buscarClientePorCodigo"
                    autocomplete="off"
                >
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--grow erp-fv-tv__field--suggest">
                <span>Razão social ou CNPJ</span>
                <input
                    id="fv-tv-cliente-busca"
                    class="erp-nfe__input"
                    type="text"
                    wire:model.live.debounce.250ms="clienteBusca"
                    wire:keydown.enter.prevent="confirmarClienteEAvancar"
                    wire:keydown.escape.prevent="fecharSugestoesCliente"
                    x-on:keydown.arrow-down.prevent="$wire.moverSugestaoCliente(1)"
                    x-on:keydown.arrow-up.prevent="$wire.moverSugestaoCliente(-1)"
                    autocomplete="off"
                    placeholder="Buscar cliente…"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded="{{ $this->clienteSugestoesOpen && $this->clienteSugestoes !== [] ? 'true' : 'false' }}"
                    aria-controls="fv-tv-cliente-sugestoes"
                >
                @if ($this->clienteSugestoesOpen && $this->clienteSugestoes !== [])
                    <ul id="fv-tv-cliente-sugestoes" class="erp-fv-tv__suggest erp-fv-tv__suggest--cliente" role="listbox" aria-label="Clientes encontrados">
                        @foreach ($this->clienteSugestoes as $index => $sug)
                            <li wire:key="fv-tv-cli-sug-{{ $sug['id'] }}" role="presentation">
                                <button
                                    type="button"
                                    id="fv-tv-cliente-sug-{{ $index }}"
                                    role="option"
                                    aria-selected="{{ $this->selectedClienteSugestaoIndex === $index ? 'true' : 'false' }}"
                                    wire:click="selecionarCliente({{ $sug['id'] }})"
                                    @class(['is-selected' => $this->selectedClienteSugestaoIndex === $index])
                                >
                                    <span class="erp-fv-tv__suggest-code">{{ $sug['codigo'] }}</span>
                                    <span class="erp-fv-tv__suggest-nome">{{ $sug['nome'] }}</span>
                                    <span class="erp-fv-tv__suggest-credito">
                                        <span class="erp-fv-tv__suggest-cred erp-fv-tv__suggest-cred--lim">Lim {{ $sug['limite'] ?? '0,00' }}</span>
                                        <span class="erp-fv-tv__suggest-cred erp-fv-tv__suggest-cred--util">Util {{ $sug['utilizado'] ?? '0,00' }}</span>
                                        <span @class([
                                            'erp-fv-tv__suggest-cred',
                                            'erp-fv-tv__suggest-cred--venc',
                                            'is-vencido' => ($sug['tem_vencidas'] ?? false),
                                        ])>Venc {{ $sug['vencidas'] ?? '0,00' }}</span>
                                    </span>
                                    <span @class([
                                        'erp-fv-tv__suggest-doc',
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

            <label class="erp-fv-tv__field erp-fv-tv__field--doc">
                <span>CPF/CNPJ</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteCpfCnpj }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--fone">
                <span>Fone</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteFone }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--fone">
                <span>WhatsApp</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteWhatsapp }}" readonly>
            </label>
        </div>

        <div class="erp-fv-tv__row erp-fv-tv__row--secondary">
            <label class="erp-fv-tv__field erp-fv-tv__field--end">
                <span>Endereço</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteEndereco }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--num">
                <span>Nº</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteNumero }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--bairro">
                <span>Bairro</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteBairro }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--cep">
                <span>CEP</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteCep }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--cidade">
                <span>Cidade</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteCidade }}" readonly>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--uf">
                <span>UF</span>
                <input class="erp-nfe__input" type="text" value="{{ $this->clienteUf }}" readonly>
            </label>
        </div>
    </div>
</section>

<section class="erp-fv-tv__panel erp-fv-tv__panel--produto">
    <div class="erp-fv-tv__box">
        <span class="erp-fv-tv__box-legend">Produto</span>

        <div class="erp-fv-tv__row erp-fv-tv__row--produto">
            <label class="erp-fv-tv__field erp-fv-tv__field--barcode erp-fv-tv__field--suggest">
                <span>Código / barras / nome</span>
                <div class="erp-fv-tv__barcode-wrap">
                    <input
                        id="fv-tv-barcode"
                        class="erp-nfe__input erp-fv-tv__input--barcode"
                        type="text"
                        x-ref="barcode"
                        wire:model.live.debounce.200ms="codigoBarras"
                        wire:keydown.enter.prevent="confirmarCodigoProduto"
                        wire:keydown.escape.prevent="fecharSugestoesProduto"
                        x-on:keydown.arrow-down.prevent="$wire.moverSugestaoProduto(1)"
                        x-on:keydown.arrow-up.prevent="$wire.moverSugestaoProduto(-1)"
                        autocomplete="off"
                        placeholder="Código, barras ou nome do produto — Enter"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded="{{ $this->produtoSugestoesOpen && $this->produtoSugestoes !== [] ? 'true' : 'false' }}"
                        aria-controls="fv-tv-produto-sugestoes"
                    >
                    @if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== [])
                        <ul id="fv-tv-produto-sugestoes" class="erp-fv-tv__suggest erp-fv-tv__suggest--produto" role="listbox" aria-label="Produtos encontrados">
                            @foreach ($this->produtoSugestoes as $index => $sug)
                                <li wire:key="fv-tv-prod-sug-{{ $sug['id'] }}" role="presentation">
                                    <button
                                        type="button"
                                        id="fv-tv-produto-sug-{{ $index }}"
                                        role="option"
                                        aria-selected="{{ $this->selectedProdutoSugestaoIndex === $index ? 'true' : 'false' }}"
                                        wire:click="selecionarProduto({{ $sug['id'] }})"
                                        @class(['is-selected' => $this->selectedProdutoSugestaoIndex === $index])
                                    >
                                        <span class="erp-fv-tv__suggest-code">{{ $sug['codigo'] }}</span>
                                        <span class="erp-fv-tv__suggest-nome">{{ $sug['nome'] }}</span>
                                        <span class="erp-fv-tv__suggest-estoques">
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--atual">Atual {{ $sug['atual'] }}</span>
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--reservado">Res {{ $sug['reservado'] }}</span>
                                            <span class="erp-fv-tv__suggest-est erp-fv-tv__suggest-est--disponivel">Disp {{ $sug['disponivel'] }}</span>
                                        </span>
                                        <span class="erp-fv-tv__suggest-preco">R$ {{ $sug['preco'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </label>

            <label class="erp-fv-tv__field erp-fv-tv__field--qtd">
                <span>Qtde</span>
                <input
                    id="fv-tv-qtd"
                    class="erp-nfe__input"
                    type="text"
                    x-ref="qtd"
                    wire:model.live="quantidade"
                    wire:keydown.enter.prevent="focoPrecoAposQtd"
                    inputmode="decimal"
                >
            </label>

            <div class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--preco">
                <span>Vlr. unit.</span>
                <div class="erp-fv-tv__preco-wrap">
                    <input
                        id="fv-tv-preco"
                        class="erp-nfe__input"
                        type="text"
                        x-ref="preco"
                        wire:model.live="precoUnitario"
                        wire:keydown.enter.prevent="adicionarItem"
                        inputmode="decimal"
                    >
                    <button
                        type="button"
                        class="erp-fv-tv__btn-desc"
                        wire:click="abrirModalDescontoItem"
                        title="Desconto / Acréscimo (Ctrl+D)"
                    >
                        %
                    </button>
                </div>
            </div>

            <label class="erp-fv-tv__field erp-fv-tv__field--money erp-fv-tv__field--total-item">
                <span>Total item</span>
                <input class="erp-nfe__input erp-fv-tv__input--total" type="text" value="{{ $this->totalItem }}" readonly>
            </label>
        </div>
    </div>
</section>

<div class="erp-fv-tv__body">
    <div class="erp-fv-tv__grid-wrap">
        <table class="erp-fv-tv__grid">
            <thead>
                <tr>
                    <th class="erp-fv-tv__col-idx">#</th>
                    <th class="erp-fv-tv__col-cod">Código</th>
                    <th>Produto</th>
                    <th class="erp-fv-tv__col-num">Qtde</th>
                    <th class="erp-fv-tv__col-num">Vlr. unit.</th>
                    <th class="erp-fv-tv__col-num">TT bruto</th>
                    <th class="erp-fv-tv__col-num">Acrés.</th>
                    <th class="erp-fv-tv__col-num">Desc.</th>
                    <th class="erp-fv-tv__col-num">TT líq.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->itens as $i => $item)
                    <tr
                        wire:key="fv-item-{{ $item['key'] }}"
                        class="{{ $this->itemSelecionado === $i ? 'is-selected' : '' }}"
                        wire:click="selecionarItem({{ $i }})"
                    >
                        <td class="erp-fv-tv__col-idx">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $i + 1 }}</div>
                        </td>
                        <td class="erp-fv-tv__col-cod">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--center">{{ $item['codigo'] }}</div>
                        </td>
                        <td>
                            <div
                                class="erp-fv-tv__cell erp-fv-tv__cell--desc erp-fv-tv__cell--pull"
                                title="Duplo clique: voltar para a barra de inclusão"
                                wire:dblclick.stop="puxarItemParaInclusao({{ $i }})"
                            >{{ $item['descricao'] }}</div>
                        </td>
                        <td class="erp-fv-tv__col-num">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--num">{{ $this->formatQty($item['quantidade']) }}</div>
                        </td>
                        <td class="erp-fv-tv__col-num">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                <span class="erp-fv-tv__money-rs">R$</span>
                                <span class="erp-fv-tv__money-val">{{ $this->formatMoney($item['preco_unitario']) }}</span>
                            </div>
                        </td>
                        <td class="erp-fv-tv__col-num">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                <span class="erp-fv-tv__money-rs">R$</span>
                                <span class="erp-fv-tv__money-val">{{ $this->formatMoney($item['quantidade'] * $item['preco_unitario']) }}</span>
                            </div>
                        </td>
                        <td class="erp-fv-tv__col-num">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--money erp-fv-tv__val-acr">
                                <span class="erp-fv-tv__money-rs">R$</span>
                                <span class="erp-fv-tv__money-val">{{ $this->formatMoney($item['acrescimo']) }}</span>
                            </div>
                        </td>
                        <td class="erp-fv-tv__col-num">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--money erp-fv-tv__val-desc">
                                <span class="erp-fv-tv__money-rs">R$</span>
                                <span class="erp-fv-tv__money-val">{{ $this->formatMoney($item['desconto']) }}</span>
                            </div>
                        </td>
                        <td class="erp-fv-tv__col-num erp-fv-tv__val-liq">
                            <div class="erp-fv-tv__cell erp-fv-tv__cell--money">
                                <span class="erp-fv-tv__money-rs">R$</span>
                                <span class="erp-fv-tv__money-val">{{ $this->formatMoney($item['total']) }}</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr class="erp-fv-tv__empty">
                        <td colspan="9">Nenhum item — informe o código e pressione Enter</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <aside class="erp-fv-tv__aside">
        <div class="erp-fv-tv__foto">
            @if ($this->produtoAtualFoto)
                <img src="{{ $this->produtoAtualFoto }}" alt="{{ $this->produtoAtualNome }}">
            @else
                <div class="erp-fv-tv__foto-empty">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <rect x="3" y="5" width="18" height="14" rx="2"/>
                        <circle cx="8.5" cy="10.5" r="1.5"/>
                        <path d="M21 16l-5-5-4 4-2-2-5 5"/>
                    </svg>
                    <span>Foto do produto</span>
                </div>
            @endif
            @if ($this->produtoAtualNome !== '')
                <p class="erp-fv-tv__foto-caption">{{ $this->produtoAtualNome }}</p>
            @endif
        </div>

        <div class="erp-fv-tv__totais">
            <div class="erp-fv-tv__totais-head">Resumo</div>
            <div class="erp-fv-tv__total-row">
                <span>Total bruto</span>
                <strong>{{ $this->formatMoney($this->totalBruto) }}</strong>
            </div>
            <div class="erp-fv-tv__total-row">
                <span>Acréscimos</span>
                <strong class="erp-fv-tv__val-acr">{{ $this->formatMoney($this->totalAcrescimosItens) }}</strong>
            </div>
            <div class="erp-fv-tv__total-row">
                <span>Descontos</span>
                <strong class="erp-fv-tv__val-desc">{{ $this->formatMoney($this->totalDescontosItens) }}</strong>
            </div>
            <div class="erp-fv-tv__total-row erp-fv-tv__total-row--liq">
                <span>Total líquido</span>
                <strong>R$ {{ $this->formatMoney($this->totalLiquido()) }}</strong>
            </div>
            <div class="erp-fv-tv__itens-count">{{ count($this->itens) }} {{ count($this->itens) === 1 ? 'item' : 'itens' }}</div>
        </div>
    </aside>
</div>
