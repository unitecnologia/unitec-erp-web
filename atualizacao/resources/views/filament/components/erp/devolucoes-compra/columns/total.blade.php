@php
    /** @var \App\Models\DevolucaoCompra $record */
    $total = (float) ($record->total ?? 0);
@endphp

<span class="erp-orcamentos__money">R$ {{ number_format($total, 2, ',', '.') }}</span>
