@php
    $selCount = $this->quantidadeSelecionada();
    $rows = $this->resultado['rows'] ?? [];
    $total = (int) ($this->resultado['total'] ?? 0);
    $page = (int) ($this->resultado['page'] ?? 1);
    $lastPage = (int) ($this->resultado['last_page'] ?? 1);
    $selecionadosSet = array_fill_keys($this->selecionados, true);
@endphp

<div
    class="erp-zera-estoque"
    wire:ignore.self
    x-data
    x-on:keydown.escape.window="
        if ($wire.confirmOpen) { $event.preventDefault(); $wire.cancelarConfirmacao(); }
    "
    x-on:keydown.window="
        if ($event.key === 'F5') { $event.preventDefault(); $wire.pesquisar(); }
    "
>
    <div class="erp-zera-estoque__toolbar">
        <div class="erp-zera-estoque__toolbar-left">
            <button type="button" wire:click="pesquisar" class="erp-zera-estoque__btn erp-zera-estoque__btn--primary" title="Consultar (F5)">
                <kbd>F5</kbd> Consulta
            </button>
            <button type="button" wire:click="selecionarTodosFiltrados" class="erp-zera-estoque__btn" title="Selecionar todos do filtro">
                Selecionar filtrados
            </button>
            <button type="button" wire:click="limparSelecao" class="erp-zera-estoque__btn" @disabled($selCount === 0)>
                Limpar seleção
            </button>
            <button type="button" wire:click="limparFiltros" class="erp-zera-estoque__btn">
                Limpar filtros
            </button>
            @if ($selCount > 0)
                <span class="erp-zera-estoque__badge">
                    {{ $selCount }} produto(s)
                    @if ($this->selecaoTodosFiltrados)
                        · conjunto filtrado
                    @endif
                </span>
            @endif
        </div>
        <button type="button" wire:click="closeScreen" class="erp-zera-estoque__btn erp-zera-estoque__btn--ghost">Fechar</button>
    </div>

    <div class="erp-zera-estoque__filters">
        <label class="erp-zera-estoque__field erp-zera-estoque__field--grow">
            <span class="erp-zera-estoque__label">Código / Descrição</span>
            <input
                type="text"
                wire:model="pesquisa"
                wire:keydown.enter="pesquisar"
                class="erp-zera-estoque__input"
                placeholder="Pesquisar…"
                autocomplete="off"
            >
        </label>

        <label class="erp-zera-estoque__field">
            <span class="erp-zera-estoque__label">Grupo</span>
            <select wire:model="grupoFilter" class="erp-zera-estoque__select">
                <option value="todos">&lt;todos&gt;</option>
                @foreach ($this->gruposOptions as $nome => $label)
                    <option value="{{ $nome }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="erp-zera-estoque__field erp-zera-estoque__field--disabled" title="Cadastro de subgrupo ainda não disponível no web">
            <span class="erp-zera-estoque__label">Subgrupo <em>n/d</em></span>
            <select class="erp-zera-estoque__select" disabled>
                <option>&lt;todos&gt;</option>
            </select>
        </label>

        <label class="erp-zera-estoque__field">
            <span class="erp-zera-estoque__label">Marca</span>
            <select wire:model="marcaFilter" class="erp-zera-estoque__select">
                <option value="todos">&lt;todas&gt;</option>
                @foreach ($this->marcasOptions as $nome => $label)
                    <option value="{{ $nome }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="erp-zera-estoque__field erp-zera-estoque__field--wide">
            <span class="erp-zera-estoque__label">Fornecedor</span>
            <select wire:model="fornecedorFilter" class="erp-zera-estoque__select">
                <option value="todos">&lt;todos&gt;</option>
                @foreach ($this->fornecedoresOptions as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <label class="erp-zera-estoque__field erp-zera-estoque__field--wide">
            <span class="erp-zera-estoque__label">Depósito</span>
            <select wire:model.live="depositoFilter" class="erp-zera-estoque__select">
                @foreach ($this->depositosOptions as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>

        <div class="erp-zera-estoque__field">
            <span class="erp-zera-estoque__label">Saldo</span>
            <div class="erp-zera-estoque__seg" role="radiogroup" aria-label="Saldo">
                <label @class(['erp-zera-estoque__chip', 'is-active' => $this->saldoFilter === 'todos'])>
                    <input type="radio" wire:model="saldoFilter" value="todos">
                    <span>Todos</span>
                </label>
                <label @class(['erp-zera-estoque__chip', 'is-active' => $this->saldoFilter === 'positivo'])>
                    <input type="radio" wire:model="saldoFilter" value="positivo">
                    <span>Positivo</span>
                </label>
                <label @class(['erp-zera-estoque__chip', 'is-active' => $this->saldoFilter === 'negativo'])>
                    <input type="radio" wire:model="saldoFilter" value="negativo">
                    <span>Negativo</span>
                </label>
            </div>
        </div>
    </div>

    <div class="erp-zera-estoque__grid-wrap">
        <table class="erp-zera-estoque__table">
            <thead>
                <tr>
                    <th class="erp-zera-estoque__th-check"></th>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th>Grupo</th>
                    <th>Marca</th>
                    <th class="num">Estoque</th>
                    <th class="num">Qtd a zerar</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $id = (int) $row['id'];
                        $checked = $this->selecaoTodosFiltrados || isset($selecionadosSet[$id]);
                    @endphp
                    <tr wire:key="zera-row-{{ $id }}">
                        <td class="erp-zera-estoque__td-check">
                            <input
                                type="checkbox"
                                @checked($checked)
                                wire:click.prevent="toggleSelecionado({{ $id }})"
                            >
                        </td>
                        <td>{{ $row['codigo'] }}</td>
                        <td class="erp-zera-estoque__desc">{{ $row['descricao'] }}</td>
                        <td>{{ $row['grupo'] }}</td>
                        <td>{{ $row['marca'] }}</td>
                        <td class="num @if ($row['estoque_atual'] < 0) is-neg @endif">
                            {{ number_format((float) $row['estoque_atual'], 3, ',', '.') }}
                        </td>
                        <td class="num">
                            {{ number_format((float) $row['qtd_zerar'], 3, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="erp-zera-estoque__empty">
                            @if ($this->pesquisado)
                                Nenhum produto corresponde aos filtros.
                            @else
                                Pressione F5 Consulta para carregar os produtos. A zeragem só ocorre após confirmação.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="erp-zera-estoque__footer">
        <div class="erp-zera-estoque__pager">
            @if ($this->pesquisado && $total > 0)
                <span>{{ $total }} produto(s)</span>
                <button type="button" class="erp-zera-estoque__btn" wire:click="irParaPagina({{ max(1, $page - 1) }})" @disabled($page <= 1)>‹</button>
                <span>{{ $page }} / {{ $lastPage }}</span>
                <button type="button" class="erp-zera-estoque__btn" wire:click="irParaPagina({{ min($lastPage, $page + 1) }})" @disabled($page >= $lastPage)>›</button>
            @endif
        </div>

        <div class="erp-zera-estoque__actions">
            <label class="erp-zera-estoque__motivo">
                <span class="erp-zera-estoque__label">Motivo <em>*</em></span>
                <input
                    type="text"
                    wire:model="motivo"
                    class="erp-zera-estoque__input"
                    maxlength="120"
                    placeholder="Balanço de Estoque"
                    autocomplete="off"
                >
            </label>
            <button
                type="button"
                class="erp-zera-estoque__btn erp-zera-estoque__btn--danger"
                wire:click="abrirConfirmacao"
                wire:loading.attr="disabled"
                @disabled($selCount === 0 || $this->executando)
            >
                Confirmar Zeragem
            </button>
        </div>
    </div>

    @if ($this->confirmOpen)
        <div class="erp-zera-estoque__modal" role="dialog" aria-modal="true" aria-labelledby="erp-zera-confirm-title">
            <div class="erp-zera-estoque__modal-backdrop" wire:click="cancelarConfirmacao"></div>
            <div class="erp-zera-estoque__modal-box">
                <h2 id="erp-zera-confirm-title" class="erp-zera-estoque__modal-title">Confirmar zeragem</h2>
                <p class="erp-zera-estoque__modal-text">
                    Serão zerados <strong>{{ (int) ($this->confirmResumo['produtos'] ?? 0) }}</strong> produto(s)
                    no depósito <strong>{{ $this->confirmResumo['deposito_label'] ?? '—' }}</strong>.
                </p>
                <ul class="erp-zera-estoque__modal-list">
                    <li>Unidades a ajustar: <strong>{{ number_format((float) ($this->confirmResumo['unidades'] ?? 0), 3, ',', '.') }}</strong></li>
                    <li>Com saldo negativo: <strong>{{ (int) ($this->confirmResumo['negativos'] ?? 0) }}</strong></li>
                    <li>Valor estimado: <strong>R$ {{ number_format((float) ($this->confirmResumo['valor_estimado'] ?? 0), 2, ',', '.') }}</strong></li>
                    <li>Motivo: <strong>{{ $this->confirmResumo['motivo'] ?? $this->motivo }}</strong></li>
                </ul>
                <p class="erp-zera-estoque__modal-warn">Esta operação registra ajuste oficial de estoque e não pode ser desfeita em lote.</p>
                <div class="erp-zera-estoque__modal-actions">
                    <button type="button" class="erp-zera-estoque__btn" wire:click="cancelarConfirmacao" @disabled($this->executando)>Cancelar</button>
                    <button
                        type="button"
                        class="erp-zera-estoque__btn erp-zera-estoque__btn--danger"
                        wire:click="confirmarZeragem"
                        wire:loading.attr="disabled"
                        @disabled($this->executando)
                    >
                        Confirmar Zeragem
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
