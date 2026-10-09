{{--
    Painel Mesas do PDV (terminal com flag "Mesas"). Renderizado e atualizado só pelo
    erp-pdv-mesas.js (wire:ignore): o pulso de status não re-renderiza a página do PDV.
--}}
@php
    $painel = $this->pdvMesasPainel;
    $mesasCssPath = public_path('css/erp-pdv-mesas.css');
    $mesasJsPath = public_path('js/erp-pdv-mesas.js');
    $mesasCssVersion = is_file($mesasCssPath) ? (int) filemtime($mesasCssPath) : time();
    $mesasJsVersion = is_file($mesasJsPath) ? (int) filemtime($mesasJsPath) : time();
@endphp

@assets
    <link rel="stylesheet" href="{{ asset('css/erp-pdv-mesas.css') }}?v={{ $mesasCssVersion }}">
    <script src="{{ asset('js/erp-pdv-mesas.js') }}?v={{ $mesasJsVersion }}" defer></script>
@endassets

<aside
    class="erp-pdv-mesas"
    wire:ignore
    data-erp-pdv-mesas
    data-quantidade="{{ $painel['quantidade'] }}"
    data-inatividade="{{ $painel['inatividade'] }}"
    data-url="{{ $painel['url'] }}"
    data-credencial="{{ $painel['credencial'] }}"
    data-mesa-numero="{{ $painel['mesa']['numero'] ?? '' }}"
    data-mesa-id="{{ $painel['mesa']['id'] ?? '' }}"
    data-mesa-token="{{ $painel['mesa']['token'] ?? '' }}"
    data-mesa-aguardando="{{ ! empty($painel['mesa']['aguardando']) ? '1' : '0' }}"
    aria-label="Mesas"
>
    <header class="erp-pdv-mesas__header">
        <button type="button" class="erp-pdv-mesas__toggle" data-mesas-toggle title="Recolher/expandir painel de mesas">
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 5h14v2H3zm0 4h14v2H3zm0 4h14v2H3z"/></svg>
        </button>
        <span class="erp-pdv-mesas__title">Mesas</span>
        <span class="erp-pdv-mesas__count" data-mesas-count title="Mesas ocupadas">0</span>
        <button type="button" class="erp-pdv-mesas__mini" data-mesas-mini hidden></button>
    </header>

    <div class="erp-pdv-mesas__content">
        <label class="erp-pdv-mesas__abrir">
            <span class="erp-pdv-mesas__abrir-label">Abrir nº</span>
            <input
                type="text"
                inputmode="numeric"
                maxlength="3"
                class="erp-pdv-mesas__abrir-input"
                data-mesas-numero
                data-erp-pdv-clickable
                autocomplete="off"
                placeholder="—"
            >
        </label>

        <div class="erp-pdv-mesas__grid" data-mesas-grid role="listbox" aria-label="Mesas (duplo clique para abrir)"></div>

        <div class="erp-pdv-mesas__atual" data-mesas-atual hidden>
            <span class="erp-pdv-mesas__atual-label" data-mesas-atual-label></span>
            <span class="erp-pdv-mesas__atual-status" data-mesas-atual-status hidden>Aguardando fechamento</span>
            <div class="erp-pdv-mesas__atual-actions">
                <button type="button" class="erp-pdv-mesas__btn" data-mesas-pedido title="Imprimir pedido da mesa (Ctrl+S)">Pedido</button>
                <button type="button" class="erp-pdv-mesas__btn" data-mesas-parcial title="Imprimir pré-conta (parcial)">Parcial</button>
                <button type="button" class="erp-pdv-mesas__btn erp-pdv-mesas__btn--danger erp-pdv-mesas__btn--wide" data-mesas-reimprimir hidden title="Reimprimir a pré-conta">Reimprimir Parcial</button>
                <button type="button" class="erp-pdv-mesas__btn" data-mesas-reabrir hidden title="Reabrir a mesa para lançamentos">Reabrir</button>
                <button type="button" class="erp-pdv-mesas__btn" data-mesas-transferir title="Transferir mesa (Ctrl+B)">Transferir</button>
                <button type="button" class="erp-pdv-mesas__btn erp-pdv-mesas__btn--ghost" data-mesas-balcao title="Sair da mesa; os itens continuam gravados">Balcão</button>
            </div>
        </div>

        <ul class="erp-pdv-mesas__legenda" aria-hidden="true">
            <li><i class="is-livre"></i>Livre</li>
            <li><i class="is-ocupada"></i>Ocupada</li>
            <li><i class="is-selecionada"></i>Aberta aqui</li>
            <li><i class="is-bloqueada"></i>Outro terminal</li>
            <li class="is-largo"><i class="is-aguardando"></i>Aguardando fechamento</li>
        </ul>
    </div>
</aside>
