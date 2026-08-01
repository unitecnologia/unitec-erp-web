@php
    use App\Models\DevolucaoCompra;

    /** @var DevolucaoCompra $record */
    $situacao = (string) ($record->situacao ?? '');
    $label = mb_strtoupper($record->situacaoLabel(), 'UTF-8');

    $class = match ($situacao) {
        DevolucaoCompra::SITUACAO_ABERTA => 'erp-orcamentos__status--aberto',
        DevolucaoCompra::SITUACAO_FINALIZADA => 'erp-orcamentos__status--fechado',
        DevolucaoCompra::SITUACAO_CANCELADA => 'erp-orcamentos__status--cancelado',
        default => 'erp-orcamentos__status--aberto',
    };
@endphp

<span class="erp-orcamentos__status {{ $class }}">{{ $label }}</span>
