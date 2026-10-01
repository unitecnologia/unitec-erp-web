@php
    /** @var \App\Models\ForcaVendasOrder $record */
    $label = $record->envioListaLabel();
@endphp

@if ($label === '—')
    <span class="erp-fv-mon-envio erp-fv-mon-envio--vazio">—</span>
@elseif ($label === 'E/W')
    <span class="erp-fv-mon-envio" title="Enviado por e-mail e WhatsApp">
        <span class="erp-fv-mon-envio--email">E</span>/<span class="erp-fv-mon-envio--whats">W</span>
    </span>
@elseif ($label === 'E')
    <span class="erp-fv-mon-envio erp-fv-mon-envio--email" title="Enviado por e-mail">E</span>
@else
    <span class="erp-fv-mon-envio erp-fv-mon-envio--whats" title="Enviado por WhatsApp">W</span>
@endif
