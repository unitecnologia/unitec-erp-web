@php
    $status = (string) ($record->status ?? '');
    $label = \App\Models\Orcamento::statusLabels()[$status] ?? $status;
@endphp

<span @class(['erp-orc-status', 'erp-orc-status--' . $status])>{{ $label }}</span>
