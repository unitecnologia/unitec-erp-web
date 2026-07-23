@php
    use App\Models\ForcaVendasOrder;

    $situacaoOptions = ForcaVendasOrder::situacaoLabels();
@endphp

<div class="erp-fv-mon-root" wire:poll.5s="pollRefresh">
<div class="erp-nfe erp-fv-mon" wire:ignore.self>

    {{-- Cabeçalho com contador de próxima atualização --}}
    <div class="erp-fv-mon__topbar">
        <span class="erp-fv-mon__topbar-title">Monitor de Recebimento de Vendas</span>
        <span
            class="erp-fv-mon__topbar-count"
            x-data="{ s: 5 }"
            x-init="setInterval(() => { s = s > 0 ? s - 1 : 5 }, 1000)"
        >
            Próxima Atualização:
            <strong x-text="'00:00:' + String(s).padStart(2, '0')">00:00:05</strong>
        </span>
    </div>

    {{-- Campos para Consulta --}}
    <fieldset class="erp-fv-mon__consulta">

        {{-- Tudo em uma única linha: campos + mobile + legenda + consultar --}}
        <div class="erp-fv-mon__consulta-row">
            <div class="erp-fv-mon__inputs">
            <label class="erp-fv-mon__field erp-fv-mon__field--tipo">
                <span>Tipo de Pedido</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => ['todos' => '<todos>'] + $situacaoOptions,
                    'searchColumn' => $this->situacaoFilter,
                    'wireProperty' => 'situacaoFilter',
                    'ariaLabel' => 'Tipo de Pedido',
                    'btnClass' => 'erp-fv-mon__dd-btn',
                ])
            </label>

            <div class="erp-fv-mon__field erp-fv-mon__field--periodo">
                <span>Período</span>
                <div class="erp-fv-mon__periodo">
                    <input
                        type="date"
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        value="{{ $this->periodoDe }}"
                        class="erp-nfe__period-input"
                    >
                    <span class="erp-fv-mon__periodo-sep">Até</span>
                    <input
                        type="date"
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        value="{{ $this->periodoAte }}"
                        class="erp-nfe__period-input"
                    >
                </div>
            </div>

            <div class="erp-fv-mon__field erp-fv-mon__field--grow erp-fv-mon__field--filtro">
                <span>Filtrar por</span>
                <div class="erp-fv-mon__filtro">
                    @include('filament.components.erp.shared.search-field-dropdown', [
                        'fields' => $this->filtroCamposOptions(),
                        'searchColumn' => $this->filtroCampo,
                        'wireProperty' => 'filtroCampo',
                        'ariaLabel' => 'Filtrar por',
                        'btnClass' => 'erp-fv-mon__dd-btn erp-fv-mon__dd-btn--filtro',
                    ])

                    @php($tipoFiltro = $this->filtroCampoTipo())

                    @if ($this->filtroCampo === 'cliente')
                        <div
                            class="erp-fv-mon__combo"
                            x-data="{
                                open: false,
                                ativo: 0,
                                q: @js($this->filtroClienteNome()),
                                itens: @js($this->clientesLookupData()),
                                filtrados() {
                                    const t = this.q.toLowerCase().trim();
                                    const base = t === '' ? this.itens : this.itens.filter(c => c.busca.includes(t));
                                    return base.slice(0, 50);
                                },
                                opcoes() {
                                    return [{ id: 'todos', nome: '<todos os clientes>' }, ...this.filtrados()];
                                },
                                abrir() { this.open = true; this.ativo = 0; },
                                mover(d) {
                                    if (! this.open) { this.abrir(); return; }
                                    const total = this.opcoes().length;
                                    if (total === 0) return;
                                    this.ativo = (this.ativo + d + total) % total;
                                    this.$nextTick(() => {
                                        const el = this.$refs.panel?.querySelector('.is-active');
                                        if (el) el.scrollIntoView({ block: 'nearest' });
                                    });
                                },
                                confirmar() {
                                    const op = this.opcoes()[this.ativo];
                                    if (op) this.escolher(op.id, op.id === 'todos' ? '' : op.nome);
                                },
                                escolher(id, nome) {
                                    this.$wire.set('filtroValor', String(id));
                                    this.q = id === 'todos' ? '' : nome;
                                    this.open = false;
                                },
                            }"
                            @click.outside="open = false"
                            @keydown.escape.stop="open = false"
                        >
                            <input type="text" x-model="q"
                                   @focus="abrir()" @click="abrir()"
                                   @input="open = true; ativo = 0"
                                   @keydown.arrow-down.prevent="mover(1)"
                                   @keydown.arrow-up.prevent="mover(-1)"
                                   @keydown.enter.prevent="confirmar()"
                                   class="erp-nfe__input erp-fv-mon__combo-input"
                                   placeholder="Digite nome ou CNPJ..." autocomplete="off">
                            <div class="erp-fv-mon__combo-panel" x-ref="panel" x-show="open" x-cloak x-transition.opacity>
                                <template x-for="(op, i) in opcoes()" :key="op.id">
                                    <button type="button"
                                            class="erp-fv-mon__combo-item"
                                            :class="{ 'is-active': i === ativo }"
                                            @mouseenter="ativo = i"
                                            @click="escolher(op.id, op.id === 'todos' ? '' : op.nome)"
                                            x-text="op.nome"></button>
                                </template>
                                <div class="erp-fv-mon__combo-empty" x-show="filtrados().length === 0 && q.trim() !== ''">Nenhum cliente encontrado</div>
                            </div>
                        </div>
                    @elseif ($tipoFiltro === 'select')
                        <select wire:model.live="filtroValor" class="erp-nfe__select erp-fv-mon__filtro-valor">
                            <option value="todos">&lt;todos&gt;</option>
                            @foreach ($this->filtroValorOptions() as $id => $nome)
                                <option value="{{ $id }}">{{ $nome }}</option>
                            @endforeach
                        </select>
                    @elseif ($tipoFiltro === 'date')
                        <input type="date" wire:model.live="filtroValor" data-erp-date-wire="iso"
                               class="erp-nfe__input erp-fv-mon__filtro-valor">
                    @elseif ($tipoFiltro === 'number')
                        <input type="text" wire:model="filtroValor" wire:keydown.enter="consultar"
                               inputmode="decimal" class="erp-nfe__input erp-fv-mon__filtro-valor"
                               placeholder="Valor mínimo e Enter">
                    @else
                        <input type="text" wire:model="filtroValor" wire:keydown.enter="consultar"
                               class="erp-nfe__input erp-fv-mon__filtro-valor" autocomplete="off"
                               placeholder="{{ $this->filtroCampo === 'dav' ? 'Digite o nº da DAV e Enter' : 'Digite a identificação e Enter' }}">
                    @endif
                </div>
            </div>

            <label class="erp-fv-mon__field erp-fv-mon__field--plataforma">
                <span>Plataforma</span>
                <select wire:model.live="plataformaFilter" class="erp-nfe__select">
                    <option value="todos">&lt;todas&gt;</option>
                    @foreach ($this->plataformaOptions() as $valor => $rotulo)
                        <option value="{{ $valor }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            </label>

            <fieldset class="erp-fv-mon__plataformas erp-fv-mon__plataformas--status">
                <legend class="erp-fv-mon__plataformas-legend">Status</legend>
                <div class="erp-fv-mon__legend">
                    <span class="erp-fv-mon__legend-item"><i class="erp-fv-mon__dot erp-fv-mon__dot--pendente"></i> Pendente</span>
                    <span class="erp-fv-mon__legend-item"><i class="erp-fv-mon__dot erp-fv-mon__dot--financeiro"></i> Financeiro</span>
                    <span class="erp-fv-mon__legend-item"><i class="erp-fv-mon__dot erp-fv-mon__dot--confirmado"></i> Confirmado</span>
                    <span class="erp-fv-mon__legend-item"><i class="erp-fv-mon__dot erp-fv-mon__dot--faturado"></i> Faturado</span>
                    <span class="erp-fv-mon__legend-item"><i class="erp-fv-mon__dot erp-fv-mon__dot--cancelado"></i> Cancelado</span>
                </div>
            </fieldset>
            </div>
        </div>
    </fieldset>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')
</div>
</div>
