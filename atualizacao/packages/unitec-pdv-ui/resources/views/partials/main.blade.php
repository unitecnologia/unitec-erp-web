{{--
    Miolo compartilhado da tela do PDV (header + corpo + toolbar + rodapé).
    FONTE ÚNICA: incluído tanto pelo PDV do ERP quanto pelo PDV offline via
    @include('pdvui::partials.main'). Depende de propriedades/métodos expostos
    pelo componente ($this->...) — no ERP pela página Filament, no offline pelo
    trait Unitec\PdvUi\Concerns\ProvidesPdvScreenDefaults.
--}}
<div
    class="erp-pdv__header"
    data-marquee="{{ filled($this->pdvMarqueeTexto) ? '1' : '0' }}"
    data-venda-andamento="{{ count($this->cupomItens) > 0 ? '1' : '0' }}"
>
    {{-- Padrão: título do caixa. Após 30s parado e sem venda, o JS troca pelo letreiro. --}}
    <h1 class="erp-pdv__title">{{ $this->caixaTitulo }}</h1>

    @if (filled($this->pdvMarqueeTexto))
        <div class="erp-pdv__marquee" aria-hidden="true">
            <div class="erp-pdv__marquee-track">
                <span class="erp-pdv__title erp-pdv__marquee-text">{{ $this->pdvMarqueeTexto }}</span>
                <span class="erp-pdv__title erp-pdv__marquee-text">{{ $this->pdvMarqueeTexto }}</span>
            </div>
        </div>
    @endif
</div>

<div class="erp-pdv__body">
    <section class="erp-pdv__main-panel">
        <div class="erp-pdv__grid-wrap" id="erp-pdv-grid-wrap">
            @if ($this->pdvEmConsulta && $this->pdvSearchResults !== [])
                <table class="erp-pdv__grid erp-pdv__grid--consulta">
                    <colgroup>
                        <col class="erp-pdv__col-codigo">
                        <col class="erp-pdv__col-descricao">
                        <col class="erp-pdv__col-preco">
                        <col class="erp-pdv__col-qtd">
                        <col class="erp-pdv__col-und">
                        <col class="erp-pdv__col-local">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th class="erp-pdv__grid-col-num">Preço</th>
                            <th class="erp-pdv__grid-col-num">Estoque</th>
                            <th class="erp-pdv__grid-col-center">Und</th>
                            <th>Local</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pdvSearchResults as $index => $produto)
                            <tr
                                wire:click="selectSearchResult({{ $index }})"
                                wire:dblclick="addSearchResultToCupom({{ $index }})"
                                wire:key="pdv-search-{{ $produto['product_id'] ?? $index }}"
                                id="erp-pdv-search-row-{{ $index }}"
                                @class([
                                    'erp-pdv__grid-row',
                                    'erp-pdv__grid-row--selected' => $this->selectedSearchIndex === $index,
                                ])
                            >
                                <td class="erp-pdv__grid-col-codigo">{{ $produto['codigo'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-descricao">{{ $produto['descricao'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-num">{{ number_format((float) ($produto['preco'] ?? 0), 2, ',', '') }}</td>
                                <td class="erp-pdv__grid-col-num">
                                    @php $estoque = (float) ($produto['estoque'] ?? 0); @endphp
                                    {{ fmod($estoque, 1.0) === 0.0 ? (int) $estoque : number_format($estoque, 3, ',', '') }}
                                </td>
                                <td class="erp-pdv__grid-col-center">{{ $produto['unidade'] ?? 'UN' }}</td>
                                <td class="erp-pdv__grid-col-descricao">{{ $produto['localizacao'] ?? '' }}</td>
                            </tr>
                        @empty
                            <tr class="erp-pdv__grid-empty">
                                <td colspan="6">Nenhum produto encontrado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="erp-pdv__grid erp-pdv__grid--cupom">
                    <colgroup>
                        <col class="erp-pdv__col-item">
                        <col class="erp-pdv__col-codigo">
                        <col class="erp-pdv__col-barras">
                        <col class="erp-pdv__col-descricao">
                        <col class="erp-pdv__col-qtd">
                        <col class="erp-pdv__col-und">
                        <col class="erp-pdv__col-preco">
                        <col class="erp-pdv__col-total">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="erp-pdv__grid-col-center">Item</th>
                            <th>Código</th>
                            <th>Cód. Barras</th>
                            <th>Descrição</th>
                            <th class="erp-pdv__grid-col-center">Qtd</th>
                            <th class="erp-pdv__grid-col-center">Und.</th>
                            <th class="erp-pdv__grid-col-num">Preço R$</th>
                            <th class="erp-pdv__grid-col-num">Total R$</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (array_reverse($this->cupomItens, true) as $index => $item)
                            <tr
                                wire:click="selectCupomItem({{ $index }})"
                                wire:key="pdv-item-{{ $index }}-{{ $item['product_id'] ?? $index }}"
                                id="erp-pdv-cupom-row-{{ $index }}"
                                @class(['erp-pdv__grid-row', 'erp-pdv__grid-row--selected' => $this->pdvMostrarDetalheItem && $this->selectedCupomIndex === $index])
                            >
                                <td class="erp-pdv__grid-col-center">{{ $index + 1 }}</td>
                                <td class="erp-pdv__grid-col-codigo">{{ $item['codigo'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-codigo">{{ ($item['codigo_barras'] ?? '') !== '' ? $item['codigo_barras'] : '—' }}</td>
                                <td class="erp-pdv__grid-col-descricao">{{ $item['descricao'] ?? '—' }}</td>
                                <td class="erp-pdv__grid-col-center">{{ $this->formatCupomQuantidade((float) ($item['quantidade'] ?? 0)) }}</td>
                                <td class="erp-pdv__grid-col-center">{{ $item['unidade'] ?? 'UN' }}</td>
                                <td class="erp-pdv__grid-col-num">
                                    <span class="erp-pdv__preco-base">{{ number_format((float) ($item['preco_base'] ?? $item['preco'] ?? 0), 2, ',', '') }}</span>
                                    @if (($item['desconto'] ?? 0) > 0)
                                        <span class="erp-pdv__preco-dif erp-pdv__preco-dif--desconto">-{{ number_format((float) $item['desconto'], 2, ',', '') }}</span>
                                    @elseif (($item['acrescimo'] ?? 0) > 0)
                                        <span class="erp-pdv__preco-dif erp-pdv__preco-dif--acrescimo">+{{ number_format((float) $item['acrescimo'], 2, ',', '') }}</span>
                                    @endif
                                </td>
                                <td class="erp-pdv__grid-col-num">{{ number_format((float) ($item['total'] ?? 0), 2, ',', '') }}</td>
                            </tr>
                        @empty
                            <tr class="erp-pdv__grid-empty">
                                <td colspan="8">&nbsp;</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <div class="erp-pdv__product-line" id="erp-pdv-product-name" aria-live="polite">{{ $this->pdvPreviewProductName }}</div>
    </section>

    <aside class="erp-pdv__side-panel">
        <div class="erp-pdv__product-photo" aria-label="Foto do produto">
            @if ($this->pdvPreviewFotoUrl)
                <img
                    src="{{ $this->pdvPreviewFotoUrl }}"
                    alt="Foto do produto"
                    class="erp-pdv__product-photo-img"
                    wire:key="pdv-product-foto-{{ md5($this->pdvPreviewFotoUrl) }}"
                >
            @endif
        </div>

        <fieldset class="erp-pdv__search-box">
            <legend class="erp-pdv__search-legend">Código:</legend>
            {{-- wire:ignore: Livewire não remonta o input após lançar item (mantém foco/caret). --}}
            <div
                class="erp-pdv__search-field"
                wire:ignore
                x-data="{
                    q: $wire.entangle('pdvSearch').live,
                    focusCodigo() {
                        // Não rouba o foco no passo Qtde/Preço do lançamento.
                        const step = this.$wire.pdvLaunchStep;
                        if (step === 'qtd' || step === 'preco') {
                            return;
                        }
                        const el = this.$refs.codigo;
                        if (! el || el.disabled) {
                            return;
                        }
                        try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); }
                        try {
                            const n = el.value.length;
                            el.setSelectionRange(n, n);
                        } catch (e) {}
                    },
                    onEnter() {
                        const step = this.$wire.pdvLaunchStep;
                        // Se já está em qtde/preço e o foco voltou ao código, Enter conclui o passo.
                        if (step === 'qtd') {
                            Promise.resolve(this.$wire.handlePdvLaunchQtdEnter()).catch(() => {});
                            return;
                        }
                        if (step === 'preco') {
                            const precoEl = document.getElementById('erp-pdv-launch-preco');
                            const preco = precoEl ? precoEl.value : null;
                            Promise.resolve(this.$wire.handlePdvLaunchPrecoEnter(preco)).catch(() => {});
                            return;
                        }

                        const done = () => {
                            const after = this.$wire.pdvLaunchStep;
                            if (after === 'qtd' || after === 'preco') {
                                return;
                            }
                            this.focusCodigo();
                            [0, 40, 100, 220, 450, 900].forEach((ms) => {
                                setTimeout(() => this.focusCodigo(), ms);
                            });
                        };
                        // Garante o termo do input no servidor antes do Enter
                        // (Livewire 3 e 4: entangle deferred pode atrasar o sync).
                        const termo = this.$refs.codigo ? this.$refs.codigo.value : (this.q || '');
                        this.q = termo;
                        Promise.resolve(this.$wire.set('pdvSearch', termo))
                            .then(() => this.$wire.handlePdvSearchEnter())
                            .finally(done);
                    },
                    init() {
                        const onRefocus = () => this.focusCodigo();
                        window.addEventListener('erp-pdv-refocus-search', onRefocus);
                        this._erpPdvSearchCleanup = () => {
                            window.removeEventListener('erp-pdv-refocus-search', onRefocus);
                        };
                    },
                    destroy() {
                        this._erpPdvSearchCleanup?.();
                    },
                }"
            >
                <input
                    x-ref="codigo"
                    id="erp-pdv-search"
                    type="text"
                    class="erp-pdv__search-input"
                    x-model="q"
                    x-bind:disabled="! $wire.caixaAberto"
                    x-on:keydown.enter.prevent="onEnter()"
                    data-erp-uppercase
                    data-erp-pdv-clickable
                    autocomplete="off"
                >
            </div>
        </fieldset>

        <div class="erp-pdv__totals">
            @if ($this->pdvShowLaunchFields)
                <div @class([
                    'erp-pdv__total-box',
                    'erp-pdv__total-box--active' => $this->pdvLaunchStep === 'qtd',
                ])>
                    <span class="erp-pdv__total-label">Qtde</span>
                    <input
                        id="erp-pdv-launch-qtd"
                        type="text"
                        wire:model.live="pdvLaunchQtd"
                        wire:keydown.enter.prevent="handlePdvLaunchQtdEnter"
                        class="erp-pdv__total-input"
                        data-mask="quantity3"
                        @readonly($this->pdvLaunchStep !== 'qtd')
                        autocomplete="off"
                    >
                </div>
                <div @class([
                    'erp-pdv__total-box',
                    'erp-pdv__total-box--active' => $this->pdvLaunchStep === 'preco',
                ])>
                    <span class="erp-pdv__total-label">Preço</span>
                    <input
                        id="erp-pdv-launch-preco"
                        type="text"
                        wire:model.blur="pdvLaunchPreco"
                        wire:keydown.enter.prevent="handlePdvLaunchPrecoEnter($event.target.value)"
                        class="erp-pdv__total-input"
                        data-mask="money"
                        @readonly($this->pdvLaunchStep !== 'preco')
                        autocomplete="off"
                    >
                </div>
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Subtotal</span>
                    <span class="erp-pdv__total-value">{{ $this->pdvLaunchItemTotal }}</span>
                </div>
            @elseif ($this->pdvMostrarDetalheItem)
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Qtde</span>
                    <span class="erp-pdv__total-value">{{ $this->cupomItemQtd }}</span>
                </div>
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Preço</span>
                    <span class="erp-pdv__total-value">{{ $this->cupomItemPreco }}</span>
                </div>
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Subtotal</span>
                    <span class="erp-pdv__total-value">{{ $this->cupomItemTotal }}</span>
                </div>
            @elseif (filled($this->pdvFlashQtd))
                <div class="erp-pdv__total-box erp-pdv__total-box--flash">
                    <span class="erp-pdv__total-label">Qtde</span>
                    <span class="erp-pdv__total-value">{{ $this->pdvFlashQtd }}</span>
                </div>
                <div class="erp-pdv__total-box erp-pdv__total-box--flash">
                    <span class="erp-pdv__total-label">Preço</span>
                    <span class="erp-pdv__total-value">{{ $this->pdvFlashPreco }}</span>
                </div>
                <div class="erp-pdv__total-box erp-pdv__total-box--flash">
                    <span class="erp-pdv__total-label">Subtotal</span>
                    <span class="erp-pdv__total-value">{{ $this->pdvFlashTotal }}</span>
                </div>
            @else
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Qtde</span>
                    <span class="erp-pdv__total-value">0</span>
                </div>
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Preço</span>
                    <span class="erp-pdv__total-value">0,00</span>
                </div>
                <div class="erp-pdv__total-box">
                    <span class="erp-pdv__total-label">Subtotal</span>
                    <span class="erp-pdv__total-value">0,00</span>
                </div>
            @endif
        </div>

        <div class="erp-pdv__total-box erp-pdv__total-box--grand">
            <span class="erp-pdv__total-label">Total</span>
            <span class="erp-pdv__total-value">R$ {{ $this->cupomTotal }}</span>
        </div>
    </aside>
</div>

<div class="erp-pdv__toolbar">
    <div class="erp-pdv__toolbar-start">
        <button type="button" wire:click="handlePdvEscape" class="erp-pdv__tool-btn erp-pdv__tool-btn--exit">
            @include('pdvui::partials.tool-icon', ['name' => 'exit'])
            <span class="erp-pdv__tool-label"><kbd>ESC</kbd> - Sair</span>
        </button>
    </div>

    @php($caixaAberto = (bool) ($this->caixaAberto ?? false))
    <div class="erp-pdv__toolbar-actions">
        <button type="button" wire:click="openImportarModal" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'import'])
            <span class="erp-pdv__tool-label"><kbd>F5</kbd> - Importar</span>
        </button>
        <button type="button" wire:click="cancelarCupom" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'cancel'])
            <span class="erp-pdv__tool-label"><kbd>F6</kbd> - Cancela</span>
        </button>
        <button type="button" wire:click="openFinalizarVenda" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'finish'])
            <span class="erp-pdv__tool-label"><kbd>F7</kbd> - Finaliza</span>
        </button>
        <button type="button" wire:click="suspenderVendaEmEspera" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'pause'])
            <span class="erp-pdv__tool-label">Suspender venda</span>
        </button>
        <button type="button" wire:click="openVendasEsperaModal" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'import'])
            <span class="erp-pdv__tool-label">Recuperar venda</span>
        </button>
        @if ($this->pdvExibirResumoCaixa)
        <button type="button" wire:click="openPdvModal('resumo')" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'resumo'])
            <span class="erp-pdv__tool-label">Res. Caixa</span>
        </button>
        @endif
        <button type="button" wire:click="openPdvModal('sangria')" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'sangria'])
            <span class="erp-pdv__tool-label">Sangria</span>
        </button>
        <button type="button" wire:click="openPdvModal('suprimento')" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'suprimento'])
            <span class="erp-pdv__tool-label"><kbd>F10</kbd> - Suprimento</span>
        </button>
        <button type="button" wire:click="openPersonOverlay" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'cliente'])
            <span class="erp-pdv__tool-label">Cad. Clientes</span>
        </button>
        <button type="button" wire:click="openProductOverlay" class="erp-pdv__tool-btn" @disabled(! $caixaAberto) title="{{ $caixaAberto ? '' : 'Abra o caixa (F2) para usar' }}">
            @include('pdvui::partials.tool-icon', ['name' => 'produto'])
            <span class="erp-pdv__tool-label">Cad. Produto</span>
        </button>
        <button
            type="button"
            wire:click="openPdvModal('options')"
            class="erp-pdv__tool-btn erp-pdv__tool-btn--options"
            id="erp-pdv-options-btn"
        >
            @include('pdvui::partials.tool-icon', ['name' => 'options'])
            <span class="erp-pdv__tool-label"><kbd>F1</kbd> - Opções</span>
        </button>
    </div>
</div>

<footer class="erp-pdv__status">
    <span>Conta: {{ $this->pdvStatusBar['conta'] }}</span>
    <span>Usuário: {{ $this->pdvStatusBar['usuario'] }}</span>
    <span>Vendedor: {{ $this->pdvStatusBar['vendedor'] }}</span>
    @if ($this->pdvHabilitarTabelaPreco)
        <span>Tab.Preço: {{ $this->pdvStatusBar['tabela_preco'] }}</span>
    @endif
    <span id="erp-pdv-status-clock">Data/Hora: {{ $this->pdvStatusBar['data_hora'] }}</span>
    @if (is_array($this->pdvStatusBar['tunel'] ?? null))
        <span class="erp-pdv__status-cloud" title="Status do túnel Cloudflare (acesso remoto)">
            <span
                @class([
                    'erp-pdv__status-cloud-dot',
                    'erp-pdv__status-cloud-dot--online' => (bool) ($this->pdvStatusBar['tunel']['online'] ?? false),
                    'erp-pdv__status-cloud-dot--offline' => ! (bool) ($this->pdvStatusBar['tunel']['online'] ?? false),
                ])
                aria-hidden="true"
            ></span>
            <strong>{{ $this->pdvStatusBar['tunel']['label'] ?? 'Offline' }}</strong>
            <span class="erp-pdv__status-cloud-detail">{{ $this->pdvStatusBar['tunel']['detail'] ?? 'Última verificação: —' }}</span>
        </span>
    @endif
    <span class="erp-pdv__status-keys" aria-label="Indicadores do teclado">
        <span id="erp-pdv-status-caps" class="erp-pdv__status-key erp-pdv__status-key--off" aria-pressed="false" title="Caps Lock">CAPS</span>
        <span id="erp-pdv-status-num" class="erp-pdv__status-key erp-pdv__status-key--off" aria-pressed="false" title="Num Lock">NUM</span>
    </span>
</footer>
