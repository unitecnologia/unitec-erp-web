@php
    use App\Support\Erp\ErpContext;

    $statusItems = filament()->auth()->check() ? ErpContext::statusBar() : [];
@endphp

<div class="erp-title-bar">
    <div class="erp-title-bar__brand">
        <span class="erp-title-bar__app">{{ config('unitec.app_name') }}</span>
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
                </div>
            @endforeach
        </div>
    @endif

    <div class="erp-title-bar__user">
        <span class="erp-title-bar__user-label">Usuário</span>
        <span class="erp-title-bar__username">{{ filament()->auth()->user()?->name }}</span>
    </div>
</div>
