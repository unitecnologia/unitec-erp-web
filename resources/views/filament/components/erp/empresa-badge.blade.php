@php
    $nome = trim((string) ($nome ?? ''));
    $prefix = $prefix ?? 'erp-empresa';
@endphp

<div
    class="{{ $prefix }}__empresa-group erp-empresa-badge"
    role="status"
    aria-label="Empresa ativa{{ $nome !== '' ? ': '.$nome : '' }}"
>
    <span class="{{ $prefix }}__empresa-label erp-empresa-badge__label">Empresa</span>
    <span class="erp-empresa-badge__sep" aria-hidden="true">·</span>
    <span
        class="{{ $prefix }}__empresa-value erp-empresa-badge__value"
        @if ($nome !== '') title="{{ $nome }}" @endif
    >{{ $nome !== '' ? $nome : '—' }}</span>
</div>
