@php
    use App\Support\Erp\ErpAssetVersion;

    $pdvJsVersion = ErpAssetVersion::bundle();
@endphp

<div
    class="erp-pdv"
    wire:keydown.escape="handlePdvEscape"
    data-caixa-aberto="{{ $this->caixaAberto ? '1' : '0' }}"
    data-bloqueio-min="{{ $this->pdvTempoBloqueioMin ?? '' }}"
    data-exibe-f3="{{ $this->pdvExibirF3Vendedor ? '1' : '0' }}"
    data-exibe-f4="{{ $this->pdvExibirF4BuscaAvancada ? '1' : '0' }}"
    data-permite-desconto-item="{{ $this->pdvPermitirDescontoItem ? '1' : '0' }}"
    data-som-ativo="{{ $this->pdvSomAtivo ? '1' : '0' }}"
    data-exibe-mesas="{{ $this->pdvExibeMesas ? '1' : '0' }}"
    data-caixa-rapido="{{ $this->pdvCaixaRapido ? '1' : '0' }}"
    data-ler-peso-balanca="{{ $this->pdvLerPesoBalanca ? '1' : '0' }}"
    data-busca-balanca-barras="{{ $this->pdvBuscaBalancaBarras ? '1' : '0' }}"
    data-usa-tef="{{ $this->pdvUsaTef ? '1' : '0' }}"
>
    <div class="erp-pdv__header">
        <h1 class="erp-pdv__title">{{ $this->caixaTitulo }}</h1>
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
                                    @class(['erp-pdv__grid-row', 'erp-pdv__grid-row--selected' => $this->selectedCupomIndex === $index])
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
                <div class="erp-pdv__search-field">
                    <input
                        id="erp-pdv-search"
                        type="text"
                        wire:model.live.debounce.150ms="pdvSearch"
                        wire:keydown.enter.prevent="handlePdvSearchEnter"
                        class="erp-pdv__search-input"
                        data-erp-uppercase
                        @disabled(! $this->caixaAberto)
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
                            @readonly($this->pdvLaunchStep !== 'preco' || $this->pdvBloquearPreco)
                            autocomplete="off"
                        >
                    </div>
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Subtotal</span>
                        <span class="erp-pdv__total-value">R$ {{ $this->pdvLaunchItemTotal }}</span>
                    </div>
                @elseif ($this->pdvCaixaRapido)
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Qtde</span>
                        <span class="erp-pdv__total-value">0</span>
                    </div>
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Preço</span>
                        <span class="erp-pdv__total-value">R$ 0,00</span>
                    </div>
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Subtotal</span>
                        <span class="erp-pdv__total-value">R$ 0,00</span>
                    </div>
                @else
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Qtde</span>
                        <span class="erp-pdv__total-value">{{ $this->cupomItemQtd }}</span>
                    </div>
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Preço</span>
                        <span class="erp-pdv__total-value">R$ {{ $this->cupomItemPreco }}</span>
                    </div>
                    <div class="erp-pdv__total-box">
                        <span class="erp-pdv__total-label">Subtotal</span>
                        <span class="erp-pdv__total-value">R$ {{ $this->cupomItemTotal }}</span>
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
                <span class="erp-pdv__tool-icon erp-pdv__tool-icon--exit">⎋</span>
                <span class="erp-pdv__tool-label"><kbd>ESC</kbd> - Sair</span>
            </button>
        </div>

        <div class="erp-pdv__toolbar-actions">
            <button type="button" wire:click="openImportarModal" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--import">↓</span>
            <span class="erp-pdv__tool-label"><kbd>F5</kbd> - Importar</span>
        </button>
        <button type="button" wire:click="cancelarCupom" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--cancel">✕</span>
            <span class="erp-pdv__tool-label"><kbd>F6</kbd> - Cancela</span>
        </button>
        <button type="button" wire:click="openFinalizarVenda" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--finish">✓</span>
            <span class="erp-pdv__tool-label"><kbd>F7</kbd> - Finaliza</span>
        </button>
        @if ($this->pdvExibirResumoCaixa)
        <button type="button" wire:click="openPdvModal('resumo')" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--resumo">📋</span>
            <span class="erp-pdv__tool-label"><kbd>F8</kbd> - Res. Caixa</span>
        </button>
        @endif
        <button type="button" wire:click="openPdvModal('sangria')" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--sangria">📄</span>
            <span class="erp-pdv__tool-label"><kbd>F9</kbd> - Sangria</span>
        </button>
        <button type="button" wire:click="openPdvModal('suprimento')" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--suprimento">📄</span>
            <span class="erp-pdv__tool-label"><kbd>F10</kbd> - Suprimento</span>
        </button>
        <button type="button" wire:click="openPersonOverlay" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--cliente">👤</span>
            <span class="erp-pdv__tool-label">Cad. Clientes</span>
        </button>
        <button type="button" wire:click="openProductOverlay" class="erp-pdv__tool-btn">
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--produto">📦</span>
            <span class="erp-pdv__tool-label">Cad. Produto</span>
        </button>
        <button
            type="button"
            wire:click="openPdvModal('options')"
            class="erp-pdv__tool-btn erp-pdv__tool-btn--options"
            id="erp-pdv-options-btn"
        >
            <span class="erp-pdv__tool-icon erp-pdv__tool-icon--options">☰</span>
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
        <span class="erp-pdv__status-keys" aria-label="Indicadores do teclado">
            <span id="erp-pdv-status-caps" class="erp-pdv__status-key erp-pdv__status-key--off" aria-pressed="false" title="Caps Lock">CAPS</span>
            <span id="erp-pdv-status-num" class="erp-pdv__status-key erp-pdv__status-key--off" aria-pressed="false" title="Num Lock">NUM</span>
        </span>
    </footer>

    @include('filament.components.erp.pdv.modals.options')
    @include('filament.components.erp.pdv.modals.resumo-caixa')
    @include('filament.components.erp.pdv.modals.sangria')
    @include('filament.components.erp.pdv.modals.suprimento')
    @include('filament.components.erp.pdv.modals.caixa')
    @include('filament.components.erp.pdv.modals.finalizar')
    @include('filament.components.erp.pdv.modals.excluir-item')
    @include('filament.components.erp.pdv.modals.vendedor')
    @include('filament.components.erp.pdv.modals.desconto-item')
    @include('filament.components.erp.pdv.modals.grade')
    @include('filament.components.erp.pdv.modals.serial')
    @include('filament.components.erp.pdv.modals.busca-avancada')
    @include('filament.components.erp.pdv.modals.remover-itens')
    @include('filament.components.erp.pdv.modals.autorizacao')
    @include('filament.components.erp.pdv.modals.busca-preco')
    @include('filament.components.erp.pdv.modals.importar')
    @include('filament.components.erp.pdv.modals.receber')
    @include('filament.components.erp.pdv.modals.reimprimir')
    @include('filament.components.erp.pdv.modals.consulta-venda')
    @include('filament.components.erp.pdv.modals.tabela-preco')
    @include('filament.components.erp.pdv.modals.bloqueio')
    @include('filament.components.erp.pdv.modals.sair')
    @include('filament.components.erp.pdv.modals.produto-nao-encontrado')

    @if ($this->overlayProductOpen)
        @include('filament.components.erp.pdv.overlay', [
            'title' => 'Cadastro de Produtos',
            'iframeUrl' => $this->productOverlayUrl,
            'type' => 'product',
        ])
    @endif

    @if ($this->overlayPersonOpen)
        @include('filament.components.erp.pdv.overlay', [
            'title' => 'Cadastro de Clientes',
            'iframeUrl' => $this->personOverlayUrl,
            'type' => 'person',
        ])
    @endif
</div>

@include('filament.components.erp.form-scripts')
<script src="{{ asset('js/erp-pdv.js') }}?v={{ $pdvJsVersion }}" defer></script>
