@php
    use App\Support\Erp\ErpContext;

    $statusItems = ErpContext::statusBar();
@endphp

@if (filament()->auth()->check() && ! request()->boolean('pdv'))
    <footer class="erp-status-bar" aria-label="Barra de status">
        @foreach ($statusItems as $label => $value)
            <div class="erp-status-bar__item">
                <span class="erp-status-bar__label">{{ $label }}:</span>
                <span class="erp-status-bar__value">{{ $value }}</span>
            </div>
        @endforeach
    </footer>
@endif
