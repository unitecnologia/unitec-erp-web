@php
    $pageClass = 'erp-rh-ocorrencias';
    $statusTabs = [
        'ativas' => 'Ativas',
        'canceladas' => 'Canceladas',
        'todos' => 'Todos',
    ];
    $tipos = array_merge(['todos' => 'Todos os tipos'], \App\Models\RhOcorrencia::tipos());
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativas';
    $tipoAtual = filled($this->tipoFilter) ? (string) $this->tipoFilter : 'todos';
@endphp

<div class="{{ $pageClass }}__status-wrap erp-list-tabs">
    <div class="{{ $pageClass }}__status">
        @foreach ($statusTabs as $value => $label)
            <button
                type="button"
                class="{{ $pageClass }}__tab {{ $statusAtual === $value ? $pageClass.'__tab--active' : '' }}"
                wire:click="setStatusFilter(@js($value))"
            >{{ $label }}</button>
        @endforeach
    </div>

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--tipo">
        <label class="{{ $pageClass }}__period-label" for="rh-oc-tipo">Tipo</label>
        <select id="rh-oc-tipo" wire:model.live="tipoFilter" class="{{ $pageClass }}__select">
            @foreach ($tipos as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--periodo">
        <label class="{{ $pageClass }}__period-label">
            De
            <input type="date" wire:model.live="periodoDe" class="{{ $pageClass }}__input {{ $pageClass }}__period-input" data-erp-date-wire="iso">
        </label>
        <label class="{{ $pageClass }}__period-label">
            Até
            <input type="date" wire:model.live="periodoAte" class="{{ $pageClass }}__input {{ $pageClass }}__period-input" data-erp-date-wire="iso">
        </label>
    </div>
</div>
