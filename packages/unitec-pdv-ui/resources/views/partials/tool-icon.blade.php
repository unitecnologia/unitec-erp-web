{{-- Ícone da toolbar do PDV (PNG no padrão ERP shortcuts). Uso: @include('pdvui::partials.tool-icon', ['name' => 'exit']) --}}
@php
    $name = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($name ?? 'options'))) ?: 'options';
    $iconPath = "img/erp/pdv-tools/{$name}.png";
@endphp
<span class="erp-pdv__tool-icon erp-pdv__tool-icon--{{ $name }}" aria-hidden="true">
    <img
        src="{{ asset($iconPath) }}?v={{ \Unitec\PdvUi\Support\PdvUiAssets::version($iconPath) }}"
        alt=""
        class="erp-pdv__tool-img"
        loading="lazy"
        decoding="async"
    />
</span>
