@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::impostoFields();
@endphp

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

<div class="erp-empresas-parametros__obs-block">
    <label class="erp-pcad-form__label" for="param-imp-obs">Observação — Consulte seu contador</label>
    <textarea
        id="param-imp-obs"
        wire:model="data.param_imp_observacao"
        class="erp-empresas-pcad__textarea erp-empresas-parametros__obs"
        rows="5"
        spellcheck="false"
    ></textarea>
</div>
