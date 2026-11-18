@php
    $pageClass = 'erp-rh-exames';
    $statusTabs = [
        'realizados' => 'Realizados',
        'proximos' => 'Próximos',
        'pendentes' => 'Pendentes',
        'todos' => 'Todos',
    ];
    $alertaTabs = [
        30 => '30 dias',
        15 => '15 dias',
        7 => '7 dias',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'realizados';
    $alertaAtual = in_array((int) $this->alertaDias, [7, 15, 30], true) ? (int) $this->alertaDias : 30;
@endphp

<div
    class="{{ $pageClass }}__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{
        statusAtivo: @js($statusAtual),
        alertaAtivo: @js($alertaAtual)
    }"
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

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--alerta">
        @foreach ($alertaTabs as $value => $label)
            <button
                type="button"
                class="{{ $pageClass }}__tab {{ $alertaAtual === (int) $value ? $pageClass.'__tab--active' : '' }}"
                :class="{ '{{ $pageClass }}__tab--active': alertaAtivo === {{ (int) $value }} }"
                @click="alertaAtivo = {{ (int) $value }}; $wire.setAlertaDias({{ (int) $value }})"
                title="Janela de alerta para próximos exames"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
