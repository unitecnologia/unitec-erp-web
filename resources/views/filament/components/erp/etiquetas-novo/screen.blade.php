<div
    class="erp-etiquetas-novo"
    wire:ignore.self
    wire:keydown.escape.window="handleEscape"
    x-data
    x-on:keydown.delete.window="
        const t = document.activeElement;
        if (t && (t.matches('input, textarea, select') || t.isContentEditable)) return;
        $wire.excluirLinhaSelecionada();
    "
>
    <header class="erp-etiquetas-novo-window__titlebar">
        <span class="erp-etiquetas-novo-window__title">Impressão de Etiquetas</span>
        <button
            type="button"
            class="erp-etiquetas-novo-window__close"
            wire:click="closeScreen"
            aria-label="Fechar"
            title="ESC | Fechar"
        >&times;</button>
    </header>

    <div class="erp-etiquetas-novo__body">
        <div class="erp-etiquetas-novo__params">
            <label class="erp-etiquetas-novo__field">
                <span>Modelo de Etiquetas</span>
                <select class="erp-etiquetas-novo__select" wire:model="modeloEtiqueta">
                    <option value="gondola">Etiqueta de Gôndola</option>
                </select>
            </label>

            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--printer">
                <span>Impressora</span>
                <div class="erp-etiquetas-novo__printer-wrap">
                    <select class="erp-etiquetas-novo__select" wire:model="impressoraSelecionada">
                        @if ($this->impressorasRaw === [] && $this->impressoraSelecionada === '')
                            <option value="">Selecione</option>
                        @endif
                        @if ($this->impressoraSelecionada !== '' && ! in_array($this->impressoraSelecionada, $this->impressorasRaw, true))
                            <option value="{{ $this->impressoraSelecionada }}">{{ $this->impressoraSelecionada }}</option>
                        @endif
                        @foreach ($this->impressorasRaw as $nome)
                            <option value="{{ $nome }}">{{ $nome }}</option>
                        @endforeach
                    </select>
                    <button
                        type="button"
                        class="erp-etiquetas-novo__lupa"
                        wire:click="listarImpressorasRaw"
                        title="Listar impressoras RAW (Device Service)"
                        aria-label="Listar impressoras RAW"
                    >🖨</button>
                </div>
            </label>

            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--qty">
                <span>Qtde Etiquetas</span>
                <input type="number" min="1" wire:model="qtdEtiquetas" class="erp-etiquetas-novo__input">
            </label>

            <button
                type="button"
                class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--save-prefs"
                wire:click="salvarConfiguracoes"
                title="Salvar modelo e impressora"
            >Salvar configurações</button>
        </div>

        <div class="erp-etiquetas-novo__filters">
            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--doc">
                <span>Nº Romaneio</span>
                <div class="erp-etiquetas-novo__lookup">
                    <input
                        type="text"
                        wire:model="filtroRomaneio"
                        wire:keydown.enter.prevent="adicionarRomaneio($event.target.value)"
                        class="erp-etiquetas-novo__input"
                        placeholder="Nº"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="erp-etiquetas-novo__lupa"
                        wire:click="abrirLookupRomaneio"
                        title="Localizar romaneio"
                        aria-label="Localizar romaneio"
                    >🔍</button>
                    @if ($this->docLookupTipo === 'romaneio')
                        <div class="erp-etiquetas-novo__lookup-panel erp-etiquetas-novo__lookup-panel--nf" wire:click.stop>
                            <div class="erp-etiquetas-novo__lookup-head erp-etiquetas-novo__lookup-head--nf">
                                <label class="erp-etiquetas-novo__lookup-field">
                                    <span>Data</span>
                                    <input
                                        type="date"
                                        wire:model="docLookupData"
                                        class="erp-etiquetas-novo__input erp-etiquetas-novo__input--lookup-date"
                                    >
                                </label>
                                <label class="erp-etiquetas-novo__lookup-field erp-etiquetas-novo__lookup-field--nf">
                                    <span>Nº</span>
                                    <input
                                        type="text"
                                        wire:model="docLookupBusca"
                                        wire:keydown.enter.prevent="buscarDocLookup"
                                        class="erp-etiquetas-novo__input"
                                        placeholder="Nº Romaneio"
                                        autocomplete="off"
                                        autofocus
                                    >
                                </label>
                                <button type="button" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--small" wire:click="buscarDocLookup">Buscar</button>
                                <button type="button" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--small" wire:click="fecharDocLookup">Fechar</button>
                            </div>
                            <div class="erp-etiquetas-novo__lookup-list">
                                @forelse ($this->docLookupResults as $doc)
                                    <button
                                        type="button"
                                        class="erp-etiquetas-novo__lookup-item erp-etiquetas-novo__lookup-item--nf"
                                        wire:click="selecionarDocLookup({{ $doc['id'] }})"
                                    >
                                        <strong>{{ $doc['numero'] }}</strong>
                                        <span>{{ $doc['data'] }}</span>
                                        <span>{{ $doc['fornecedor'] }}</span>
                                    </button>
                                @empty
                                    <div class="erp-etiquetas-novo__lookup-empty">Nenhum romaneio encontrado.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--doc">
                <span>NF Entrada</span>
                <div class="erp-etiquetas-novo__lookup">
                    <input
                        type="text"
                        wire:model="filtroNfEntrada"
                        wire:keydown.enter.prevent="adicionarNfEntrada($event.target.value)"
                        class="erp-etiquetas-novo__input"
                        placeholder="NF"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        class="erp-etiquetas-novo__lupa"
                        wire:click="abrirLookupNfEntrada"
                        title="Localizar NF de entrada"
                        aria-label="Localizar NF de entrada"
                    >🔍</button>
                    @if ($this->docLookupTipo === 'nf')
                        <div class="erp-etiquetas-novo__lookup-panel erp-etiquetas-novo__lookup-panel--nf" wire:click.stop>
                            <div class="erp-etiquetas-novo__lookup-head erp-etiquetas-novo__lookup-head--nf">
                                <label class="erp-etiquetas-novo__lookup-field">
                                    <span>Data</span>
                                    <input
                                        type="date"
                                        wire:model="docLookupData"
                                        class="erp-etiquetas-novo__input erp-etiquetas-novo__input--lookup-date"
                                    >
                                </label>
                                <label class="erp-etiquetas-novo__lookup-field erp-etiquetas-novo__lookup-field--nf">
                                    <span>NF</span>
                                    <input
                                        type="text"
                                        wire:model="docLookupBusca"
                                        wire:keydown.enter.prevent="buscarDocLookup"
                                        class="erp-etiquetas-novo__input"
                                        placeholder="Nº NF"
                                        autocomplete="off"
                                        autofocus
                                    >
                                </label>
                                <button type="button" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--small" wire:click="buscarDocLookup">Buscar</button>
                                <button type="button" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--small" wire:click="fecharDocLookup">Fechar</button>
                            </div>
                            <div class="erp-etiquetas-novo__lookup-list">
                                @forelse ($this->docLookupResults as $doc)
                                    <button
                                        type="button"
                                        class="erp-etiquetas-novo__lookup-item erp-etiquetas-novo__lookup-item--nf"
                                        wire:click="selecionarDocLookup({{ $doc['id'] }})"
                                    >
                                        <strong>{{ $doc['nota'] !== '—' ? $doc['nota'] : $doc['numero'] }}</strong>
                                        <span>{{ $doc['data'] }}</span>
                                        <span>{{ $doc['fornecedor'] }}</span>
                                    </button>
                                @empty
                                    <div class="erp-etiquetas-novo__lookup-empty">Nenhuma NF encontrada.</div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </label>

            <label class="erp-etiquetas-novo__check">
                <input type="checkbox" wire:model="somentePrecoAlterados">
                <span>Somente preço alterados</span>
            </label>

            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--date">
                <span>Validade</span>
                <input type="date" wire:model="filtroValidade" class="erp-etiquetas-novo__input">
            </label>

            <label class="erp-etiquetas-novo__field erp-etiquetas-novo__field--date">
                <span>Alterados em</span>
                <input type="date" wire:model="filtroAlteradosEm" class="erp-etiquetas-novo__input">
            </label>

            <button
                type="button"
                class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--add"
                wire:click="adicionarDocumentos"
                title="Adicionar produtos do Romaneio/NF informado"
            >Adicionar</button>
        </div>

        <div
            class="erp-etiquetas-novo__produto-busca"
            x-data
            x-on:erp-etiquetas-focus-busca.window="$nextTick(() => $refs.etiqBusca?.focus())"
        >
            <div class="erp-etiquetas-novo__produto-box">
                <span class="erp-etiquetas-novo__produto-legend">Produto</span>
                <div class="erp-etiquetas-novo__produto-row">
                    <label class="erp-etiquetas-novo__produto-field">
                        <span>Código / barras / nome</span>
                        <div class="erp-etiquetas-novo__produto-wrap">
                            <input
                                type="text"
                                x-ref="etiqBusca"
                                wire:model.live.debounce.200ms="termoBusca"
                                wire:keydown.enter.prevent="confirmarProdutoBusca"
                                wire:keydown.escape.prevent="fecharSugestoesProduto"
                                wire:keydown.arrow-up.prevent="moverSugestaoProduto(-1)"
                                wire:keydown.arrow-down.prevent="moverSugestaoProduto(1)"
                                class="erp-etiquetas-novo__input erp-etiquetas-novo__input--search"
                                placeholder="Código, barras ou nome do produto — Enter"
                                autocomplete="off"
                                data-erp-uppercase
                                role="combobox"
                                aria-autocomplete="list"
                                aria-expanded="{{ $this->produtoSugestoesOpen && $this->produtoSugestoes !== [] ? 'true' : 'false' }}"
                                aria-controls="etiq-produto-sugestoes"
                            >
                            @if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== [])
                                <ul
                                    id="etiq-produto-sugestoes"
                                    class="erp-etiquetas-novo__suggest"
                                    role="listbox"
                                    aria-label="Produtos encontrados"
                                    wire:click.stop
                                >
                                    @foreach ($this->produtoSugestoes as $index => $sug)
                                        <li wire:key="etiq-prod-sug-{{ $sug['id'] }}" role="presentation">
                                            <button
                                                type="button"
                                                role="option"
                                                aria-selected="{{ $this->selectedProdutoSugestaoIndex === $index ? 'true' : 'false' }}"
                                                wire:click="selecionarProdutoBusca({{ $sug['id'] }})"
                                                @class(['is-selected' => $this->selectedProdutoSugestaoIndex === $index])
                                            >
                                                <span class="erp-etiquetas-novo__suggest-code">{{ $sug['codigo'] !== '' ? $sug['codigo'] : '—' }}</span>
                                                <span class="erp-etiquetas-novo__suggest-nome">{{ $sug['descricao'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </label>
                    <button type="button" wire:click="limpar" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--small">Limpar</button>
                </div>
            </div>
        </div>

        <div class="erp-etiquetas-novo__main">
            <section class="erp-etiquetas-novo__panel">
                <header class="erp-etiquetas-novo__panel-head">Produtos a serem impressos</header>
                <div class="erp-etiquetas-novo__grid-wrap">
                    <table class="erp-etiquetas-novo__grid">
                        <thead>
                            <tr>
                                <th class="is-sel">Selecionar</th>
                                <th class="is-qty">Quantidade</th>
                                <th class="is-code">Código</th>
                                <th class="is-barcode">Código Barra</th>
                                <th class="is-desc">Descrição</th>
                                <th class="is-price">Preço</th>
                                <th class="is-unid">Unid</th>
                                <th class="is-validade">Validade</th>
                                <th class="is-action">Excluir</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->itensImpressao as $index => $item)
                                <tr
                                    wire:key="etiq-item-{{ $item['uid'] }}"
                                    class="{{ $this->linhaSelecionada === $index ? 'is-selected' : '' }}"
                                    wire:click="selecionarLinha({{ $index }})"
                                >
                                    <td class="is-sel" wire:click.stop>
                                        <input
                                            type="checkbox"
                                            class="erp-etiquetas-novo__sel"
                                            @checked($this->linhaSelecionada === $index)
                                            wire:click="selecionarLinha({{ $index }})"
                                            aria-label="Selecionar linha"
                                        >
                                    </td>
                                    <td class="is-qty" wire:click.stop>
                                        <input
                                            type="number"
                                            min="1"
                                            class="erp-etiquetas-novo__qty-input"
                                            wire:model.blur="itensImpressao.{{ $index }}.quantidade"
                                        >
                                    </td>
                                    <td class="is-code">{{ $item['codigo'] }}</td>
                                    <td class="is-barcode">{{ $item['codigo_barras'] }}</td>
                                    <td class="is-desc" title="{{ $item['descricao'] }}">{{ $item['descricao'] }}</td>
                                    <td class="is-price">{{ $item['preco'] }}</td>
                                    <td class="is-unid">{{ $item['unidade'] }}</td>
                                    <td class="is-validade">{{ $item['validade'] !== '' ? $item['validade'] : '—' }}</td>
                                    <td class="is-action" wire:click.stop>
                                        <button
                                            type="button"
                                            class="erp-etiquetas-novo__icon-btn erp-etiquetas-novo__icon-btn--danger"
                                            wire:click="removerItemImpressao({{ $index }})"
                                            title="Excluir da lista"
                                            aria-label="Excluir da lista"
                                        >🗑</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($this->itensImpressao === [])
                        <div class="erp-etiquetas-novo__empty">Nenhum produto selecionado</div>
                    @endif
                </div>
            </section>
        </div>

        <div class="erp-etiquetas-novo__footer">
            <div class="erp-etiquetas-novo__footer-left">
                <span class="erp-etiquetas-novo__total">Total de produtos selecionados: <strong>{{ $this->totalItensImpressao() }}</strong></span>
                <button type="button" wire:click="closeScreen" class="erp-etiquetas-novo__exit">ESC - Sair</button>
            </div>
            <div class="erp-etiquetas-novo__footer-actions">
                <button type="button" wire:click="limpar" class="erp-etiquetas-novo__btn">Limpar seleção</button>
                <button type="button" wire:click="imprimir" class="erp-etiquetas-novo__btn erp-etiquetas-novo__btn--primary">Imprimir</button>
            </div>
        </div>
    </div>
</div>
