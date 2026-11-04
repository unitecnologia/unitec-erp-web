@php
    $message = $message ?? 'Não foi possível carregar esta seção.';
    $retryMethod = $retryMethod ?? null;
@endphp

<p class="erp-dash__wave-error" role="alert">
    <span>{{ $message }}</span>
    @if (filled($retryMethod))
        <button
            type="button"
            class="erp-dash__visao-btn"
            wire:click="{{ $retryMethod }}"
        >
            Tentar novamente
        </button>
    @endif
</p>
