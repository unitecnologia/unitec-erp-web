@php
    $pageClass = 'erp-rh-epis';
    $statusTabs = [
        'abertas' => 'Abertas',
        'devolvidas' => 'Devolvidas',
        'substituidas' => 'Substituídas',
        'canceladas' => 'Canceladas',
        'todos' => 'Todos',
    ];
    $trocaTabs = [
        'todos' => 'Troca: todas',
        'proximas' => 'Próximas',
        'vencidas' => 'Vencidas',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'abertas';
    $trocaAtual = filled($this->trocaFilter) ? (string) $this->trocaFilter : 'todos';
@endphp

<div
    class="{{ $pageClass }}__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{
        statusAtivo: @js($statusAtual),
        trocaAtiva: @js($trocaAtual)
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

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--troca">
        @foreach ($trocaTabs as $value => $label)
            <button
                type="button"
                class="{{ $pageClass }}__tab {{ $trocaAtual === $value ? $pageClass.'__tab--active' : '' }}"
                :class="{ '{{ $pageClass }}__tab--active': trocaAtiva === @js($value) }"
                @click="trocaAtiva = @js($value); $wire.setTrocaFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
