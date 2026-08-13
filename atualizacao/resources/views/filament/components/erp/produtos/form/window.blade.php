@php
    $isStandaloneFullscreen = ! ($this->embedsInPdv || $this->embedsInOrcamento || $this->embedsInNotaFornecedor);
@endphp

@if ($isStandaloneFullscreen)
    <style data-erp-produtos-viewport="v3">
        .fi-body:has(.erp-produtos-window--fullscreen) .erp-title-bar,
        .fi-body:has(.erp-produtos-window--fullscreen) .erp-status-bar,
        .fi-body:has(.erp-produtos-window--fullscreen) .erp-shell,
        .fi-body:has(.erp-produtos-window--fullscreen) .erp-menu-bar,
        .fi-body:has(.erp-produtos-window--fullscreen) .erp-shortcut-bar {
            display: none !important;
        }

        .fi-body:has(.erp-produtos-window--fullscreen) {
            background: rgba(0, 0, 0, 0.48) !important;
            overflow: hidden !important;
        }

        /*
         * Janela fixa/centralizada: altura limitada para não “esticar”
         * em monitores grandes deixando vão branco feio acima do rodapé.
         */
        .erp-produtos-window--fullscreen {
            position: fixed !important;
            top: 50% !important;
            bottom: auto !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            z-index: 50 !important;
            display: flex !important;
            flex-direction: column !important;
            width: min(calc(1280px + 4cm), calc(100vw - 0.3rem)) !important;
            max-width: calc(100vw - 0.3rem) !important;
            height: min(52rem, calc(100dvh - 0.6rem)) !important;
            min-height: 0 !important;
            max-height: calc(100dvh - 0.6rem) !important;
            margin: 0 !important;
            border-radius: 10px !important;
            overflow: hidden !important;
            box-shadow:
                0 24px 48px rgb(15 52 96 / 28%),
                0 0 0 1px rgb(255 255 255 / 35%) inset !important;
        }

        .erp-produtos-window--fullscreen .erp-produtos-window__body {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            overflow: hidden !important;
        }

        .erp-produtos-window--fullscreen .erp-produtos-pcad {
            flex: 1 1 auto !important;
            min-height: 0 !important;
        }

        .erp-produtos-window--fullscreen .erp-produtos-pcad__footer {
            flex: 0 0 auto !important;
            border-radius: 0 !important;
            border: none !important;
            border-top: 1px solid #8fa8c8 !important;
            box-shadow: none !important;
        }
    </style>
@endif

<div
    class="erp-produtos-window{{ $isStandaloneFullscreen ? ' erp-produtos-window--fullscreen' : '' }}"
    @if ($this->isEditingProduct()) wire:keydown.f7.window="openProductCardex" @endif
>
    <header class="erp-produtos-window__titlebar">
        <span class="erp-produtos-window__title">Cadastro de Produtos</span>
        <button
            type="button"
            class="erp-produtos-window__close"
            wire:click="cancelForm"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-produtos-window__body">
        @include('filament.components.erp.produtos.form.shell')
    </div>

    @if ($isStandaloneFullscreen)
        @include('filament.components.erp.produtos.form.action-bar')
    @endif
</div>
