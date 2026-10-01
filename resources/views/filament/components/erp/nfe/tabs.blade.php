@php
    use App\Models\Nfe;

    $statusTabs = [
        'todas' => 'Todas',
        Nfe::STATUS_ABERTA => 'Aberta',
        Nfe::STATUS_TRANSMITIDA => 'Transmitida',
        Nfe::STATUS_CANCELADA => 'Cancelada',
        Nfe::STATUS_DUPLICIDADE => 'Duplicidade',
        Nfe::STATUS_INUTILIZADA => 'Inutilizada',
        Nfe::STATUS_DENEGADA => 'Denegada',
        Nfe::STATUS_CONTINGENCIA => 'Contingência',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todas';
@endphp

<div
    class="erp-nfe__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-nfe__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                @class([
                    'erp-nfe__tab',
                    'erp-nfe__tab--' . $value,
                ])
                :class="{ 'erp-nfe__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
