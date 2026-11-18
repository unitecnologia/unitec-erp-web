@php
    $pageClass = 'erp-rh-beneficios';
    $statusTabs = [
        'ativos' => 'Ativos',
        'cancelados' => 'Cancelados',
        'todos' => 'Todos',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativos';
@endphp

<div
    class="{{ $pageClass }}__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{ statusAtivo: @js($statusAtual) }"
>
    <div class="{{ $pageClass }}__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="{{ $pageClass }}__tab {{ $statusAtual === $value ? $pageClass.'__tab--active' : '' }}"
                :class="{ '{{ $pageClass }}__tab--active': statusAtivo === @js($value) }"
                @click="statusAtivo = @js($value); $wire.setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
