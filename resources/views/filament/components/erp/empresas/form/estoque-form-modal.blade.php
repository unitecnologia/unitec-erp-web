@if ($this->empresaEstoqueModalOpen)
    <div
        class="erp-lookup-modal erp-contador-form-modal erp-codigo-nome-modal"
        wire:keydown.escape.window="closeEmpresaEstoqueModal"
        wire:keydown.f5.window.prevent="saveEmpresaEstoque"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEmpresaEstoqueModal"></div>

        <div
            class="erp-lookup-modal__window erp-contador-form-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="empresa-estoque-modal-title"
        >
            <div class="erp-lookup-modal__titlebar">
                <span id="empresa-estoque-modal-title">
                    {{ $this->empresaEstoqueModalId ? 'Alterar Estoque' : 'Incluir Estoque' }}
                </span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeEmpresaEstoqueModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-contador-form-modal__body erp-codigo-nome-modal__body">
                <div class="erp-pcad-form erp-contador-form-modal__form erp-codigo-nome-modal__form">
                    <section class="erp-codigo-nome-form__section">
                        <h3 class="erp-codigo-nome-form__section-title">Cadastro</h3>
                        <div class="erp-codigo-nome-form__section-grid">
                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-pcad-form__label" for="empresa-estoque-codigo">Código</label>
                                <input
                                    id="empresa-estoque-codigo"
                                    type="text"
                                    wire:model="empresaEstoqueForm.codigo"
                                    class="erp-pcad-form__input erp-pcad-form__input--xs"
                                    @if ($this->empresaEstoqueModalId) readonly @endif
                                >
                                @error('empresaEstoqueForm.codigo')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="empresa-estoque-nome">Nome</label>
                                <input
                                    id="empresa-estoque-nome"
                                    type="text"
                                    wire:model="empresaEstoqueForm.nome"
                                    class="erp-pcad-form__input erp-pcad-form__input--grow"
                                    data-erp-uppercase
                                    autofocus
                                >
                                @error('empresaEstoqueForm.nome')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row erp-codigo-nome-form__row--single">
                                <label class="erp-pcad-form__label" for="empresa-estoque-vendedor">Vendedor</label>
                                <select
                                    id="empresa-estoque-vendedor"
                                    wire:model="empresaEstoqueForm.vendedor_id"
                                    class="erp-pcad-form__select erp-pcad-form__input--grow"
                                >
                                    <option value="">— opcional —</option>
                                    @foreach ($this->empresaEstoqueVendedorOptions() as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('empresaEstoqueForm.vendedor_id')
                                    <span class="erp-contador-form-modal__error erp-contador-form-modal__error--row">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="erp-pcad-form__row erp-codigo-nome-form__row">
                                <label class="erp-vform__check" for="empresa-estoque-ativo">
                                    <input id="empresa-estoque-ativo" type="checkbox" wire:model="empresaEstoqueForm.ativo">
                                    Ativo
                                </label>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-contador-form-modal__actions erp-codigo-nome-modal__actions">
                <button type="button" wire:click="saveEmpresaEstoque" wire:loading.attr="disabled" wire:target="saveEmpresaEstoque" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label" wire:loading.remove wire:target="saveEmpresaEstoque"><kbd>F5</kbd> | Gravar</span>
                    <span class="erp-pcad-actions__label" wire:loading wire:target="saveEmpresaEstoque">Salvando…</span>
                </button>
                <button type="button" wire:click="closeEmpresaEstoqueModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
                </button>
            </div>
        </div>
    </div>

    @include('filament.components.erp.form-scripts')
@endif
