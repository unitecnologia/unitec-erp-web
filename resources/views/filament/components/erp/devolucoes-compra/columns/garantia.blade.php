@php
    /** @var \App\Models\DevolucaoCompra $record */
    $garantia = (bool) ($record->troca_em_garantia ?? false);
@endphp

@if ($garantia)
    <span class="erp-devcompra__garantia-chip">SIM</span>
@else
    <span class="erp-devcompra__garantia-empty">—</span>
@endif
