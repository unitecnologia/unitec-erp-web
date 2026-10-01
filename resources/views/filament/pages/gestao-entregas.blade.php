<x-filament-panels::page>
<div
    class="erp-gestao-entregas-shell"
    wire:keydown.f5.window.prevent="refreshTable"
    @if ($this->detalheOpen)
        wire:keydown.escape.window="fecharDetalhe"
    @endif
>
    <div class="erp-nfe erp-gestao-entregas" wire:ignore.self>
        <div class="erp-gestao-entregas__topbar">
            <span class="erp-gestao-entregas__topbar-title">Entregas</span>
        </div>

        <fieldset class="erp-gestao-entregas__consulta">
            <legend>Filtros</legend>
            <div class="erp-gestao-entregas__consulta-bar">
                <div class="erp-gestao-entregas__field erp-gestao-entregas__field--periodo">
                    <span>Período</span>
                    <div
                        class="erp-gestao-entregas__periodo"
                        data-erp-date-group
                        data-erp-date-auto-apply="1"
                        data-erp-date-apply-method="consultar"
                    >
                        <input
                            type="date"
                            data-wire-field="periodoDe"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoDe }}"
                            data-erp-date
                            value="{{ $this->periodoDe }}"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="dd/mm/aaaa"
                            class="erp-nfe__period-input erp-date-input"
                            aria-label="Período inicial"
                        >
                        <span class="erp-gestao-entregas__periodo-sep">até</span>
                        <input
                            type="date"
                            data-wire-field="periodoAte"
                            data-erp-date-wire="iso"
                            data-erp-date-initial="{{ $this->periodoAte }}"
                            data-erp-date
                            value="{{ $this->periodoAte }}"
                            inputmode="numeric"
                            autocomplete="off"
                            placeholder="dd/mm/aaaa"
                            class="erp-nfe__period-input erp-date-input"
                            aria-label="Período final"
                        >
                    </div>
                </div>

                <div class="erp-gestao-entregas__field erp-gestao-entregas__field--numero">
                    <span>Nº carga</span>
                    <div class="erp-gestao-entregas__numero-wrap">
                        <input
                            type="text"
                            wire:model.live.debounce.350ms="numeroCarga"
                            onkeydown="if (event.key === 'Enter') window.ErpDatepicker?.commitAllIn(this.closest('.erp-gestao-entregas') ?? document)"
                            class="erp-nfe__input"
                            placeholder="Nº"
                            inputmode="numeric"
                            autocomplete="off"
                        >
                        <button
                            type="button"
                            class="erp-gestao-entregas__lupa"
                            wire:click="abrirCargaLookup"
                            title="Cargas na rua (em entrega)"
                            aria-label="Buscar cargas em entrega"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"/>
                                <path d="M20 20l-3.5-3.5"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <label class="erp-gestao-entregas__field erp-gestao-entregas__field--entregador">
                    <span>Entregador</span>
                    <select wire:model.live="entregadorUserId" class="erp-nfe__select">
                        <option value="">Todos</option>
                        @foreach ($this->entregadorOptions() as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-gestao-entregas__field erp-gestao-entregas__field--veiculo">
                    <span>Veículo</span>
                    <select wire:model.live="veiculoId" class="erp-nfe__select">
                        <option value="">Todos</option>
                        @foreach ($this->veiculoOptions() as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['label'] }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-gestao-entregas__field erp-gestao-entregas__field--status">
                    <span>Status</span>
                    <select wire:model.live="statusFilter" class="erp-nfe__select">
                        <option value="todos">Todos</option>
                        <option value="pendente">Pendente</option>
                        <option value="entregue">Entregue</option>
                        <option value="parcial">Parcial</option>
                        <option value="nao_entregue">Não entregue</option>
                    </select>
                </label>

                <div class="erp-gestao-entregas__legend" title="Legenda de status">
                    <span class="erp-gestao-entregas__legend-title">Status</span>
                    <span class="erp-gestao-entregas__legend-item">
                        <i class="erp-gestao-entregas__dot erp-gestao-entregas__dot--pendente"></i> Pendente
                    </span>
                    <span class="erp-gestao-entregas__legend-item">
                        <i class="erp-gestao-entregas__dot erp-gestao-entregas__dot--entregue"></i> Entregue
                    </span>
                    <span class="erp-gestao-entregas__legend-item">
                        <i class="erp-gestao-entregas__dot erp-gestao-entregas__dot--parcial"></i> Parcial
                    </span>
                    <span class="erp-gestao-entregas__legend-item">
                        <i class="erp-gestao-entregas__dot erp-gestao-entregas__dot--nao"></i> Não entregue
                    </span>
                </div>
            </div>
        </fieldset>

        <div class="erp-gestao-entregas__grid-area">
            <table class="erp-gestao-entregas__table">
                <thead>
                    <tr>
                        <th>Carga</th>
                        <th>Pedido</th>
                        <th>Cliente</th>
                        <th>Entregador</th>
                        <th>Status</th>
                        <th>Data/Hora</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->rows as $row)
                        <tr
                            class="erp-gestao-entregas__row"
                            wire:key="entrega-{{ $row['carga_id'] }}-{{ $row['pedido_id'] }}"
                            wire:click="abrirDetalhe({{ $row['carga_id'] }}, {{ $row['pedido_id'] }})"
                        >
                            <td class="num">{{ $row['carga_numero'] }}</td>
                            <td class="num">{{ $row['pedido_numero'] }}</td>
                            <td class="nome">{{ $row['cliente'] }}</td>
                            <td>{{ $row['entregador'] }}</td>
                            <td>
                                <span @class([
                                    'erp-gestao-entregas__badge',
                                    'erp-gestao-entregas__badge--entregue' => $row['status'] === 'entregue',
                                    'erp-gestao-entregas__badge--parcial' => $row['status'] === 'parcial',
                                    'erp-gestao-entregas__badge--nao' => $row['status'] === 'nao_entregue',
                                    'erp-gestao-entregas__badge--pendente' => $row['status'] === 'pendente',
                                ])>
                                    @if ($row['status'] === 'entregue')
                                        Entregue
                                    @elseif ($row['status'] === 'parcial')
                                        Parcial
                                    @elseif ($row['status'] === 'nao_entregue')
                                        Não entregue
                                    @else
                                        Pendente
                                    @endif
                                </span>
                            </td>
                            <td class="num">{{ $row['concluida_em'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">Nenhuma entrega encontrada no período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="erp-gestao-entregas__resultados">
            <span><strong>Resultados:</strong> {{ count($this->rows) }}</span>
        </div>
    </div>
</div>

{{-- Barra fixa no rodapé da viewport (fora de qualquer overflow) --}}
<div class="erp-nfe-actions erp-gestao-entregas-actions" wire:ignore.self>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn" data-erp-key="F5">
        <span class="erp-nfe-actions__icon">↻</span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">✕</span>
        <span class="erp-nfe-actions__label">Sair</span>
    </button>
</div>

@if ($this->cargaLookupOpen)
    <div class="erp-lookup-modal erp-gestao-entregas-carga-lookup" wire:keydown.escape.window="fecharCargaLookup">
        <div class="erp-lookup-modal__backdrop" wire:click="fecharCargaLookup"></div>
        <div
            class="erp-lookup-modal__window erp-gestao-entregas-carga-lookup__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-ge-carga-lookup-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-ge-carga-lookup-title">Cargas na rua (em entrega)</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharCargaLookup" title="Fechar">✕</button>
            </div>
            <div class="erp-lookup-modal__body">
                <p class="erp-gestao-entregas-carga-lookup__hint">
                    Cargas abertas ou fechadas. Clique para filtrar a consulta.
                </p>
                <div class="erp-lookup-modal__grid-wrap">
                    <table class="erp-lookup-modal__grid">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>Data</th>
                                <th>Entregador</th>
                                <th>Pedidos</th>
                                <th>Entregues</th>
                                <th>Não</th>
                                <th>Pendentes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->cargaLookupRows as $carga)
                                <tr
                                    class="erp-lookup-modal__row"
                                    wire:key="carga-lookup-{{ $carga['id'] }}"
                                    wire:click="selecionarCargaLookup('{{ $carga['numero'] }}')"
                                >
                                    <td>{{ $carga['numero'] }}</td>
                                    <td>{{ $carga['data'] }}</td>
                                    <td>{{ $carga['entregador'] }}</td>
                                    <td class="num">{{ $carga['pedidos'] }}</td>
                                    <td class="num">{{ $carga['entregues'] }}</td>
                                    <td class="num">{{ $carga['nao_entregues'] }}</td>
                                    <td class="num">{{ $carga['pendentes'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="erp-lookup-modal__empty">
                                        Nenhuma carga em andamento no momento.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="erp-lookup-modal__actions">
                <button type="button" wire:click="fecharCargaLookup" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif

@if ($this->detalheOpen && $this->detalhe)
    @php $d = $this->detalhe; @endphp
    <div class="erp-lookup-modal erp-gestao-entregas-modal">
        <div class="erp-lookup-modal__backdrop" wire:click="fecharDetalhe"></div>
        <div
            class="erp-lookup-modal__window erp-gestao-entregas-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-gestao-entrega-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-gestao-entrega-title">ENTREGA DO PEDIDO {{ $d['pedido_numero'] }}</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="fecharDetalhe" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-gestao-entregas-modal__body">
                <div class="erp-gestao-entregas-modal__meta">
                    <div><strong>Carga:</strong> {{ $d['carga_numero'] }}</div>
                    <div><strong>Cliente:</strong> {{ $d['cliente'] }}</div>
                    <div><strong>Entregador:</strong> {{ $d['entregador'] }}</div>
                    <div><strong>Data/Hora:</strong> {{ $d['concluida_em'] ?? '—' }}</div>
                </div>

                <div @class([
                    'erp-gestao-entregas-modal__status',
                    'is-entregue' => $d['status'] === 'entregue',
                    'is-nao' => $d['status'] === 'nao_entregue',
                    'is-pendente' => $d['status'] === 'pendente',
                ])>
                    @if ($d['status'] === 'entregue')
                        ✓ ENTREGUE
                    @elseif ($d['status'] === 'nao_entregue')
                        ⚠ NÃO ENTREGUE
                    @else
                        ● PENDENTE
                    @endif
                </div>

                @if ($d['status'] === 'nao_entregue')
                    <div class="erp-gestao-entregas-modal__block">
                        <strong>Motivo:</strong>
                        <div>{{ $d['motivo'] ?? '—' }}</div>
                    </div>
                @endif

                @if (filled($d['observacao'] ?? null))
                    <div class="erp-gestao-entregas-modal__block">
                        <strong>Observação:</strong>
                        <div>{{ $d['observacao'] }}</div>
                    </div>
                @endif

                @if (! empty($d['itens'] ?? []))
                    <div class="erp-gestao-entregas-modal__block">
                        <strong>Itens entregues</strong>
                        <table class="erp-gestao-entregas-modal__itens">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Original</th>
                                    <th>Entregue</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['itens'] as $it)
                                    <tr>
                                        <td>
                                            <div class="erp-gestao-entregas-modal__item-desc">{{ $it['descricao'] }}</div>
                                            <div class="erp-gestao-entregas-modal__item-cod">Cód. {{ $it['codigo'] }}</div>
                                        </td>
                                        <td class="num">{{ number_format((float) $it['quantidade_original'], 3, ',', '.') }} {{ $it['unidade'] }}</td>
                                        <td class="num">{{ number_format((float) $it['quantidade'], 3, ',', '.') }} {{ $it['unidade'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if (filled($d['foto_url'] ?? null))
                    <div class="erp-gestao-entregas-modal__block">
                        <strong>FOTO</strong>
                        <div class="erp-gestao-entregas-modal__foto">
                            <img src="{{ $d['foto_url'] }}" alt="Foto da entrega" loading="lazy">
                        </div>
                    </div>
                @endif

                @if (filled($d['assinatura_url'] ?? null))
                    <div class="erp-gestao-entregas-modal__block">
                        <strong>ASSINATURA</strong>
                        <div class="erp-gestao-entregas-modal__foto erp-gestao-entregas-modal__foto--assinatura">
                            <img src="{{ $d['assinatura_url'] }}" alt="Assinatura da entrega" loading="lazy">
                        </div>
                    </div>
                @endif

                @if (
                    blank($d['foto_url'] ?? null)
                    && blank($d['assinatura_url'] ?? null)
                    && ($d['status'] ?? '') === 'pendente'
                )
                    <p class="erp-gestao-entregas-modal__hint">Aguardando ocorrência do aplicativo.</p>
                @endif
            </div>

            <div class="erp-lookup-modal__actions">
                <button type="button" wire:click="fecharDetalhe" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
@endif

@include('filament.components.erp.form-scripts')
</x-filament-panels::page>
