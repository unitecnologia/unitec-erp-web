<div
    class="erp-os-window"
    x-data
    x-on:erp-os-focus-cliente.window="$nextTick(() => { const el = document.getElementById('os-cliente'); if (!el || el.disabled) return; el.removeAttribute('readonly'); el.focus(); })"
>
    <header class="erp-os-window__titlebar">
        <span>Lançamento OS</span>
        <button
            type="button"
            class="erp-os-window__close"
            wire:click="handleOsFormEscape"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-os-window__body">
        @include('filament.components.erp.ordens-servico.form.shell')
        @include('filament.components.erp.ordens-servico.form.totals')
        @include('filament.components.erp.ordens-servico.form.action-bar')
    </div>

    @include('filament.components.erp.ordens-servico.form.item-delete-confirm')
    @include('filament.components.erp.ordens-servico.form.post-save-prompt')
</div>

@include('filament.components.erp.ordens-servico.form.desconto-item')
@include('filament.components.erp.ordens-servico.form.servico-prestado-modal')

<div
    wire:ignore
    id="erp-os-cadastro-overlays"
    data-product-url="{{ $this->productOverlayUrl }}"
    data-person-url="{{ $this->personOverlayUrl }}"
></div>

@include('filament.components.erp.ordens-servico.preview-overlay')
@include('filament.components.erp.ordens-servico.print-modal')
@include('filament.components.erp.ordens-servico.form.faturamento-modal')
@include('filament.components.erp.boleto-pos-documento')
