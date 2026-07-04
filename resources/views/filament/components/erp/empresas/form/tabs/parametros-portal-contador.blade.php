@php
    use App\Models\Contador;
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::portalContadorFields();
    $booleans = EmpresaParametros::portalContadorBooleanFields();
    $ambientes = EmpresaParametros::portalContadorAmbienteOptions();
    $contadores = Contador::query()->orderBy('nome')->pluck('nome', 'id');

    $habilitar = $booleans['param_portal_contador_habilitar'] ?? null;
    unset($booleans['param_portal_contador_habilitar']);
@endphp

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @if ($habilitar)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.param_portal_contador_habilitar">
            <span>{{ $habilitar['label'] }}</span>
        </label>
    @endif
</div>

<p class="erp-empresas-parametros__hint">
    Envio automático de documentos fiscais para o portal na nuvem (escritório contábil).
    O ERP local envia via HTTPS; o contador consulta no portal.
</p>

<div class="erp-empresas-parametros__section-title">Conexão</div>

<div class="erp-empresas-parametros__form-grid">
    @foreach ($fields as $field => $meta)
        <div class="erp-empresas-parametros__field">
            <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>

            @if ($field === 'param_portal_contador_ambiente')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    @foreach ($ambientes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_portal_contador_contador_id')
                <select id="param-{{ $field }}" wire:model="data.{{ $field }}" class="erp-pcad-form__select erp-pcad-form__select--md">
                    <option value="">— Nenhum —</option>
                    @foreach ($contadores as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            @elseif ($field === 'param_portal_contador_token')
                <input
                    id="param-{{ $field }}"
                    type="password"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                    autocomplete="off"
                >
            @elseif ($field === 'param_portal_contador_timeout')
                <input
                    id="param-{{ $field }}"
                    type="number"
                    min="1"
                    max="300"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
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

<div class="erp-empresas-parametros__section-title">O que enviar</div>

<div class="erp-empresas-parametros__checks erp-empresas-parametros__checks--inline">
    @foreach ($booleans as $field => $meta)
        <label class="erp-pcad__check">
            <input type="checkbox" wire:model="data.{{ $field }}">
            <span>{{ $meta['label'] }}</span>
        </label>
    @endforeach
</div>

<div class="erp-empresas-parametros__actions">
    <button
        type="button"
        class="erp-pcad-form__btn"
        wire:click="testPortalContadorConnection"
        wire:loading.attr="disabled"
        wire:target="testPortalContadorConnection"
    >
        <span wire:loading.remove wire:target="testPortalContadorConnection">Testar conexão</span>
        <span wire:loading wire:target="testPortalContadorConnection">Testando…</span>
    </button>
</div>
