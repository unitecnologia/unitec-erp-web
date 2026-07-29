@php
    use App\Models\NotaFornecedor;

    $status = (string) ($record->status ?? '');
    $label = NotaFornecedor::statusLabels()[$status] ?? $status;
@endphp

<span @class(['erp-nfe__status-chip', 'erp-nfe__status-chip--' . $status])>
    {{ $label }}
</span>
