<button
    type="button"
    class="erp-emp-imp-apply-btn"
    wire:click="pedirAplicarImpostoPadraoEmProdutos('{{ $field }}')"
    title="Aplicar {{ $label }} em todos os produtos"
    aria-label="Aplicar {{ $label }} em todos os produtos"
    @disabled($this->impostoPadraoApplyProgressOpen)
>
    <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M12 5v10"/>
        <path d="m8 11 4 4 4-4"/>
        <path d="M5 19h14"/>
    </svg>
</button>
