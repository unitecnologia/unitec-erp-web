@php
    $qtd = max(0, (int) ($qtd ?? 0));
    $qtdFmt = number_format($qtd, 0, ',', '.');
@endphp
<span class="erp-fv-mon-dav-count" title="TOTAL DAV: {{ $qtdFmt }}">
    <span class="erp-fv-mon-dav-count__label">TOTAL DAV:</span>
    <span class="erp-fv-mon-dav-count__value">{{ $qtdFmt }}</span>
</span>
