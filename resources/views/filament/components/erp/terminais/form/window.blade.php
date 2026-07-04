<div class="erp-terminais-window">
    <header class="erp-terminais-window__titlebar">
        <span class="erp-terminais-window__title">Configurações de Terminais</span>
        <button
            type="button"
            class="erp-terminais-window__close"
            wire:click="closeScreen"
            aria-label="Fechar"
            title="ESC | Sair"
        >&times;</button>
    </header>

    <div class="erp-terminais-window__body">
        <div class="erp-terminais-master">
            @include('filament.components.erp.terminais.form.sidebar')
            @include('filament.components.erp.terminais.form.shell')
        </div>

        @include('filament.components.erp.terminais.form.action-bar')
    </div>
</div>

@include('filament.components.erp.form-scripts')

@php
    $terminaisJsPath = public_path('js/erp-terminais-form.js');
    $terminaisJsVersion = file_exists($terminaisJsPath) ? filemtime($terminaisJsPath) : time();
@endphp
<script src="{{ asset('js/erp-terminais-form.js') }}?v={{ $terminaisJsVersion }}" defer></script>
