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
                    if (this.busy || window.__erpFvMonFiltroBusy) return;
                    // Não compete com faturar/cancelar/reabrir/lote/clonar/NFC-e.
                    // Overlay SEFAZ (DOM/flag JS): cobre o request longo sem depender só do Livewire.
                    if (this.$wire.faturarProgressOpen
                        || this.$wire.cancelarProgressOpen
                        || this.$wire.reabrirProgressOpen
                        || this.$wire.nfeLoteProgressOpen
                        || this.$wire.clonarProgressOpen
                        || this.$wire.nfceTransmitindo
                        || this.$wire.nfceImprimirConfirmOpen
                        || window.__erpSefazProgressAtivo
                        || document.querySelector('[data-erp-sefaz-progress].is-visible')) {
                        return;
                    }
                    this.busy = true;
                    // Epoch: se o usuário trocar vendedor/filtro durante o poll, a resposta
                    // antiga pode reaplicar o filtro anterior — reaplica o desejado.
                    const epoch = window.__erpFvMonFiltroEpoch || 0;
                    this.$wire.pollRefresh().finally(() => {
                        this.busy = false;
                        const epochAgora = window.__erpFvMonFiltroEpoch || 0;
                        const desejado = window.__erpFvMonFiltroDesejado;
                        if (epochAgora === epoch || desejado == null) return;
                        if (String(this.$wire.filtroValor ?? '') === String(desejado)) return;
                        if (typeof window.__erpFvMonAplicarFiltroValor === 'function') {
                            window.__erpFvMonAplicarFiltroValor(this.$wire, desejado);
                        }
                    });
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
                                    this.q = id === 'todos' ? '' : nome;
                                    this.open = false;
                                    window.__erpFvMonAplicarFiltroValor(this.$wire, id);
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
                    @elseif ($this->filtroCampo === 'meio_pgto')
                        @php
                            $meioCombo = $this->filtroMeioPgtoCombo();
                        @endphp
                        <div
                            class="erp-fv-mon__combo erp-fv-mon__combo--select erp-fv-mon__combo--multi"
                            wire:key="filtro-meio-pgto-multi"
                            x-data="{
                                open: false,
                                itens: @js($meioCombo['itens']),
                                selecionados: @js($meioCombo['selecionados']),
                                aplicado: @js($meioCombo['aplicado']),
                                rotulo() {
                                    const n = this.selecionados.length;
                                    if (n === 0) return '<todos>';
                                    if (n === 1) {
                                        const op = this.itens.find(i => i.id === this.selecionados[0]);
                                        return op ? op.nome : this.selecionados[0];
                                    }
                                    return n + ' selecionados';
                                },
                                marcado(id) {
                                    return this.selecionados.includes(id);
                                },
                                sync(id, on) {
                                    const i = this.selecionados.indexOf(id);
                                    if (on && i < 0) this.selecionados.push(id);
                                    if (! on && i >= 0) this.selecionados.splice(i, 1);
                                },
                                limpar() {
                                    this.selecionados = [];
                                    this.fechar(true);
                                },
                                abrir() { this.open = true; },
                                fechar(force = false) {
                                    if (! this.open && ! force) return;
                                    this.open = false;
                                    this.aplicar();
                                },
                                aplicar() {
                                    const valor = this.selecionados.length ? this.selecionados.join('|') : '';
                                    if (valor === this.aplicado) return;
                                    this.aplicado = valor;
                                    window.__erpFvMonAplicarFiltroValor(this.$wire, valor);
                                },
                            }"
                            @click.outside="fechar()"
                            @keydown.escape.stop="fechar()"
                        >
                            <button
                                type="button"
                                class="erp-fv-mon__select-btn"
                                @click="open ? fechar() : abrir()"
                                :aria-expanded="open"
                                title="Meio de pagamento (múltipla seleção)"
                            >
                                <span class="erp-fv-mon__select-btn-label" x-text="rotulo()"></span>
                                <span
                                    class="erp-fv-mon__multi-count"
                                    x-show="selecionados.length > 0"
                                    x-text="selecionados.length"
                                    x-cloak
                                ></span>
                                <span class="erp-fv-mon__select-btn-caret" aria-hidden="true">▾</span>
                            </button>
                            <div
                                class="erp-fv-mon__combo-panel erp-fv-mon__combo-panel--compact erp-fv-mon__combo-panel--multi"
                                x-show="open"
                                x-cloak
                                x-transition.opacity
                                @click.stop
                            >
                                <button
                                    type="button"
                                    class="erp-fv-mon__combo-item erp-fv-mon__combo-item--todos"
                                    @click="limpar()"
                                >&lt;todos&gt;</button>
                                <template x-for="op in itens" :key="op.id">
                                    <label class="erp-fv-mon__multi-option">
                                        <input
                                            type="checkbox"
                                            class="erp-fv-mon__multi-check"
                                            :checked="marcado(op.id)"
                                            @change="sync(op.id, $event.target.checked)"
                                        >
                                        <span class="erp-fv-mon__multi-option-label" x-text="op.nome"></span>
                                    </label>
                                </template>
                                <div class="erp-fv-mon__combo-empty" x-show="itens.length === 0">Nenhuma forma cadastrada</div>
                            </div>
                        </div>
                    @elseif ($this->filtroCampoTipo() === 'select')
                        <div
                            class="erp-fv-mon__combo erp-fv-mon__combo--select"
                            wire:key="filtro-select-{{ $this->filtroCampo }}-{{ $this->filtroValor }}"
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
                                    this.open = false;
                                    window.__erpFvMonAplicarFiltroValor(this.$wire, id);
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
                               placeholder="{{ $this->filtroCampo === 'pedido' || $this->filtroCampo === 'dav' ? 'Digite o nº do pedido (DAV) e Enter' : ($this->filtroCampo === 'venda' ? 'Digite o nº da venda e Enter' : 'Digite e Enter') }}">
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
            </div>

            @php
                $lojaRotulo = $this->empresaLojaRotulo;
            @endphp
            <div
                class="erp-fv-mon__loja"
                wire:key="fv-mon-loja-{{ \App\Support\Erp\ErpContext::currentEmpresaId() ?? 0 }}"
                role="status"
                aria-label="Empresa ativa: {{ $lojaRotulo }}"
                title="{{ $lojaRotulo }}"
            >
                <span class="erp-fv-mon__loja-text">{{ $lojaRotulo }}</span>
            </div>
        </div>
    </fieldset>

    @include('filament.components.erp.list-scripts', [
        'config' => $this->getErpListKeyboardConfigForView(),
    ])

    @include('filament.components.erp.form-scripts')

    @once
        @script
        <script>
            window.__erpFvMonFiltroEpoch = window.__erpFvMonFiltroEpoch || 0;
            window.__erpFvMonFiltroBusy = false;
            window.__erpFvMonFiltroDesejado = window.__erpFvMonFiltroDesejado ?? null;

            /**
             * Aplica filtroValor com proteção contra o poll 5s sobrescrever a seleção.
             * Não remonta a tela: só $wire.set + updatedFiltroValor (resetTable no PHP).
             */
            window.__erpFvMonAplicarFiltroValor = function (wire, valor) {
                const next = String(valor ?? '');
                window.__erpFvMonFiltroEpoch = (window.__erpFvMonFiltroEpoch || 0) + 1;
                window.__erpFvMonFiltroDesejado = next;
                window.__erpFvMonFiltroBusy = true;

                const done = () => { window.__erpFvMonFiltroBusy = false; };
                try {
                    const req = wire.set('filtroValor', next);
                    if (req && typeof req.finally === 'function') {
                        req.finally(done);
                    } else {
                        done();
                    }
                } catch (e) {
                    done();
                    throw e;
                }
            };
        </script>
        @endscript
    @endonce
</div>
</div>
