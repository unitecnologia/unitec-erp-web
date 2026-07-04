@php
    $tipoTabs = [
        'clientes' => 'Clientes',
        'funcionarios' => 'Funcionários',
        'fornecedores' => 'Fornecedores',
        'administradoras' => 'Administradoras',
        'parceiros' => 'Parceiros',
        'todos' => 'Todos',
    ];
@endphp

@if (! in_array($this->tipoFilter, ['ccf_spc'], true))
    <div class="erp-pessoas__tabs-wrap">
        <div class="erp-pessoas__tabs erp-pessoas__tabs--tipo">
            @foreach ($tipoTabs as $value => $label)
                <button
                    type="button"
                    wire:click="setTipoFilter('{{ $value }}')"
                    @class(['erp-pessoas__tab', 'erp-pessoas__tab--active' => $this->tipoFilter === $value])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>
@endif
