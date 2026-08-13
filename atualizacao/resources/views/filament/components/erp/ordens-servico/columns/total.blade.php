@php
    /** @var \App\Models\OrdemServico $record */
    $total = (float) ($record->total_geral ?? 0);
@endphp

<span class="erp-orcamentos__money">R$ {{ number_format($total, 2, ',', '.') }}</span>
