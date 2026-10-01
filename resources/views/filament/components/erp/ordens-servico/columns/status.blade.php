@php
    use App\Models\OrdemServico;

    /** @var OrdemServico $record */
    $situacao = (string) ($record->situacao ?? '');
    $label = mb_strtoupper($record->situacaoLabel(), 'UTF-8');
    $chip = match ($situacao) {
        OrdemServico::SITUACAO_ABERTA => 'aberta',
        OrdemServico::SITUACAO_ANDAMENTO => 'andamento',
        OrdemServico::SITUACAO_FINALIZADA => 'finalizada',
        OrdemServico::SITUACAO_ENTREGUE => 'entregue',
        OrdemServico::SITUACAO_CANCELADA => 'cancelada',
        default => 'aberta',
    };
@endphp

<span class="erp-os__status-chip erp-os__status-chip--{{ $chip }}">{{ $label }}</span>
@if ($record->aguardandoFaturamento())
    <span class="erp-os__status-chip erp-os__status-chip--faturar">Faturar</span>
@endif
