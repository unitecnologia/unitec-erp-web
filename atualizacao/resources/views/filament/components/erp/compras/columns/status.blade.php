@php
    use App\Models\Compra;

    $status = (string) ($getState() ?? $record->status ?? '');
    $label = $status === 'devolvida'
        ? 'Devolvida'
        : (Compra::statusLabels()[$status] ?? $status);
@endphp

<span @class(['erp-compras__status-chip', 'erp-compras__status-chip--'.$status])>
    {{ $label }}
</span>
