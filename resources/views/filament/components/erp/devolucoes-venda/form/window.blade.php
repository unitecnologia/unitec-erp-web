<div class="erp-orcamentos-window erp-devvenda-window">
    <header class="erp-orcamentos-window__titlebar">
        <span class="erp-orcamentos-window__title">Lançamento Devolução de Venda</span>
        <button
            type="button"
            class="erp-orcamentos-window__close"
            wire:click="handleDevolucaoFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-orcamentos-window__body">
        @include('filament.components.erp.devolucoes-venda.form.shell')
        @include('filament.components.erp.devolucoes-venda.form.action-bar')
    </div>
</div>

@include('filament.components.erp.form-scripts')

@php
    $jsPath = public_path('js/erp-devolucao-venda-form.js');
    $jsVersion = is_file($jsPath) ? (string) filemtime($jsPath) : (string) time();
@endphp
<script src="{{ asset('js/erp-devolucao-venda-form.js') }}?v={{ $jsVersion }}" defer></script>
