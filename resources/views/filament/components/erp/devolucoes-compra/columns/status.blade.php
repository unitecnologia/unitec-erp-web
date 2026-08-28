@php
    use App\Models\DevolucaoCompra;

    /** @var DevolucaoCompra $record */
    $situacao = (string) ($record->situacao ?? '');
    $label = match ($situacao) {
        DevolucaoCompra::SITUACAO_ABERTA => 'Aberta',
        DevolucaoCompra::SITUACAO_FINALIZADA => 'Finalizada',
        DevolucaoCompra::SITUACAO_CANCELADA => 'Cancelada',
        default => $record->situacaoLabel(),
    };
@endphp

<span @class(['erp-devcompra__status-chip', 'erp-devcompra__status-chip--'.$situacao])>
    {{ $label }}
</span>
