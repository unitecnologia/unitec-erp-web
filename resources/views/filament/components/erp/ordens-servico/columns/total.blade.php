@php
    /** @var \App\Models\OrdemServico $record */
    $valor = number_format((float) ($record->total_geral ?? 0), 2, ',', '.');
@endphp

<span class="erp-orc-total-cell">
    <span class="erp-orc-total-cell__currency">R$</span>
    <span class="erp-orc-total-cell__amount" title="R$ {{ $valor }}">{{ $valor }}</span>
</span>
