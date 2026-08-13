@php
    $tipos = \App\Models\CaixaConta::tipoLabels();
    $isEdit = filled($this->formId);
@endphp

@if ($this->showForm)
    <div class="erp-contas-caixa-modal" x-data
         x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.handleContasCaixaEscape(); }
            if ($event.key === 'F2') { $event.preventDefault(); $wire.saveContaCaixa(); }
         ">
        <div class="erp-contas-caixa-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-contas-caixa-modal__dialog" role="dialog" aria-modal="true" aria-label="Cadastro de conta caixa">
            <div class="erp-contas-caixa-modal__titlebar">
                <strong>{{ $isEdit ? 'Alterar conta caixa' : 'Nova conta caixa' }}</strong>
                <button type="button" class="erp-contas-caixa-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-contas-caixa-modal__body">
                <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--code">
                    <span>Código</span>
                    <input type="number" min="1" wire:model="form.codigo">
                </label>
                @error('form.codigo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Descrição</span>
                    <input type="text" wire:model="form.nome" maxlength="120" autofocus data-erp-uppercase>
                </label>
                @error('form.nome') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Tipo</span>
                    <select wire:model="form.tipo">
                        @foreach ($tipos as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @error('form.tipo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__check">
                    <input type="checkbox" wire:model="form.ativo">
                    Conta ativa
                </label>
            </div>

            <div class="erp-contas-caixa-modal__footer">
                <button type="button" class="is-cancel" wire:click="closeForm">Cancelar</button>
                <button type="button" class="is-save" wire:click="saveContaCaixa">Gravar</button>
            </div>
        </div>
    </div>
@endif
