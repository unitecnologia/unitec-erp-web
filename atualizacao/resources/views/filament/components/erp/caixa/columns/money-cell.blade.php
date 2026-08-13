@php
    $valor = number_format((float) ($getState() ?? 0), 2, ',', '.');
@endphp

<span class="erp-caixa-money">
    <span class="erp-caixa-money__currency">R$</span>
    <span class="erp-caixa-money__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
