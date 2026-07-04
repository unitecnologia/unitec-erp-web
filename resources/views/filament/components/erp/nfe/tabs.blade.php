@php
    use App\Models\Nfe;

    $statusTabs = [
        Nfe::STATUS_ABERTA => 'Aberta',
        Nfe::STATUS_TRANSMITIDA => 'Transmitida',
        Nfe::STATUS_CANCELADA => 'Cancelada',
        Nfe::STATUS_DUPLICIDADE => 'Duplicidade',
        Nfe::STATUS_INUTILIZADA => 'Inutilizada',
        Nfe::STATUS_DENEGADA => 'Denegada',
        Nfe::STATUS_CONTINGENCIA => 'Contingência',
    ];
@endphp

<div class="erp-nfe__tabs-wrap">
    <div class="erp-nfe__tabs">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class(['erp-nfe__tab', 'erp-nfe__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
