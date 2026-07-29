@php
    $valor = number_format((float) ($record->venda?->total ?? 0), 2, ',', '.');
@endphp

<span class="erp-expedicao-total-cell">
    <span class="erp-expedicao-total-cell__currency">R$</span>
    <span class="erp-expedicao-total-cell__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
