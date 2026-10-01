@php
    $statusTabs = [
        'todos' => 'Todos',
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'todos';
@endphp

<div
    class="erp-cfop__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="erp-cfop__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="erp-cfop__tab"
                :class="{ 'erp-cfop__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
