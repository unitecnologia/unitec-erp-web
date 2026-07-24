@php
    $valor = number_format((float) ($getState() ?? 0), 2, ',', '.');
@endphp

<span class="erp-fv-mon-money">
    <span class="erp-fv-mon-money__currency">R$</span>
    <span class="erp-fv-mon-money__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
