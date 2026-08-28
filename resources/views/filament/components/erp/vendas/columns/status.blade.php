@php
    $status = (string) ($record->status ?? '');
    $label = \App\Models\Venda::statusLabels()[$status] ?? $status;
@endphp

<span @class(['erp-vendas-status', 'erp-vendas-status--' . $status])>{{ $label }}</span>
