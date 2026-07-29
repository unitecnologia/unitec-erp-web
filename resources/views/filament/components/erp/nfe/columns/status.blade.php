@php
    use App\Models\Nfe;

    $status = (string) ($record->status ?? '');
    $label = Nfe::statusLabels()[$status] ?? $status;
@endphp

<span @class(['erp-nfe__status-chip', 'erp-nfe__status-chip--' . $status])>
    {{ $label }}
</span>
