@php
    use App\Models\ForcaVendasOrder;
    use Illuminate\Support\Carbon;

    $situacaoOptions = ForcaVendasOrder::situacaoLabels();
    $periodoDeValor = filled($this->periodoDe)
        ? Carbon::parse($this->periodoDe)->format('d/m/Y')
        : '';
    $periodoAteValor = filled($this->periodoAte)
        ? Carbon::parse($this->periodoAte)->format('d/m/Y')
        : '';
@endphp

<div class="erp-fv-mon-root">
<div class="erp-nfe erp-fv-mon" wire:ignore.self>

    {{-- Cabeçalho com contador de próxima atualização --}}
    {{-- O contador dispara o poll (5s); pausa com a aba oculta e atualiza ao voltar. --}}
    <div class="erp-fv-mon__topbar">
        <span class="erp-fv-mon__topbar-title">Monitor de Recebimento de Vendas</span>
        <span
            class="erp-fv-mon__topbar-count"
            x-data="{
                s: 5,
                busy: false,
                timer: null,
                onVis: null,
                init() {
                    this.timer = setInterval(() => this.tick(), 1000);
                    this.onVis = () => { if (! document.hidden) this.poll(); };
                    document.addEventListener('visibilitychange', this.onVis);
                },
                destroy() {
                    clearInterval(this.timer);
                    document.removeEventListener('visibilitychange', this.onVis);
                },
                tick() {
                    if (document.hidden) return;
                    this.s = this.s > 0 ? this.s - 1 : 0;
                    if (this.s === 0) this.poll();
                },
                poll() {
                    this.s = 5;
                    if (this.busy) return;
                    this.busy = true;
                    this.$wire.pollRefresh().finally(() => { this.busy = false; });
                },
            }"
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
                    'markedFields' => [$this->situacaoFilter],
                    'wireProperty' => 'situacaoFilter',
                    'ariaLabel' => 'Tipo de Pedido',
                    'btnClass' => 'erp-fv-mon__dd-btn',
                    'showFlag' => true,
                ])
            </label>

            <div class="erp-fv-mon__field erp-fv-mon__field--periodo">
                <span>Período</span>
                <div
                    class="erp-fv-mon__periodo"
                    wire:ignore
                    data-erp-date-group
                    data-erp-date-auto-apply="1"
                    data-erp-date-apply-method="applyPeriodFilterAuto"
                >
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoDe"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $this->periodoDe }}"
                        value="{{ $periodoDeValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-date-input"
                    >
                    <span class="erp-fv-mon__periodo-sep">Até</span>
                    <input
                        type="text"
                        data-erp-date
                        data-wire-field="periodoAte"
                        data-erp-date-wire="iso"
                        data-erp-date-initial="{{ $this->periodoAte }}"
                        value="{{ $periodoAteValor }}"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="dd/mm/aaaa"
                        class="erp-nfe__period-input erp-date-input"
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
                    @elseif ($this->filtroCampoTipo() === 'select')
                        <div
                            class="erp-fv-mon__combo erp-fv-mon__combo--select"
                            wire:key="filtro-select-{{ $this->filtroCampo }}"
                            x-data="{
                                open: false,
                                ativo: 0,
                                valor: @js($this->filtroSelectCombo()['valor']),
                                rotulo: @js($this->filtroSelectCombo()['rotulo']),
                                todos: @js($this->filtroSelectCombo()['todos']),
                                itens: @js($this->filtroSelectCombo()['itens']),
                                opcoes() {
                                    return [{ id: 'todos', nome: this.todos }, ...this.itens];
                                },
                                abrir() {
                                    this.open = true;
                                    const idx = this.opcoes().findIndex(o => o.id === this.valor);
                                    this.ativo = idx >= 0 ? idx : 0;
                                },
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
                                    if (op) this.escolher(op.id, op.nome);
                                },
                                escolher(id, nome) {
                                    this.valor = id;
                                    this.rotulo = id === 'todos' ? this.todos : nome;
                                    this.$wire.set('filtroValor', String(id));
                                    this.open = false;
                                },
                            }"
                            @click.outside="open = false"
                            @keydown.escape.stop="open = false"
                        >
                            <button
                                type="button"
                                class="erp-fv-mon__select-btn"
                                @click="open ? open = false : abrir()"
                                @keydown.arrow-down.prevent="mover(1)"
                                @keydown.arrow-up.prevent="mover(-1)"
                                @keydown.enter.prevent="open ? confirmar() : abrir()"
                                :aria-expanded="open"
                            >
                                <span class="erp-fv-mon__select-btn-label" x-text="rotulo"></span>
                                <span class="erp-fv-mon__select-btn-caret" aria-hidden="true">▾</span>
                            </button>
                            <div class="erp-fv-mon__combo-panel erp-fv-mon__combo-panel--compact" x-ref="panel" x-show="open" x-cloak x-transition.opacity>
                                <template x-for="(op, i) in opcoes()" :key="op.id">
                                    <button type="button"
                                            class="erp-fv-mon__combo-item"
                                            :class="{ 'is-active': i === ativo || op.id === valor }"
                                            @mouseenter="ativo = i"
                                            @click="escolher(op.id, op.nome)"
                                            x-text="op.nome"></button>
                                </template>
                            </div>
                        </div>
                    @elseif ($this->filtroCampoTipo() === 'date')
                        <input type="date" wire:model.live="filtroValor" data-erp-date-wire="iso"
                               class="erp-nfe__input erp-fv-mon__filtro-valor">
                    @elseif ($this->filtroCampoTipo() === 'number')
                        <input type="text" wire:model="filtroValor" wire:keydown.enter="consultar"
                               inputmode="decimal" class="erp-nfe__input erp-fv-mon__filtro-valor"
                               placeholder="Valor mínimo e Enter">
                    @else
                        <input type="text" wire:model="filtroValor" wire:keydown.enter="consultar"
                               class="erp-nfe__input erp-fv-mon__filtro-valor" autocomplete="off"
                               placeholder="{{ $this->filtroCampo === 'pedido' || $this->filtroCampo === 'dav' ? 'Digite o nº do pedido e Enter' : 'Digite e Enter' }}">
                    @endif
                </div>
            </div>

            <label class="erp-fv-mon__field erp-fv-mon__field--plataforma">
                <span>Plataforma</span>
                @include('filament.components.erp.shared.search-field-dropdown', [
                    'fields' => ['todos' => '<todas>'] + $this->plataformaOptions(),
                    'searchColumn' => $this->plataformaFilter,
                    'markedFields' => [$this->plataformaFilter],
                    'wireProperty' => 'plataformaFilter',
                    'ariaLabel' => 'Plataforma',
                    'btnClass' => 'erp-fv-mon__dd-btn',
                    'showFlag' => true,
                ])
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
