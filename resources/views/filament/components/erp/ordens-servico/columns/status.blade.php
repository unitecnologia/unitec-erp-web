@php
    use App\Models\OrdemServico;

    /** @var OrdemServico $record */
    $situacao = (string) ($record->situacao ?? '');
    $label = mb_strtoupper($record->situacaoLabel(), 'UTF-8');

    $class = match ($situacao) {
        OrdemServico::SITUACAO_ABERTA, OrdemServico::SITUACAO_ANDAMENTO => 'erp-orcamentos__status--aberto',
        OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE => 'erp-orcamentos__status--fechado',
        OrdemServico::SITUACAO_CANCELADA => 'erp-orcamentos__status--cancelado',
        default => 'erp-orcamentos__status--aberto',
    };
@endphp

<span class="erp-orcamentos__status {{ $class }}">{{ $label }}</span>
