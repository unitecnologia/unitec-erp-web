@php
    use App\Support\Erp\CloudflaredStatus;
    use App\Support\Erp\ErpContext;
    use App\Support\Erp\ErpSystemConfig;
    use App\Support\Erp\Hotfix\HotfixEstado;

    $statusItems = filament()->auth()->check() ? ErpContext::statusBar() : [];
    $hotfix = isset($statusItems['Versão']) ? HotfixEstado::ativo((string) $statusItems['Versão']) : null;
    $cloud = null;
    if (filament()->auth()->check() && ErpSystemConfig::acessoRemotoHabilitado()) {
        $cloud = CloudflaredStatus::forUi();
    }
@endphp

<div class="erp-title-bar">
    <div class="erp-title-bar__brand">
        <span class="erp-title-bar__app">{{ config('unitec.app_name') }}</span>

        @if ($cloud !== null)
            <span
                class="erp-title-bar__cloud"
                title="Status do túnel Cloudflare (acesso remoto)"
            >
                <span
                    @class([
                        'erp-title-bar__cloud-dot',
                        'erp-title-bar__cloud-dot--online' => $cloud['online'],
                        'erp-title-bar__cloud-dot--offline' => ! $cloud['online'],
                    ])
                    aria-hidden="true"
                ></span>
                <strong class="erp-title-bar__cloud-label">{{ $cloud['label'] }}</strong>
                <span class="erp-title-bar__cloud-detail">{{ $cloud['detail'] }}</span>
            </span>
        @endif
    </div>

    @if ($statusItems !== [])
        <div class="erp-title-bar__status" aria-label="Barra de status">
            @foreach ($statusItems as $label => $value)
                <div class="erp-title-bar__status-item">
                    <span class="erp-title-bar__status-label">{{ $label }}:</span>
                    <span
                        @class([
                            'erp-title-bar__status-value',
                            'erp-title-bar__status-value--accent' => $label === 'Empresa',
                        ])
                        @if ($label === 'Atualizado Em') id="erp-status-updated-at" @endif
                    >{{ $value }}</span>
                    @if ($label === 'Versão' && $hotfix !== null)
                        <span
                            class="erp-title-bar__status-hotfix"
                            title="Hotfix {{ $hotfix['id'] }} instalado{{ $hotfix['aplicado_em'] !== '' ? ' em '.$hotfix['aplicado_em'] : '' }}"
                        >| {{ $hotfix['rotulo'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="erp-title-bar__user">
        <span class="erp-title-bar__user-label">Usuário</span>
        <span class="erp-title-bar__username">{{ filament()->auth()->user()?->name }}</span>
    </div>
</div>
