@php
    $pageClass = $pageClass ?? 'erp-pessoas';
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];
@endphp

<div class="{{ $pageClass }}__status-wrap">
    <div class="{{ $pageClass }}__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                wire:click="setStatusFilter('{{ $value }}')"
                @class([$pageClass . '__tab', $pageClass . '__tab--active' => $this->statusFilter === $value])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
