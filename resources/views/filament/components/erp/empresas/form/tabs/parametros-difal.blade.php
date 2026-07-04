@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::difalFields();
    $booleans = EmpresaParametros::difalBooleanFields();
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
            <input
                id="param-{{ $field }}"
                type="text"
                wire:model="data.{{ $field }}"
                class="erp-pcad-form__input erp-pcad-form__input--sm"
            >
        </div>
    @endforeach
</div>
