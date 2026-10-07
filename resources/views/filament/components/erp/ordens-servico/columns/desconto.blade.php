@php
    /** @var \App\Models\OrdemServico $record */
    $desconto = $record->descontoTotalLista();
    $valor = number_format($desconto, 2, ',', '.');
@endphp

@if ($desconto > 0)
    <span class="erp-orc-total-cell">
        <span class="erp-orc-total-cell__currency">R$</span>
        <span class="erp-orc-total-cell__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
    </span>
@else
    <span class="erp-orc-total-cell erp-orc-total-cell--vazio">—</span>
@endif
