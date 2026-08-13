@php
    use App\Models\PdvVendaNfce;

    $statusTabs = PdvVendaNfce::tabLabels();
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
