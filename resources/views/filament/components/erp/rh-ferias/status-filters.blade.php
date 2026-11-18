@php
    $pageClass = 'erp-rh-ferias';
    $statusTabs = [
        'programadas' => 'Programadas',
        'em_andamento' => 'Em andamento',
        'concluidas' => 'Concluídas',
        'pendentes' => 'Pendentes',
        'todos' => 'Todos',
    ];
    $alertaTabs = [
        30 => 'Aviso 30d',
        15 => 'Aviso 15d',
        7 => 'Aviso 7d',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'programadas';
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
                title="Referência de antecedência mínima do aviso de férias"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
