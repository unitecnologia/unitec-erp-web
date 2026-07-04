<div class="erp-title-bar">
    <div class="erp-title-bar__brand">
        <span class="erp-title-bar__app">{{ config('unitec.app_name') }}</span>
    </div>

    <div class="erp-title-bar__user">
        <span class="erp-title-bar__avatar">{{ strtoupper(substr(filament()->auth()->user()?->name ?? 'U', 0, 1)) }}</span>
        <span class="erp-title-bar__username">{{ filament()->auth()->user()?->name }}</span>
    </div>
</div>
