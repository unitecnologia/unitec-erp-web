@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::pixFields();
    $booleans = EmpresaParametros::pixBooleanFields();
    $provedores = EmpresaParametros::pixProvedorOptions();
@endphp

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<div class="erp-empresas-parametros__form-grid">
    @foreach ($fields as $field => $meta)
        <div class="erp-empresas-parametros__field">
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
            @if ($field === 'param_pix_provedor')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($provedores as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_pix_ambiente')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    <option value="homologacao">Homologação</option>
                    <option value="producao">Produção</option>
                </select>
            @else
                <input
                    id="param-{{ $field }}"
                    type="text"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                >
            @endif
        </div>
    @endforeach
</div>
