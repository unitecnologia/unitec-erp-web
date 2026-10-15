@php
    $valor = round((float) ($record->valor_devolvido ?? 0), 2);
@endphp

@if ($valor > 0.00005)
    @php $fmt = number_format($valor, 2, ',', '.'); @endphp
    <span class="erp-compras-total-cell erp-compras-total-cell--devolvido">
        <span class="erp-compras-total-cell__currency">R$</span>
        <span class="erp-compras-total-cell__amount" title="R$ {{ $fmt }}">{{ $fmt }}</span>
    </span>
@else
    —
@endif
