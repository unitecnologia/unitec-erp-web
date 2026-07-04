@php
    $value = $this->data[$field] ?? '';
    $state = match (true) {
        $value === true, $value === 1, $value === '1' => 'yes',
        $value === false, $value === 0, $value === '0' => 'no',
        default => 'null',
    };
@endphp

<div class="erp-empresas-parametros__tri">
    <button
        type="button"
        wire:click="cycleTriStatePermission('{{ $field }}')"
        @class([
            'erp-empresas-parametros__tri-box',
            'erp-empresas-parametros__tri-box--yes' => $state === 'yes',
            'erp-empresas-parametros__tri-box--no' => $state === 'no',
            'erp-empresas-parametros__tri-box--null' => $state === 'null',
        ])
        aria-label="{{ $label }}"
    ></button>
    <span>{{ $label }}</span>
</div>
