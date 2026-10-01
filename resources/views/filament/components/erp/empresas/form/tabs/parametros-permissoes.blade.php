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
                            'hint' => $meta['hint'] ?? null,
                        ])
                    @elseif ($field === 'param_geral_bloquear_estoque_negativo')
                        @php
                            $bloquearEstoqueNegativoAtivo = filter_var(
                                $this->data['param_geral_bloquear_estoque_negativo'] ?? false,
                                FILTER_VALIDATE_BOOLEAN,
                            );
                        @endphp
                        <label class="erp-pcad__check" @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif>
                            {{-- wire:key remonta o input: Livewire morph não atualiza a property checked --}}
                            <input
                                type="checkbox"
                                wire:key="emp-bloquear-estoque-negativo-{{ $bloquearEstoqueNegativoAtivo ? '1' : '0' }}"
                                wire:click.prevent="toggleBloquearEstoqueNegativo"
                                @checked($bloquearEstoqueNegativoAtivo)
                            >
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @elseif ($field === 'param_monitor_vendas_escolher_empresa_emitente_nfe')
                        <label class="erp-pcad__check">
                            <input type="checkbox" wire:model.live="data.{{ $field }}">
                            <span>
                                {{ $meta['label'] }}
                                @if (filled($meta['hint'] ?? null))
                                    <span
                                        class="erp-produtos-impostos__label--hint"
                                        title="{{ $meta['hint'] }}"
                                        role="img"
                                        aria-label="{{ $meta['hint'] }}"
                                    ></span>
                                @endif
                            </span>
                        </label>
                        @include('filament.components.erp.empresas.form.partials.nfe-emitentes-config')
                    @else
                        <label class="erp-pcad__check" @if (filled($meta['hint'] ?? null)) title="{{ $meta['hint'] }}" @endif>
                            <input type="checkbox" wire:model="data.{{ $field }}">
                            <span>{{ $meta['label'] }}</span>
                        </label>
                    @endif
                @endforeach
            </div>
            @if ($groupKey === 'monitor_vendas')
                @php($modoDescontoReais = EmpresaParametros::monitorVendasDescontoReaisItemModoField())
                <div class="erp-empresas-parametros__desconto-reais">
                    <label class="erp-pcad-form__label" for="param-param_monitor_vendas_desconto_reais_item_modo">
                        {{ $modoDescontoReais['label'] }}
                        <span
                            class="erp-produtos-impostos__label--hint"
                            title="{{ $modoDescontoReais['hint'] }}"
                            role="img"
                            aria-label="{{ $modoDescontoReais['hint'] }}"
                        ></span>
                    </label>
                    <select
                        id="param-param_monitor_vendas_desconto_reais_item_modo"
                        wire:model="data.param_monitor_vendas_desconto_reais_item_modo"
                        class="erp-pcad-form__select"
                    >
                        @foreach ($modoDescontoReais['options'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </fieldset>
    @endforeach
</div>
