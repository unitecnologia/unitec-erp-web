@php
    /** @var \App\Models\OrdemServico $record */
    $label = $record->envioListaLabel();
@endphp

@if ($label === '—')
    <span class="erp-os-envio erp-os-envio--vazio">—</span>
@elseif ($label === 'E/W')
    <span class="erp-os-envio" title="Enviado por e-mail e WhatsApp">
        <span class="erp-os-envio--email">E</span>/<span class="erp-os-envio--whats">W</span>
    </span>
@elseif ($label === 'E')
    <span class="erp-os-envio erp-os-envio--email" title="Enviado por e-mail">E</span>
@else
    <span class="erp-os-envio erp-os-envio--whats" title="Enviado por WhatsApp">W</span>
@endif
