@php
    $pageClass = 'erp-rh-documentos';
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];
    $validadeTabs = [
        'todos' => 'Validade',
        'vencendo' => 'Vencendo',
        'vencidos' => 'Vencidos',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativos';
    $validadeAtual = filled($this->validadeFilter) ? (string) $this->validadeFilter : 'todos';
@endphp

<div
    class="{{ $pageClass }}__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{
        statusAtivo: @js($statusAtual),
        validadeAtiva: @js($validadeAtual)
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

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--validade">
        @foreach ($validadeTabs as $value => $label)
            <button
                type="button"
                class="{{ $pageClass }}__tab {{ $validadeAtual === $value ? $pageClass.'__tab--active' : '' }}"
                :class="{ '{{ $pageClass }}__tab--active': validadeAtiva === @js($value) }"
                @click="validadeAtiva = @js($value); $wire.setValidadeFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
