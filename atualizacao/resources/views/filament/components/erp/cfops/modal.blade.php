@php
    use App\Models\Cfop;
@endphp

@if ($this->showForm)
    <div
        class="erp-fpgto-modal"
        x-data
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleCfopEscape(); }
            if ($event.key === 'F5') { $event.preventDefault(); $wire.saveCfop(); }
        "
    >
        <div class="erp-fpgto-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-fpgto-modal__dialog erp-cfop-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>Cadastro CFOP</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body erp-cfop-modal__body">
                <div class="erp-cfop-modal__row">
                    <label class="erp-fpgto-field erp-cfop-modal__code">
                        <span class="erp-fpgto-field__label">Código</span>
                        <input
                            type="text"
                            inputmode="numeric"
                            maxlength="4"
                            wire:model="form.codigo"
                            class="erp-fpgto-field__input erp-fpgto-field__input--code"
                        >
                    </label>
                    <label class="erp-fpgto-field erp-cfop-modal__nome">
                        <span class="erp-fpgto-field__label">Nome</span>
                        <input type="text" wire:model="form.descricao" maxlength="150" class="erp-fpgto-field__input" autofocus>
                    </label>
                </div>
                @error('form.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                @error('form.descricao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                <div class="erp-cfop-modal__row erp-cfop-modal__row--opts">
                    <div class="erp-cfop-modal__left">
                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Operação</span>
                            <select wire:model="form.operacao" class="erp-fpgto-field__input">
                                @foreach (Cfop::operacaoLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('form.operacao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                        <label class="erp-fpgto-field">
                            <span class="erp-fpgto-field__label">Tipo</span>
                            <select wire:model="form.tipo" class="erp-fpgto-field__input erp-cfop-modal__tipo">
                                @foreach (Cfop::tipoLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        @error('form.tipo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror
                    </div>

                    <div class="erp-cfop-modal__checks">
                        <label class="erp-fpgto-check">
                            <input type="checkbox" wire:model="form.movimenta_estoque"> Movimenta Estoque
                        </label>
                        <label class="erp-fpgto-check">
                            <input type="checkbox" wire:model="form.ativo"> Ativo
                        </label>
                    </div>
                </div>
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save" wire:click="saveCfop">
                    <span class="erp-fpgto-modal__btn-icon">✓</span> <kbd>F5</kbd> | Salvar
                </button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel" wire:click="closeForm">
                    <span class="erp-fpgto-modal__btn-icon">✕</span> <kbd>ESC</kbd> | Sair
                </button>
            </div>
        </div>
    </div>
@endif
