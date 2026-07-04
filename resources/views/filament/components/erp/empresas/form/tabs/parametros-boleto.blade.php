@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::boletoFields();
    $booleans = EmpresaParametros::boletoBooleanFields();
    $bancos = EmpresaParametros::boletoBancoOptions();
    $ambientes = EmpresaParametros::boletoAmbienteOptions();
    $especies = EmpresaParametros::boletoEspecieOptions();
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
    Os campos exigidos variam conforme o banco. Preencha de acordo com o manual da API de
    Cobrança do banco selecionado (convênio/carteira, credenciais OAuth e, quando exigido,
    certificado mTLS).
</p>

<div class="erp-empresas-parametros__form-grid erp-empresas-parametros__form-grid--boleto">
    @foreach ($fields as $field => $meta)
        <div class="erp-empresas-parametros__field">
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
            @if ($field === 'param_boleto_banco')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($bancos as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_boleto_ambiente')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($ambientes as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_boleto_especie_documento')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($especies as $value => $rotulo)
                        <option value="{{ $value }}">{{ $rotulo }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_boleto_certificado_senha' || $field === 'param_boleto_client_secret')
                <input
                    id="param-{{ $field }}"
                    type="password"
                    autocomplete="new-password"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
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
