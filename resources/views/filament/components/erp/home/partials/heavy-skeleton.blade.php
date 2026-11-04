{{-- Skeleton completo (compat / fallback). --}}
@include('filament.components.erp.home.partials.heavy-skeleton-gauges')

<div class="erp-dash__layout">
    <div class="erp-dash__main">
        @include('filament.components.erp.home.partials.heavy-skeleton-charts')
        @include('filament.components.erp.home.partials.heavy-skeleton-secondary-main')
    </div>
    @include('filament.components.erp.home.partials.heavy-skeleton-aside-wrap')
</div>
