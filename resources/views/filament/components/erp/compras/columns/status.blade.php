@php
    use App\Models\Compra;

    $statusOriginal = (string) ($record->status ?? '');
    if ($statusOriginal === 'devolvida') {
        $statusOriginal = 'fechada';
    }
    $statusOriginalLabel = Compra::statusLabels()[$statusOriginal] ?? $statusOriginal;
    $valorDevolvido = round((float) ($record->valor_devolvido ?? 0), 2);
    $temDevolucao = $valorDevolvido > 0.00005
        || (bool) ($record->tem_devolucao_finalizada ?? false)
        || (bool) ($record->has_devolucao_finalizada ?? false);
@endphp

@if ($temDevolucao)
    <span class="erp-compras__status-chip erp-compras__status-chip--devolvida" title="Situação: {{ $statusOriginalLabel }}">
        Devolvida
    </span>
@else
    <span @class(['erp-compras__status-chip', 'erp-compras__status-chip--'.$statusOriginal])>
        {{ $statusOriginalLabel }}
    </span>
@endif
