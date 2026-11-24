{{--
  Botão compacto + Cadastrar Cliente (padrão UNI).
  Props: $wireClick (default openPessoasCadastro), $disabled (bool), $title

  Atenção: Filament Schema View injeta métodos públicos no escopo (ex.: disabled() → Closure).
  Por isso a flag usa $btnDisabled internamente e só lê $disabled se for escalar/bool.
--}}
@php
    $wireClick = $wireClick ?? 'openPessoasCadastro';
    $title = $title ?? 'Cadastrar cliente';
    $btnDisabled = false;
    if (array_key_exists('disabled', get_defined_vars())
        && (is_bool($disabled) || is_int($disabled) || is_string($disabled))
    ) {
        $btnDisabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    }
@endphp
<button
    type="button"
    class="erp-nfe-actions__btn erp-fv-tv-btn erp-fv-tv-btn--cad-cliente erp-btn-cadastrar-cliente"
    wire:click="{{ $wireClick }}"
    title="{{ $title }}"
    @disabled($btnDisabled)
>
    <span class="erp-fv-tv-btn__icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 5v14"/>
            <path d="M5 12h14"/>
        </svg>
    </span>
    <span class="erp-nfe-actions__label">Cadastrar Cliente</span>
</button>
