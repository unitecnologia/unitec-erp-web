@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::ifoodFields();
    $booleans = EmpresaParametros::ifoodBooleanFields();
    $ambientes = EmpresaParametros::ifoodAmbienteOptions();
@endphp

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<p class="erp-empresas-parametros__hint">
    Credenciais do app no <strong>iFood Developer</strong> (Merchant API). Nesta etapa os dados são gravados na empresa;
    a sincronização de pedidos será ligada depois.
</p>

<div class="erp-empresas-parametros__form-grid">
    @foreach ($fields as $field => $meta)
        <div class="erp-empresas-parametros__field">
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
            @if ($field === 'param_ifood_ambiente')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($ambientes as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @elseif (in_array($field, ['param_ifood_client_secret', 'param_ifood_access_token', 'param_ifood_refresh_token', 'param_ifood_webhook_secret'], true))
                <input
                    id="param-{{ $field }}"
                    type="password"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    autocomplete="off"
                    data-lpignore="true"
                    data-1p-ignore="true"
                    data-bwignore="true"
                    data-google-password-manager="ignore"
                >
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
