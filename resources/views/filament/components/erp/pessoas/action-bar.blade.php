<div class="erp-pessoas-actions">
    @if (erp_can('pessoas.create'))
        @if ($this->embedsInPdv)
            {{-- PDV iframe: full reload obrigatório (mesmo caminho de createPerson). --}}
            <button type="button" wire:click="createPerson" class="erp-pessoas-actions__btn" data-erp-key="F2">
                <span class="erp-pessoas-actions__icon">+</span>
                <span class="erp-pessoas-actions__label"><kbd>F2</kbd> | Novo</span>
            </button>
        @else
            {{--
              URL calculada no clique: abas de tipo atualizam $wire.tipoFilter com skip render
              (PersonListTable), então o href estático ficaria defasado.
            --}}
            <a
                href="{{ $this->personCreateUrl() }}"
                class="erp-pessoas-actions__btn"
                data-erp-key="F2"
                data-erp-spa-nav="1"
                data-erp-create-base="{{ \App\Filament\Resources\PersonResource::getUrl('create') }}"
                x-on:click.prevent="
                    const base = $el.dataset.erpCreateBase || $el.getAttribute('href');
                    const tipo = $wire.tipoFilter || 'clientes';
                    const url = (tipo && tipo !== 'clientes')
                        ? (base + (base.includes('?') ? '&' : '?') + 'tipo=' + encodeURIComponent(tipo))
                        : base;
                    window.Livewire.navigate(url);
                "
            >
                <span class="erp-pessoas-actions__icon">+</span>
                <span class="erp-pessoas-actions__label"><kbd>F2</kbd> | Novo</span>
            </a>
        @endif
    @endif
    @if (erp_can('pessoas.update'))
        <button type="button" wire:click="editPerson" class="erp-pessoas-actions__btn" data-erp-key="F3">
            <span class="erp-pessoas-actions__icon">✎</span>
            <span class="erp-pessoas-actions__label"><kbd>F3</kbd> | Alterar</span>
        </button>
    @endif
    @if (erp_can('pessoas.print'))
        <button type="button" wire:click="printPeople" class="erp-pessoas-actions__btn" data-erp-key="F4">
            <span class="erp-pessoas-actions__icon">🖨</span>
            <span class="erp-pessoas-actions__label"><kbd>F4</kbd> | Imprimir</span>
        </button>
    @endif
    <button type="button" wire:click="refreshTable" class="erp-pessoas-actions__btn" data-erp-key="F5">
        <span class="erp-pessoas-actions__icon">↻</span>
        <span class="erp-pessoas-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-pessoas-actions__btn erp-pessoas-actions__btn--close">
        <span class="erp-pessoas-actions__icon erp-pessoas-actions__icon--close">✕</span>
        <span class="erp-pessoas-actions__label">Fechar</span>
    </button>
</div>
