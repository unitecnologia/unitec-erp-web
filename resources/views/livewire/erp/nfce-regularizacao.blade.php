<div
    class="erp-lookup-modal erp-nfce-fiscal-modal erp-nfce-regularizacao-modal"
    x-data="erpNfceRegularizacao()"
    x-on:keydown.escape.window="fecharSePuder()"
>
    <div class="erp-lookup-modal__backdrop" x-on:click="fecharSePuder()"></div>

    <div class="erp-lookup-modal__window erp-nfce-reg__window" role="dialog" aria-modal="true" aria-labelledby="erp-nfce-reg-title">
        <div class="erp-lookup-modal__titlebar">
            <span id="erp-nfce-reg-title">Regularização Fiscal — Vendas sem NFC-e/NF-e</span>
            <button type="button" class="erp-lookup-modal__close" x-on:click="fecharSePuder()" title="Fechar">✕</button>
        </div>

        <div class="erp-nfce-reg__filters" x-bind:class="{ 'is-disabled': ocupado }">
            <label class="erp-nfce-reg__field">
                <span>De</span>
                <input type="date" wire:model="dataDe" wire:keydown.enter="aplicarFiltros" min="{{ $periodo[0] }}" max="{{ $periodo[1] }}" class="erp-nfce-reg__input">
            </label>
            <label class="erp-nfce-reg__field">
                <span>Até</span>
                <input type="date" wire:model="dataAte" wire:keydown.enter="aplicarFiltros" min="{{ $periodo[0] }}" max="{{ $periodo[1] }}" class="erp-nfce-reg__input">
            </label>
            <label class="erp-nfce-reg__field erp-nfce-reg__field--sm">
                <span>Nº venda</span>
                <input type="text" wire:model="numero" wire:keydown.enter="aplicarFiltros" class="erp-nfce-reg__input" autocomplete="off">
            </label>
            <label class="erp-nfce-reg__field erp-nfce-reg__field--lg">
                <span>Cliente / CPF / CNPJ</span>
                <input type="text" wire:model="cliente" wire:keydown.enter="aplicarFiltros" class="erp-nfce-reg__input" autocomplete="off">
            </label>
            <label class="erp-nfce-reg__field">
                <span>Origem</span>
                <select wire:model="origem" wire:change="aplicarFiltros" class="erp-nfce-reg__input">
                    <option value="">Todas</option>
                    @foreach ($origens as $valor => $label)
                        <option value="{{ $valor }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="erp-nfce-reg__field">
                <span>Pagamento</span>
                <select wire:model="pagamento" wire:change="aplicarFiltros" class="erp-nfce-reg__input">
                    <option value="">Todos</option>
                    @foreach ($formasPagamento as $forma)
                        <option value="{{ $forma }}">{{ $forma }}</option>
                    @endforeach
                </select>
            </label>
            <label class="erp-nfce-reg__field">
                <span>Vendedor</span>
                <input type="text" wire:model="vendedor" wire:keydown.enter="aplicarFiltros" class="erp-nfce-reg__input" autocomplete="off">
            </label>
            <div class="erp-nfce-reg__filter-actions">
                <button type="button" wire:click="aplicarFiltros" class="erp-nfce-reg__btn erp-nfce-reg__btn--primary">
                    <span wire:loading.remove wire:target="aplicarFiltros,limparFiltros">Filtrar</span>
                    <span wire:loading wire:target="aplicarFiltros,limparFiltros">Filtrando…</span>
                </button>
                <button type="button" wire:click="limparFiltros" class="erp-nfce-reg__btn">Limpar</button>
            </div>
        </div>

        <template x-if="bloqueio">
            <div class="erp-nfce-reg__alert erp-nfce-reg__alert--danger" role="alert">
                <span x-text="bloqueio"></span>
                <button type="button" class="erp-nfce-reg__alert-close" x-on:click="bloqueio = null">✕</button>
            </div>
        </template>

        <div class="erp-nfce-reg__grid" x-on:scroll.passive="busca.aberta && fecharBusca()" wire:loading.class="is-loading" wire:target="aplicarFiltros,limparFiltros,previousPage,nextPage,gotoPage">
            <table class="erp-nfce-reg__table">
                <colgroup>
                    <col class="erp-nfce-reg__w-check">
                    <col class="erp-nfce-reg__w-origem">
                    <col class="erp-nfce-reg__w-numero">
                    <col class="erp-nfce-reg__w-data">
                    <col>
                    <col class="erp-nfce-reg__w-doc">
                    <col class="erp-nfce-reg__w-caixa">
                    <col class="erp-nfce-reg__w-vendedor">
                    <col class="erp-nfce-reg__w-pagamento">
                    <col class="erp-nfce-reg__w-valor">
                    <col class="erp-nfce-reg__w-situacao">
                    <col class="erp-nfce-reg__w-dias">
                </colgroup>
                <thead>
                    <tr>
                        <th class="erp-nfce-reg__col-check" aria-label="Selecionar"></th>
                        <th>Origem</th>
                        <th>Nº venda</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>CPF</th>
                        <th>Caixa</th>
                        <th>Vendedor</th>
                        <th>Pagamento</th>
                        <th class="erp-nfce-reg__num">Valor</th>
                        <th>Situação</th>
                        <th class="erp-nfce-reg__center">Dias</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @if ($row['selecionavel'])
                            <tr
                                wire:key="reg-row-{{ $row['id'] }}"
                                data-reg-row
                                data-id="{{ $row['id'] }}"
                                data-total="{{ $row['total'] }}"
                                x-bind:class="{ 'is-selected': isSel({{ $row['id'] }}) }"
                                x-on:click="toggle({{ $row['id'] }}, {{ $row['total'] }})"
                            >
                                <td class="erp-nfce-reg__col-check">
                                    <input
                                        type="checkbox"
                                        class="erp-nfce-reg__check"
                                        x-bind:checked="isSel({{ $row['id'] }})"
                                        x-on:click.stop="toggle({{ $row['id'] }}, {{ $row['total'] }})"
                                        aria-label="Selecionar venda {{ $row['numero'] }}"
                                    >
                                </td>
                        @else
                            <tr wire:key="reg-row-{{ $row['id'] }}" class="is-blocked" title="{{ $row['situacao'] }}">
                                <td class="erp-nfce-reg__col-check">
                                    <input type="checkbox" class="erp-nfce-reg__check" disabled aria-label="Venda {{ $row['numero'] }} não pode ser selecionada">
                                </td>
                        @endif
                            <td><span class="erp-nfce-reg__badge erp-nfce-reg__badge--{{ $row['origem'] }}">{{ $row['origem_label'] }}</span></td>
                            <td class="erp-nfce-reg__mono">{{ $row['numero'] }}</td>
                            <td>{{ $row['data'] }}</td>
                        @if ($row['selecionavel'])
                            <td class="erp-nfce-reg__edit-cell" wire:ignore x-on:click.stop>
                                <input
                                    type="text"
                                    class="erp-nfce-reg__cell-input"
                                    data-reg-nome="{{ $row['id'] }}"
                                    data-person="{{ $row['edit_person_id'] ?? '' }}"
                                    value="{{ $row['edit_nome'] }}"
                                    placeholder="CONSUMIDOR FINAL"
                                    maxlength="60"
                                    autocomplete="off"
                                    spellcheck="false"
                                    title="Digite para buscar o cliente na base ou informe o nome"
                                    x-on:input="digitarNome({{ $row['id'] }}, $event.target)"
                                    x-on:keydown="teclaNome($event, {{ $row['id'] }})"
                                    x-on:blur="sairNome()"
                                    x-bind:disabled="ocupado"
                                >
                            </td>
                            <td class="erp-nfce-reg__edit-cell" wire:ignore x-on:click.stop>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    class="erp-nfce-reg__cell-input erp-nfce-reg__mono"
                                    x-bind:class="{ 'is-invalid': erroCpf({{ $row['id'] }}) }"
                                    x-bind:title="erroCpf({{ $row['id'] }}) || 'CPF do consumidor (NFC-e não aceita CNPJ)'"
                                    data-reg-cpf="{{ $row['id'] }}"
                                    value="{{ $row['edit_cpf'] }}"
                                    placeholder="—"
                                    maxlength="18"
                                    autocomplete="off"
                                    x-on:input="digitarCpf({{ $row['id'] }}, $event.target)"
                                    x-on:change="confirmarCpf({{ $row['id'] }}, $event.target)"
                                    x-on:keydown.enter.prevent="$event.target.blur()"
                                    x-bind:disabled="ocupado"
                                >
                            </td>
                        @else
                            <td class="erp-nfce-reg__ellipsis" title="{{ $row['cliente'] }}">{{ $row['cliente'] }}</td>
                            <td class="erp-nfce-reg__mono">{{ $row['documento'] }}</td>
                        @endif
                            <td class="erp-nfce-reg__ellipsis">{{ $row['caixa'] }}</td>
                            <td class="erp-nfce-reg__ellipsis" title="{{ $row['vendedor'] }}">{{ $row['vendedor'] }}</td>
                            <td class="erp-nfce-reg__ellipsis" title="{{ $row['forma'] }}">{{ $row['forma'] }}</td>
                            <td class="erp-nfce-reg__num erp-nfce-reg__mono">
                                <span class="erp-nfce-reg__money"><span class="erp-nfce-reg__money-cur">R$</span><span>{{ number_format($row['total'], 2, ',', '.') }}</span></span>
                            </td>
                            <td class="erp-nfce-reg__ellipsis" title="{{ $row['situacao'] }}">
                                <span class="erp-nfce-reg__status erp-nfce-reg__status--{{ $row['situacao_tone'] }}">{{ $row['situacao'] }}</span>
                            </td>
                            <td class="erp-nfce-reg__center erp-nfce-reg__mono">{{ $row['dias'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="erp-nfce-reg__empty">Nenhuma venda pendente de documento fiscal no filtro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <ul
            class="erp-nfce-reg__suggest"
            x-show="busca.aberta"
            x-cloak
            x-bind:style="'top:' + busca.top + 'px;left:' + busca.left + 'px;width:' + busca.width + 'px'"
            role="listbox"
        >
            <template x-for="(c, i) in busca.itens" x-bind:key="c.id">
                <li
                    role="option"
                    x-bind:class="{ 'is-active': i === busca.idx }"
                    x-on:mousedown.prevent="escolherCliente(c)"
                    x-on:mouseenter="busca.idx = i"
                >
                    <span class="erp-nfce-reg__suggest-nome" x-text="c.nome"></span>
                    <span class="erp-nfce-reg__suggest-doc erp-nfce-reg__mono" x-text="c.cpf || 'sem CPF'"></span>
                </li>
            </template>
            <li class="erp-nfce-reg__suggest-empty" x-show="busca.itens.length === 0 && ! busca.carregando">
                Cliente não encontrado na base — informe o CPF e o nome digitado vai na nota.
            </li>
            <li class="erp-nfce-reg__suggest-empty" x-show="busca.carregando">Buscando…</li>
        </ul>

        <div class="erp-nfce-reg__pager">
            <span>{{ number_format($paginator->total(), 0, ',', '.') }} venda(s) pendente(s) no filtro</span>
            <button type="button" class="erp-nfce-reg__btn erp-nfce-reg__btn--ghost" x-on:click="marcarPagina()" x-bind:disabled="ocupado">Marcar todos</button>
            @if ($paginator->hasPages())
                <div class="erp-nfce-reg__pager-nav">
                    @if ($paginator->onFirstPage())
                        <span class="erp-nfce-reg__pager-off">Anterior</span>
                    @else
                        <button type="button" class="erp-nfce-reg__btn erp-nfce-reg__btn--ghost" wire:click="previousPage('regPage')">Anterior</button>
                    @endif
                    <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
                    @if ($paginator->hasMorePages())
                        <button type="button" class="erp-nfce-reg__btn erp-nfce-reg__btn--ghost" wire:click="nextPage('regPage')">Próxima</button>
                    @else
                        <span class="erp-nfce-reg__pager-off">Próxima</span>
                    @endif
                </div>
            @endif
        </div>

        <template x-if="resultados.length > 0">
            <div class="erp-nfce-reg__results">
                <div class="erp-nfce-reg__results-head">
                    <strong>Resultado da transmissão</strong>
                    <span>
                        <span class="erp-nfce-reg__status erp-nfce-reg__status--ok" x-text="contagem('autorizada') + ' autorizada(s)'"></span>
                        <span class="erp-nfce-reg__status erp-nfce-reg__status--danger" x-text="(contagem('rejeitada') + contagem('ignorada')) + ' não emitida(s)'"></span>
                    </span>
                    <button type="button" class="erp-nfce-reg__alert-close" x-on:click="resultados = []" x-show="! ocupado" title="Ocultar">✕</button>
                </div>
                <ul class="erp-nfce-reg__results-list">
                    <template x-for="r in resultados" x-bind:key="r.id">
                        <li x-bind:class="'is-' + r.status">
                            <span class="erp-nfce-reg__mono" x-text="'Venda ' + r.numero"></span>
                            <span x-text="rotulo(r)"></span>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        <div class="erp-lookup-modal__actions erp-pcad-actions erp-nfce-reg__actions">
            <div class="erp-nfce-reg__summary" aria-live="polite">
                <strong x-text="qtd"></strong>
                <span x-text="qtd === 1 ? 'venda selecionada' : 'vendas selecionadas'"></span>
                <span class="erp-nfce-reg__summary-sep">|</span>
                <span>Total</span>
                <strong x-text="'R$ ' + fmt(total)"></strong>
            </div>
            <button
                type="button"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--primary erp-nfce-reg__action erp-nfce-reg__action--emit"
                x-on:click="emitir()"
                x-bind:disabled="qtd === 0 || ocupado"
            >
                <span class="erp-pcad-actions__icon">📡</span>
                <span class="erp-pcad-actions__label" x-text="ocupado ? 'Transmitindo…' : 'Transmitir NFC-e selecionadas'"></span>
            </button>
            <button type="button" class="erp-pcad-actions__btn erp-nfce-reg__action" x-on:click="limpar()" x-bind:disabled="qtd === 0 || ocupado">
                <span class="erp-pcad-actions__icon">☐</span>
                <span class="erp-pcad-actions__label">Limpar seleção</span>
            </button>
            <button type="button" class="erp-pcad-actions__btn erp-nfce-reg__action erp-nfce-reg__action--close" x-on:click="fecharSePuder()" x-bind:disabled="ocupado">
                <span class="erp-pcad-actions__icon">✕</span>
                <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
            </button>
        </div>

        <div class="erp-nfce-reg__busy" x-show="ocupado" x-cloak role="status" aria-live="polite">
            <div class="erp-nfce-reg__busy-panel">
                <div class="erp-nfce-reg__spinner" aria-hidden="true"></div>
                <p x-show="fase === 'validando'">Validando vendas selecionadas…</p>
                <p x-show="fase === 'emitindo'" x-text="'Transmitindo NFC-e ' + progresso.atual + ' de ' + progresso.total + ' — venda ' + progresso.numero"></p>
                <div class="erp-nfce-reg__busy-track" x-show="fase === 'emitindo'">
                    <div class="erp-nfce-reg__busy-bar" x-bind:style="'width:' + (progresso.total ? Math.round(progresso.atual / progresso.total * 100) : 0) + '%'"></div>
                </div>
                <p class="erp-nfce-reg__busy-hint">Aguarde, não feche esta tela.</p>
            </div>
        </div>
    </div>
</div>
