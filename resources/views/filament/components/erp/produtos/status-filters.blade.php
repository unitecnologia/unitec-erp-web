@php
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];

    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativos';
    $statusCounts = $this->isSeriaisView() ? [] : $this->productStatusCounts();
@endphp

@if (! $this->isSeriaisView())
    <div class="erp-produtos__status-wrap erp-list-tabs">
        <div class="erp-produtos__status">
            @foreach ($statusTabs as $value => $label)
                <button
                    type="button"
                    class="erp-produtos__tab{{ $statusAtual === $value ? ' erp-produtos__tab--active' : '' }}"
                    wire:click="setStatusFilter('{{ $value }}')"
                >{{ $label }} ({{ number_format((int) ($statusCounts[$value] ?? 0), 0, ',', '.') }})</button>
            @endforeach
        </div>
    </div>
@endif
