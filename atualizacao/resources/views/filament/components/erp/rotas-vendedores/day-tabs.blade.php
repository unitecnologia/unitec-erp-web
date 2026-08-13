@php
    use App\Models\PersonVisitaDia;
@endphp

<div class="erp-unidades__tabs-wrap">
    <div class="erp-unidades__tabs">
        @foreach (PersonVisitaDia::diasLabels() as $value => $label)
            <button
                type="button"
                wire:click="setDiaSemana({{ $value }})"
                @disabled(! filled($this->vendedorId))
                @class([
                    'erp-unidades__tab',
                    'erp-unidades__tab--active' => $this->diaSemana === $value,
                ])
            >{{ $label }}</button>
        @endforeach
    </div>
</div>
