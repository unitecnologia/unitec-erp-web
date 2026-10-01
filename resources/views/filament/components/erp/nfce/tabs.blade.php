@php
    use App\Models\PdvVendaNfce;

    $statusTabs = PdvVendaNfce::tabLabels();
    $statusAtual = filled($this->statusFilter)
        ? (string) $this->statusFilter
        : PdvVendaNfce::TAB_TRANSMITIDOS;
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
                class="erp-nfe__tab"
                :class="{ 'erp-nfe__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
