@php
    $empresa = \App\Support\Erp\ErpContext::currentEmpresa();
    $logoUrl = $empresa?->logoUrl();
@endphp
<div class="erp-home-logo">
    @if (filled($logoUrl))
        <img src="{{ $logoUrl }}" alt="{{ $empresa?->nome_fantasia ?: $empresa?->nome ?: 'Empresa' }}" decoding="async">
    @endif
</div>
<style>
.fi-body:has(.erp-home-page){display:flex;flex-direction:column;height:100dvh;max-height:100dvh;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-layout{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-main-ctn,.fi-body:has(.erp-home-page) .fi-main{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-main{padding:0 !important;background:#f4f7fb !important}
.erp-home-page.fi-page,.erp-home-page .fi-page-header-main-ctn,.erp-home-page .fi-page-main,.erp-home-page .fi-page-content,.erp-home-page .fi-sc,.erp-home-page .fi-sc>*{display:flex !important;flex-direction:column;flex:1;min-height:0;padding:0 !important;gap:0 !important;background:transparent !important;border:0 !important;box-shadow:none !important;overflow:hidden}
.erp-home-page .fi-header,.erp-home-page .fi-page-header-main-ctn>.fi-page-header{display:none !important}
.erp-home-logo{display:flex;flex:1;align-items:center;justify-content:center;min-height:0;background:#f4f7fb}
.erp-home-logo img{max-width:min(26rem,70vw);max-height:8.5rem;width:auto;height:auto;object-fit:contain}
</style>
