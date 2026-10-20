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
                @click="
                    statusAtivo = @js($value);
                    window.dispatchEvent(new CustomEvent('erp-nfce-status', { detail: @js($value) }));
                    const t = (window.Livewire?.getByName?.('erp.nfce-list-table') || [])[0];
                    if (t) t.call('setStatusFilter', @js($value));
                "
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
