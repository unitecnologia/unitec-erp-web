@php
    $pageClass = 'erp-rh-holerites';
    $statusTabs = [
        'ativos' => 'Ativos',
        'inativos' => 'Inativos',
        'todos' => 'Todos',
    ];
    $statusAtual = filled($this->statusFilter) ? (string) $this->statusFilter : 'ativos';
    $meses = \App\Models\RhHolerite::meses();
    $anoAtual = filled($this->competenciaAno) ? (int) $this->competenciaAno : null;
    $mesAtual = filled($this->competenciaMes) ? (int) $this->competenciaMes : null;
@endphp

<div
    class="{{ $pageClass }}__status-wrap erp-list-tabs"
    wire:ignore
    x-data="{
        statusAtivo: @js($statusAtual),
        anoComp: @js($anoAtual),
        mesComp: @js($mesAtual)
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

    <div class="{{ $pageClass }}__status {{ $pageClass }}__status--competencia">
        <label class="{{ $pageClass }}__comp-label" for="rh-hol-ano">Ano</label>
        <input
            id="rh-hol-ano"
            type="number"
            min="2000"
            max="2100"
            class="{{ $pageClass }}__comp-input"
            x-model.number="anoComp"
            placeholder="Todos"
        >
        <label class="{{ $pageClass }}__comp-label" for="rh-hol-mes">Mês</label>
        <select id="rh-hol-mes" class="{{ $pageClass }}__comp-select" x-model.number="mesComp">
            <option value="">Todos</option>
            @foreach ($meses as $num => $nome)
                <option value="{{ $num }}">{{ sprintf('%02d', $num) }} — {{ $nome }}</option>
            @endforeach
        </select>
        <button
            type="button"
            class="{{ $pageClass }}__tab"
            @click="$wire.setCompetenciaFilter(anoComp || null, mesComp || null)"
        >Filtrar competência</button>
        <button
            type="button"
            class="{{ $pageClass }}__tab"
            @click="anoComp = null; mesComp = null; $wire.setCompetenciaFilter(null, null)"
        >Limpar</button>
    </div>
</div>
