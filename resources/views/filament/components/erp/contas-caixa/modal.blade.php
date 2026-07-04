@php
    $tipos = \App\Models\CaixaConta::tipoLabels();
    $situacoes = \App\Models\CaixaConta::situacaoLabels();
@endphp

@if ($this->showForm)
    <div class="erp-fpgto-modal" x-data
         x-on:keydown.escape.window="$wire.closeForm()"
         x-on:keydown.window="if ($event.key === 'F2') { $event.preventDefault(); $wire.saveContaCaixa(); }">
        <div class="erp-fpgto-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-fpgto-modal__dialog" role="dialog" aria-modal="true">
            <div class="erp-fpgto-modal__titlebar">
                <span>Contas Caixa</span>
                <button type="button" class="erp-fpgto-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-fpgto-modal__body">
                <div class="erp-fpgto-modal__col">
                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Código</span>
                        <input type="number" min="1" wire:model="form.codigo" class="erp-fpgto-field__input erp-fpgto-field__input--code">
                    </label>
                    @error('form.codigo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Descrição</span>
                        <input type="text" wire:model="form.nome" maxlength="120" class="erp-fpgto-field__input" autofocus>
                    </label>
                    @error('form.nome') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Tipo</span>
                        <select wire:model="form.tipo" class="erp-fpgto-field__input">
                            @foreach ($tipos as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('form.tipo') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-fpgto-field">
                        <span class="erp-fpgto-field__label">Situação</span>
                        <select wire:model="form.situacao" class="erp-fpgto-field__input">
                            @foreach ($situacoes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    @error('form.situacao') <p class="erp-fpgto-modal__error">{{ $message }}</p> @enderror

                    <label class="erp-fpgto-check">
                        <input type="checkbox" wire:model="form.ativo"> Ativo
                    </label>
                </div>
            </div>

            <div class="erp-fpgto-modal__footer">
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--save" wire:click="saveContaCaixa">
                    <span class="erp-fpgto-modal__btn-icon">✓</span> <kbd>F2</kbd> | Gravar
                </button>
                <button type="button" class="erp-fpgto-modal__btn erp-fpgto-modal__btn--cancel" wire:click="closeForm">
                    <span class="erp-fpgto-modal__btn-icon">✕</span> <kbd>ESC</kbd> | Sair
                </button>
            </div>
        </div>
    </div>
@endif
