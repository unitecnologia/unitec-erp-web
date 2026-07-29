@php
    use App\Support\Erp\EmpresaParametros;

    $fields = EmpresaParametros::expedicaoBooleanFields();
    $numericFields = EmpresaParametros::expedicaoFields();
    $principal = ['param_expedicao_ativar', 'param_expedicao_pedir_quantidade'];
    $origens = [
        'param_expedicao_origem_pdv',
        'param_expedicao_origem_monitor',
        'param_expedicao_origem_vi',
        'param_expedicao_origem_erp',
    ];
@endphp

<fieldset class="erp-pcad__group erp-empresas-parametros__perm-group">
    <legend class="erp-pcad__legend">Parametrizações Expedição</legend>

    <div class="erp-empresas-parametros__checks">
        @foreach ($principal as $field)
            @php($meta = $fields[$field])
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model="data.{{ $field }}">
                {{ $meta['label'] }}
            </label>
        @endforeach
    </div>

    <div class="erp-empresas-parametros__form-grid">
        @foreach ($numericFields as $field => $meta)
            <div class="erp-empresas-parametros__field">
                <label class="erp-pcad-form__label" for="param-{{ $field }}">{{ $meta['label'] }}</label>
                <input
                    id="param-{{ $field }}"
                    type="number"
                    min="1"
                    max="999"
                    wire:model="data.{{ $field }}"
                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                >
            </div>
        @endforeach
    </div>

    <p class="erp-empresas-parametros__hint">
        Limita quantos pedidos podem ser marcados de uma vez na tela Controle de Expedição (bipagem em lote).
    </p>
</fieldset>

<fieldset class="erp-pcad__group erp-empresas-parametros__perm-group">
    <legend class="erp-pcad__legend">Origens do pedido faturado</legend>
    <p class="erp-empresas-parametros__hint">
        Com <strong>Ativar Expedição</strong> marcado, pedidos faturados das origens selecionadas entram automaticamente no Controle de Expedição.
    </p>

    <div class="erp-empresas-parametros__checks">
        @foreach ($origens as $field)
            @php($meta = $fields[$field])
            <label class="erp-pcad__check">
                <input type="checkbox" wire:model="data.{{ $field }}">
                {{ $meta['label'] }}
            </label>
        @endforeach
    </div>
</fieldset>
