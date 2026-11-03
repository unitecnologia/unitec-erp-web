@php
    $empresa = \App\Support\Erp\ErpContext::currentEmpresa();
    $logoUrl = $empresa?->logoUrl();
    $empresaNome = $empresa?->nome ?: ($empresa?->razao_social ?: 'Empresa');
    $podeEditarEmpresa = \App\Support\Erp\ErpAccess::currentCan('empresa.access') && $empresa;
    $empresaEditUrl = ($podeEditarEmpresa && $empresa)
        ? \App\Filament\Resources\EmpresaResource::getUrl('edit', ['record' => $empresa])
        : null;
@endphp
<div class="erp-home-logo" wire:key="erp-home-logo-{{ $empresa?->getKey() ?? 0 }}">
    @if (filled($logoUrl))
        <img
            src="{{ $logoUrl }}"
            alt="{{ $empresaNome }}"
            decoding="async"
            class="erp-home-logo__img"
        >
    @else
        <div class="erp-home-logo__empty" role="status">
            <p class="erp-home-logo__empty-title">{{ $empresaNome }}</p>
            <p class="erp-home-logo__empty-hint">
                Nenhuma logomarca cadastrada.
                @if ($empresaEditUrl)
                    <a href="{{ $empresaEditUrl }}" class="erp-home-logo__empty-link">Cadastrar em Configurações → Empresa</a>
                @else
                    Cadastre em Configurações → Empresa.
                @endif
            </p>
        </div>
    @endif
</div>
<style>
.fi-body:has(.erp-home-page){display:flex;flex-direction:column;height:100dvh;max-height:100dvh;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-layout{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-main-ctn,.fi-body:has(.erp-home-page) .fi-main{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.fi-body:has(.erp-home-page) .fi-main{padding:0 !important;background:#f4f7fb !important}
.erp-home-page.fi-page,.erp-home-page .fi-page-header-main-ctn,.erp-home-page .fi-page-main,.erp-home-page .fi-page-content,.erp-home-page .fi-sc,.erp-home-page .fi-sc>*{display:flex !important;flex-direction:column;flex:1;min-height:0;padding:0 !important;gap:0 !important;background:transparent !important;border:0 !important;box-shadow:none !important;overflow:hidden}
.erp-home-page .fi-header,.erp-home-page .fi-page-header-main-ctn>.fi-page-header{display:none !important}
.erp-home-logo{display:flex;flex:1;align-items:center;justify-content:center;min-height:0;padding:1.5rem;background:#f4f7fb}
.erp-home-logo__img{max-width:min(32rem,78vw);max-height:min(14rem,42vh);width:auto;height:auto;object-fit:contain}
.erp-home-logo__empty{display:flex;flex-direction:column;align-items:center;gap:0.45rem;max-width:28rem;text-align:center}
.erp-home-logo__empty-title{margin:0;font-size:1.05rem;font-weight:700;color:#0f3460}
.erp-home-logo__empty-hint{margin:0;font-size:0.82rem;line-height:1.4;color:#64748b}
.erp-home-logo__empty-link{color:#1e5a9e;font-weight:600;text-decoration:underline;text-underline-offset:2px}
.erp-home-logo__empty-link:hover{color:#0f3460}
</style>
