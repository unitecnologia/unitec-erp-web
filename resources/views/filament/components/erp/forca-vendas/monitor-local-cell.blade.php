@php
    /** @var \App\Models\ForcaVendasOrder $record */
    $mapsUrl = \App\Filament\Resources\ForcaVendasMonitorResource::googleMapsUrl($record);
@endphp

@if ($mapsUrl)
    <a
        href="{{ $mapsUrl }}"
        class="erp-fv-mon-local"
        target="_blank"
        rel="noopener noreferrer"
        title="Ver local da venda"
        aria-label="Ver local da venda"
        onclick="event.stopPropagation()"
        @click.stop
    >📍</a>
@else
    <span class="erp-fv-mon-local erp-fv-mon-local--vazio" aria-hidden="true">—</span>
@endif
