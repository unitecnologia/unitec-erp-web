@php
    use App\Models\DevolucaoVenda;

    /** @var DevolucaoVenda $record */
    $situacao = (string) ($record->situacao ?? '');
    $label = mb_strtoupper($record->situacaoLabel(), 'UTF-8');

    $class = match ($situacao) {
        DevolucaoVenda::SITUACAO_ABERTA => 'erp-orcamentos__status--aberto',
        DevolucaoVenda::SITUACAO_FINALIZADA => 'erp-orcamentos__status--fechado',
        DevolucaoVenda::SITUACAO_CANCELADA => 'erp-orcamentos__status--cancelado',
        default => 'erp-orcamentos__status--aberto',
    };
@endphp

<span class="erp-orcamentos__status {{ $class }}">{{ $label }}</span>
