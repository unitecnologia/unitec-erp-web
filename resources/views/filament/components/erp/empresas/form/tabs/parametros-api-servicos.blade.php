@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::apiServicosFields();
    $booleans = EmpresaParametros::apiServicosBooleanFields();
@endphp

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--wide">
    @foreach ($fields as $field => $meta)
        <div @class([
            'erp-empresas-parametros__field',
            'erp-empresas-parametros__field--compact' => $field === 'param_api_servicos_timeout',
        ])>
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
            <input
                id="param-{{ $field }}"
                type="{{ in_array($field, ['param_api_servicos_senha'], true) ? 'password' : ($field === 'param_api_servicos_timeout' ? 'number' : 'text') }}"
                @if ($field === 'param_api_servicos_timeout') min="1" max="300" @endif
                wire:model="data.{{ $field }}"
                @class([
                    'erp-pcad-form__input',
                    'erp-pcad-form__input--grow' => $field !== 'param_api_servicos_timeout',
                    'erp-pcad-form__input--xs' => $field === 'param_api_servicos_timeout',
                ])
                @if ($field === 'param_api_servicos_senha') autocomplete="off" @endif
                @if ($field === 'param_api_servicos_token') placeholder="Cole aqui o token Cosmos (Bluesoft)" @endif
            >
        </div>
    @endforeach
</div>
