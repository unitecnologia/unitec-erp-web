@php
    use App\Support\Erp\EmpresaParametros;

    $groups = EmpresaParametros::permissionGroups();
    $fields = EmpresaParametros::permissionFields();
    $grouped = [];

    foreach ($fields as $field => $meta) {
        $group = EmpresaParametros::permissionGroupForField($field);
        $grouped[$group][$field] = $meta;
    }
@endphp

<div class="erp-empresas-parametros__perm-grid">
    @foreach ($groups as $groupKey => $groupTitle)
        <fieldset class="erp-pcad__group erp-empresas-parametros__perm-group">
            <legend class="erp-pcad__group-title">{{ $groupTitle }}</legend>
            <div class="erp-empresas-parametros__checks">
                @foreach ($grouped[$groupKey] ?? [] as $field => $meta)
                    @if ($meta['tri'] ?? false)
                        @include('filament.components.erp.empresas.form.tri-checkbox', [
                            'field' => $field,
                            'label' => $meta['label'],
                        ])
                    @elseif ($field === 'param_geral_bloquear_estoque_negativo')
                        <label class="erp-pcad__check">
                            <input
                                type="checkbox"
                                wire:click.prevent="toggleBloquearEstoqueNegativo"
                                @checked(filter_var($this->data['param_geral_bloquear_estoque_negativo'] ?? false, FILTER_VALIDATE_BOOLEAN))
                            >
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @else
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model="data.{{ $field }}">
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endif
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
