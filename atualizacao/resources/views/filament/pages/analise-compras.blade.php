<x-filament-panels::page>
@php
    $rows = $this->rowsVisiveis();
    $cobertura = $this->coberturaDias();
    $temLinhas = count($this->rows) > 0;
    $temVisiveis = count($rows) > 0;
@endphp

<div
    class="erp-os-window erp-analise-compras-window"
    wire:keydown.f3.window.prevent="filtrar"
    wire:keydown.f6.window.prevent="gerarSugestao"
    wire:keydown.f4.window.prevent="selecionarSugestoes"
    wire:keydown.f7.window.prevent="excluirSelecionados"
    wire:keydown.f5.window.prevent="gerarPedidoCompra"
>
    <header class="erp-os-window__titlebar">
        <span>Análise e Sugestão de Compra</span>
        <a href="{{ url('/admin') }}" class="erp-os-window__close" title="ESC | Sair" aria-label="Fechar">&times;</a>
    </header>

    <div class="erp-os-window__body erp-analise-compras-body">
        <section class="erp-analise-compras-panel">
            <div class="erp-analise-compras-panel__head">
                <div>
                    <strong>Filtros da análise</strong>
                    <p>Defina o período de vendas e até quando o estoque deve cobrir.</p>
                </div>
                <span class="erp-analise-compras-cobertura">
                    Cobertura <strong>{{ $cobertura }}</strong> dia(s)
                </span>
            </div>

            <div class="erp-analise-compras-fields">
                <label class="erp-analise-compras-field">
                    <span>Período vendas (de)</span>
                    <input type="date" wire:model="dataIni">
                </label>
                <label class="erp-analise-compras-field">
                    <span>Período vendas (até)</span>
                    <input type="date" wire:model="dataFim">
                </label>
                <label class="erp-analise-compras-field">
                    <span>Suprir até</span>
                    <input type="date" wire:model.live="suprirAte">
                </label>
                <label class="erp-analise-compras-field erp-analise-compras-field--sm">
                    <span>Aprovisionamento</span>
                    <input type="number" min="0" max="365" wire:model.live="aprovisionamentoDias">
                </label>
                <label class="erp-analise-compras-field erp-analise-compras-field--produto">
                    <span>Produto</span>
                    <div class="erp-analise-compras-produto">
                        <input
                            type="search"
                            wire:model.live.debounce.200ms="produto"
                            wire:keydown.enter.prevent="confirmarProduto"
                            wire:keydown.escape.prevent="fecharSugestoesProduto"
                            wire:keydown.arrow-up.prevent="moverSugestaoProduto(-1)"
                            wire:keydown.arrow-down.prevent="moverSugestaoProduto(1)"
                            placeholder="Código, barras ou nome — ↑ ↓ Enter"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="{{ $this->produtoSugestoesOpen && $this->produtoSugestoes !== [] ? 'true' : 'false' }}"
                            data-erp-uppercase
                        >
                        @if ($this->produtoId)
                            <button type="button" class="erp-analise-compras-produto__clear" wire:click="limparProduto" title="Limpar produto" aria-label="Limpar produto">×</button>
                        @endif
                        @if ($this->produtoSugestoesOpen && $this->produtoSugestoes !== [])
                            <ul class="erp-analise-compras-produto-lookup" role="listbox" aria-label="Produtos encontrados">
                                @foreach ($this->produtoSugestoes as $index => $sug)
                                    <li wire:key="ac-prod-sug-{{ $sug['id'] }}" role="presentation">
                                        <button
                                            type="button"
                                            role="option"
                                            aria-selected="{{ $this->selectedProdutoSugestaoIndex === $index ? 'true' : 'false' }}"
                                            wire:click="selecionarProduto({{ $sug['id'] }})"
                                            @class(['is-selected' => $this->selectedProdutoSugestaoIndex === $index])
                                        >
                                            <span class="erp-analise-compras-produto-lookup__code">{{ $sug['codigo'] }}</span>
                                            <span class="erp-analise-compras-produto-lookup__nome">{{ $sug['nome'] }}</span>
                                            <span class="erp-analise-compras-produto-lookup__meta">
                                                @if ($sug['barras'] !== '')
                                                    <em>{{ $sug['barras'] }}</em>
                                                @endif
                                                <strong>Est. {{ $sug['estoque'] }}</strong>
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </label>
                <label class="erp-analise-compras-field">
                    <span>Grupo</span>
                    <select wire:model="grupo">
                        <option value="">— todos —</option>
                        @foreach ($this->grupoOptions() as $g)
                            <option value="{{ $g }}">{{ $g }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-analise-compras-field">
                    <span>Marca</span>
                    <select wire:model="marca">
                        <option value="">— todas —</option>
                        @foreach ($this->marcaOptions() as $m)
                            <option value="{{ $m }}">{{ $m }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-analise-compras-field erp-analise-compras-field--fornecedor">
                    <span>Fornecedor</span>
                    <div class="erp-analise-compras-fornecedor">
                        <input
                            type="search"
                            wire:model.live.debounce.250ms="fornecedorBusca"
                            wire:keydown.arrow-up.prevent="moverFornecedorSelecionado(-1)"
                            wire:keydown.arrow-down.prevent="moverFornecedorSelecionado(1)"
                            wire:keydown.enter.prevent="confirmarFornecedorSelecionado"
                            wire:keydown.escape.prevent="fecharLookupFornecedor"
                            placeholder="Digite nome ou CNPJ"
                            autocomplete="off"
                        >
                        @if ($this->fornecedorId)
                            <button type="button" class="erp-analise-compras-fornecedor__clear" wire:click="limparFornecedor" title="Limpar fornecedor" aria-label="Limpar fornecedor">×</button>
                        @endif
                        @if ($this->fornecedorResultados !== [])
                            <div class="erp-analise-compras-lookup" role="listbox">
                                @foreach ($this->fornecedorResultados as $index => $fornecedor)
                                    <button
                                        type="button"
                                        wire:click="selecionarFornecedor({{ $fornecedor['id'] }})"
                                        role="option"
                                        @class(['is-selected' => $this->fornecedorSelecionadoIndex === $index])
                                    >
                                        <strong>{{ $fornecedor['nome'] }}</strong>
                                        <em>{{ $fornecedor['cnpj'] !== '' ? $fornecedor['cnpj'] : 'CNPJ não informado' }}</em>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </label>
            </div>

            <div class="erp-analise-compras-checks">
                <label><input type="checkbox" wire:model="soEstoqueMinimo"> Só abaixo do estoque mínimo</label>
                <label><input type="checkbox" wire:model="usarEstoqueMinimoNaSugestao"> Usar estoque mínimo na sugestão</label>
                <label><input type="checkbox" wire:model.live="ocultarSugestaoZerada"> Ocultar sugestão zerada</label>
                <label><input type="checkbox" wire:model="ultimaCompraTodasEmpresas"> Última compra independente da empresa</label>
            </div>
        </section>

        <section class="erp-analise-compras-panel erp-analise-compras-panel--grid">
            <div class="erp-analise-compras-panel__head erp-analise-compras-panel__head--toolbar">
                <div class="erp-analise-compras-localizar">
                    <select wire:model="localizarCampo">
                        <option value="codigo">Código</option>
                        <option value="descricao">Descrição</option>
                        <option value="codigo_barras">Cód. barras</option>
                    </select>
                    <input type="search" wire:model.live.debounce.200ms="localizarTexto" placeholder="Localizar na grade…" autocomplete="off">
                    <button type="button" class="erp-analise-compras-ghost" wire:click="limparLocalizar">Limpar</button>
                </div>
                <div class="erp-analise-compras-meta">
                    Exibindo <strong>{{ count($rows) }}</strong>
                    @if (count($this->rows) !== count($rows))
                        de {{ count($this->rows) }}
                    @endif
                    produto(s)
                    @if ($this->sugestaoGerada)
                        · sugestão gerada
                    @endif
                </div>
            </div>

            <div class="erp-analise-compras-table-wrap">
                <table class="erp-analise-compras-table">
                    <thead>
                        <tr>
                            <th class="is-check">#</th>
                            <th>Código</th>
                            <th>Barras</th>
                            <th>Descrição</th>
                            <th class="is-num">Venda per.</th>
                            <th>Últ. venda</th>
                            <th class="is-num">Dias s/ venda</th>
                            <th class="is-num">Média/dia</th>
                            <th class="is-num">Estoque</th>
                            <th class="is-num">Duração</th>
                            <th class="is-num">Falta mín.</th>
                            <th>Últ. compra</th>
                            <th class="is-num">Custo cad.</th>
                            <th class="is-num">Vl. últ. compra</th>
                            <th>Últ. fornec.</th>
                            <th class="is-num">Sugestão</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $urgencia = $row['urgencia'] ?? null;
                                $badge = match ($urgencia) {
                                    'urgente' => ['Urgente', 'is-urgente'],
                                    'atencao' => ['Atenção', 'is-atencao'],
                                    'normal' => ['Normal', 'is-normal'],
                                    default => ['—', 'is-muted'],
                                };
                            @endphp
                            <tr @class(['is-selected' => $row['selected'] ?? false])>
                                <td class="is-check">
                                    <input
                                        type="checkbox"
                                        @checked($row['selected'] ?? false)
                                        wire:click="toggleSelecao({{ (int) $row['product_id'] }})"
                                    >
                                </td>
                                <td>{{ $row['codigo'] }}</td>
                                <td>{{ $row['codigo_barras'] ?: '—' }}</td>
                                <td class="is-desc" title="{{ $row['descricao'] }}">{{ $row['descricao'] }}</td>
                                <td class="is-num">{{ number_format((float) $row['venda_periodo'], 3, ',', '.') }}</td>
                                <td>{{ filled($row['data_ultima_venda']) ? \Illuminate\Support\Carbon::parse($row['data_ultima_venda'])->format('d/m/Y') : '—' }}</td>
                                <td class="is-num">{{ $row['dias_sem_venda'] ?? '—' }}</td>
                                <td class="is-num">{{ number_format((float) $row['media_diaria'], 3, ',', '.') }}</td>
                                <td class="is-num">{{ number_format((float) $row['estoque'], 3, ',', '.') }}</td>
                                <td class="is-num">{{ $row['duracao_estoque'] !== null ? number_format((float) $row['duracao_estoque'], 1, ',', '.') : '—' }}</td>
                                <td class="is-num">{{ number_format((float) $row['falta_minimo'], 3, ',', '.') }}</td>
                                <td>{{ filled($row['ultima_compra_data']) ? \Illuminate\Support\Carbon::parse($row['ultima_compra_data'])->format('d/m/Y') : '—' }}</td>
                                <td class="is-num">{{ number_format((float) $row['ultimo_custo_cadastro'], 2, ',', '.') }}</td>
                                <td class="is-num">{{ number_format((float) $row['valor_ultima_compra'], 2, ',', '.') }}</td>
                                <td class="is-desc" title="{{ $row['ult_fornecedor'] }}">{{ $row['ult_fornecedor'] ?: '—' }}</td>
                                <td class="is-num is-sugestao">
                                    {{ $row['sugestao'] !== null ? number_format((float) $row['sugestao'], 3, ',', '.') : '—' }}
                                </td>
                                <td>
                                    <span class="erp-analise-compras-badge {{ $badge[1] }}">{{ $badge[0] }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="17" class="is-empty">
                                    @if (! $temLinhas)
                                        Ajuste os filtros e pressione <strong>F3 Filtrar</strong> para carregar os produtos.
                                    @else
                                        Nenhuma linha visível com os filtros atuais (localizar / ocultar zeradas).
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <footer class="erp-os-actions">
            <button type="button" class="erp-os-actions__btn" wire:click="filtrar" wire:loading.attr="disabled" wire:target="filtrar" data-erp-key="F3" title="Filtrar (F3)">
                <span class="erp-os-actions__icon">⌕</span>
                <span class="erp-os-actions__label"><kbd>F3</kbd> | Filtrar</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn erp-os-actions__btn--save"
                wire:click="gerarSugestao"
                wire:loading.attr="disabled"
                wire:target="gerarSugestao"
                @disabled(! $temLinhas)
                data-erp-key="F6"
                title="Gerar sugestão (F6)"
            >
                <span class="erp-os-actions__icon">✦</span>
                <span class="erp-os-actions__label"><kbd>F6</kbd> | Sugestão</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn"
                wire:click="selecionarSugestoes"
                @disabled(! $this->sugestaoGerada)
                data-erp-key="F4"
                title="Selecionar linhas com sugestão (F4)"
            >
                <span class="erp-os-actions__icon">☑</span>
                <span class="erp-os-actions__label"><kbd>F4</kbd> | Seleciona</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn"
                wire:click="excluirSelecionados"
                @disabled(! $temLinhas)
                data-erp-key="F7"
                title="Excluir selecionados (F7)"
            >
                <span class="erp-os-actions__icon">✕</span>
                <span class="erp-os-actions__label"><kbd>F7</kbd> | Excluir</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn erp-analise-compras-actions__pedido"
                wire:click="gerarPedidoCompra"
                @disabled(! $this->sugestaoGerada)
                data-erp-key="F5"
                title="Gerar pedido de compra (F5)"
            >
                <span class="erp-os-actions__icon">🛒</span>
                <span class="erp-os-actions__label"><kbd>F5</kbd> | Ped. compra</span>
            </button>
            <button
                type="button"
                class="erp-os-actions__btn"
                wire:click="exportCsv"
                @disabled(! $temVisiveis)
                title="Exportar CSV das linhas exibidas"
            >
                <span class="erp-os-actions__icon">⇩</span>
                <span class="erp-os-actions__label">CSV</span>
            </button>
            <button type="button" class="erp-os-actions__btn" wire:click="limparConsulta" title="Limpar consulta">
                <span class="erp-os-actions__icon">↻</span>
                <span class="erp-os-actions__label">Limpar</span>
            </button>
            <a href="{{ url('/admin') }}" class="erp-os-actions__btn erp-os-actions__btn--exit" title="Sair">
                <span class="erp-os-actions__icon">✕</span>
                <span class="erp-os-actions__label"><kbd>ESC</kbd> | Sair</span>
            </a>
        </footer>
    </div>
</div>
</x-filament-panels::page>
