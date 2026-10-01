@php
    $tipoTabs = [
        'clientes' => 'Clientes',
        'funcionarios' => 'Funcionários',
        'fornecedores' => 'Fornecedores',
        'administradoras' => 'Administradoras',
        'parceiros' => 'Parceiros',
        'todos' => 'Todos',
    ];

    $tipoAtual = filled($this->tipoFilter) ? (string) $this->tipoFilter : 'clientes';
@endphp

@if (! in_array($this->tipoFilter, ['ccf_spc'], true))
    <div
        class="erp-pessoas__tabs-wrap erp-list-tabs"
        wire:ignore
        x-data="{ statusAtivo: @js($tipoAtual) }"
    >
        <div class="erp-pessoas__tabs erp-pessoas__tabs--tipo">
            @foreach ($tipoTabs as $value => $label)
                <button
                    type="button"
                    class="erp-pessoas__tab"
                    :class="{ 'erp-pessoas__tab--active': statusAtivo === @js($value) }"
                    @click="statusAtivo = @js($value); const t = (window.Livewire?.getByName?.('erp.person-list-table') || [])[0]; if (t) t.call('setTipoFilter', @js($value))"
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>
@endif
