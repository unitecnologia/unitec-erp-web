@php
    use App\Models\PersonVisitaDia;

    $vendedores = $this->vendedoresOptions();
    $vendedor = $this->vendedorSelecionado();
    $clientes = $this->clientesDoVendedor();
    $marcados = $this->contagemMarcados();
    $diaLabel = PersonVisitaDia::diasLabels()[$this->diaSemana] ?? '';
@endphp

<div class="erp-rotas" wire:ignore.self>
    <div class="erp-rotas__locate">
        <span class="erp-rotas__locate-label">Filtros</span>
        <div class="erp-rotas__locate-controls">
            <label class="erp-rotas__inline">
                <span>Vendedor</span>
                <select wire:model.live="vendedorId" class="erp-rotas__select">
                    <option value="">— Selecione o vendedor —</option>
                    @foreach ($vendedores as $item)
                        <option value="{{ $item->id }}">{{ $item->codigo }} - {{ $item->nome }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-rotas__inline erp-rotas__inline--grow">
                <span>Cliente</span>
                <input
                    type="text"
                    wire:model.live.debounce.300ms="searchClientes"
                    class="erp-rotas__input"
                    placeholder="Pesquisar cliente"
                    @disabled(! $vendedor)
                >
            </label>

            <label class="erp-rotas__inline">
                <span>Lista</span>
                <select wire:model.live="filtroLista" class="erp-rotas__select erp-rotas__select--sm" @disabled(! $vendedor)>
                    <option value="todos">Todos</option>
                    <option value="marcados">Marcados no dia</option>
                    <option value="nao_marcados">Não marcados</option>
                </select>
            </label>
        </div>
    </div>

    @if ($vendedor)
        <div class="erp-rotas__summary">
            <strong>{{ $vendedor->nome }}</strong>
            <span>· {{ $diaLabel }}</span>
            <span class="erp-rotas__count">{{ $marcados }} marcado{{ $marcados === 1 ? '' : 's' }}</span>
            @if ($this->draftDirty)
                <span class="erp-rotas__dirty">alterações não salvas</span>
            @endif
        </div>

        <div class="erp-rotas__table-wrap erp-rotas__table-wrap--full">
            <table class="erp-rotas__table">
                <thead>
                    <tr>
                        <th class="erp-rotas__col-check">{{ $diaLabel }}</th>
                        <th class="erp-rotas__col-ordem">Ordem</th>
                        <th>Cliente</th>
                        <th class="erp-rotas__col-endereco">Endereço</th>
                        <th class="erp-rotas__col-cidade">Cidade</th>
                        <th class="erp-rotas__col-fone">Telefone</th>
                        <th class="erp-rotas__col-mapa">Mapa</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientes as $cliente)
                        @php
                            $naRota = filled($cliente->visita_id);
                            $endereco = $this->enderecoCliente($cliente);
                            $mapsUrl = $this->googleMapsUrl($cliente);
                        @endphp
                        <tr
                            class="{{ $this->selectedClienteId === (int) $cliente->id ? 'is-selected' : '' }} {{ $naRota ? 'is-marked' : '' }}"
                            wire:click="selectCliente({{ $cliente->id }})"
                        >
                            <td class="erp-rotas__col-check" wire:click.stop>
                                <label class="erp-rotas__check">
                                    <input
                                        type="checkbox"
                                        @checked($naRota)
                                        wire:change="toggleCliente({{ $cliente->id }}, $event.target.checked)"
                                    >
                                    <span>Visita</span>
                                </label>
                            </td>
                            <td class="erp-rotas__col-ordem">
                                @if ($naRota)
                                    <div class="erp-rotas__ordem" wire:click.stop>
                                        <button type="button" class="erp-rotas__icon-btn" title="Subir" wire:click="moveCliente({{ $cliente->id }}, 'up')">▲</button>
                                        <input
                                            type="number"
                                            min="1"
                                            class="erp-rotas__ordem-input"
                                            value="{{ $cliente->visita_ordem }}"
                                            wire:change="setOrdem({{ $cliente->id }}, $event.target.value)"
                                        >
                                        <button type="button" class="erp-rotas__icon-btn" title="Descer" wire:click="moveCliente({{ $cliente->id }}, 'down')">▼</button>
                                    </div>
                                @else
                                    <span class="erp-rotas__muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="erp-rotas__cliente-name">{{ $cliente->nome_razao }}</div>
                                <div class="erp-rotas__cliente-code">{{ $cliente->codigo }}</div>
                            </td>
                            <td class="erp-rotas__col-endereco" title="{{ $endereco }}">{{ $endereco }}</td>
                            <td class="erp-rotas__col-cidade">
                                {{ $cliente->cidade_nome ?: '—' }}@if ($cliente->uf)/{{ $cliente->uf }}@endif
                            </td>
                            <td class="erp-rotas__col-fone">{{ $this->telefoneCliente($cliente) }}</td>
                            <td class="erp-rotas__col-mapa" wire:click.stop>
                                @if ($mapsUrl)
                                    <a
                                        href="{{ $mapsUrl }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="erp-rotas__map-btn"
                                        title="Abrir no Google Maps"
                                    >
                                        🗺
                                    </a>
                                @else
                                    <span class="erp-rotas__muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="erp-rotas__empty-cell">
                                Nenhum cliente vinculado a este vendedor (campo Vendedor Força de Vendas).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
        <div class="erp-rotas__placeholder">
            <strong>Selecione o vendedor</strong>
            <span>Depois escolha o dia da semana e marque os clientes que ele visita nesse dia.</span>
        </div>
    @endif
</div>
