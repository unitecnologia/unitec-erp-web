@php
    $valor = number_format((float) ($record->total ?? 0), 2, ',', '.');
@endphp

<span class="erp-compras-total-cell">
    <span class="erp-compras-total-cell__currency">R$</span>
    <span class="erp-compras-total-cell__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
