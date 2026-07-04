@if (filament()->auth()->check() && ! request()->boolean('pdv'))
    <header class="erp-shell">
        @include('filament.components.erp.title-bar')
        @include('filament.components.erp.menu-bar')
        @include('filament.components.erp.shortcut-bar')
    </header>
    @include('filament.components.erp.update-modal')
@endif
