@php
    $valor = number_format((float) ($record->pdvVenda?->total ?? 0), 2, ',', '.');
@endphp

<span class="erp-list-total-cell">
    <span class="erp-list-total-cell__currency">R$</span>
    <span class="erp-list-total-cell__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
