@php
    use App\Models\PdvVendaNfce;

    $statusTabs = PdvVendaNfce::tabLabels();
    $statusAtual = filled($this->statusFilter)
        ? (string) $this->statusFilter
        : PdvVendaNfce::TAB_TRANSMITIDOS;
    $statusCounts = $this->statusTabCounts;
@endphp

<div
    class="erp-nfe__tabs-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
    x-on:erp-nfce-status.window="statusAtivo = $event.detail || @js(PdvVendaNfce::TAB_TRANSMITIDOS)"
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
            >
                <span>{{ $label }}</span>
                <span class="erp-nfe__tab-count" data-erp-nfce-tab-count="{{ $value }}">{{ $statusCounts[$value] ?? '(0)' }}</span>
            </button>
        @endforeach
    </div>
</div>
