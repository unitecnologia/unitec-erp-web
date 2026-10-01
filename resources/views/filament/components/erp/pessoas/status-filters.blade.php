@php
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativos';
    $statusCounts = $this->personStatusCounts();
@endphp

<div class="erp-pessoas__status-wrap erp-list-tabs">
    <div class="erp-pessoas__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="erp-pessoas__tab{{ $statusAtual === $value ? ' erp-pessoas__tab--active' : '' }}"
                wire:click="setStatusFilter('{{ $value }}')"
            >{{ $label }} ({{ number_format((int) ($statusCounts[$value] ?? 0), 0, ',', '.') }})</button>
        @endforeach
    </div>
</div>
