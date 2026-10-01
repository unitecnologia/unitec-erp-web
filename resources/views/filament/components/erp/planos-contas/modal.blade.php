@php
    $isEdit = filled($this->formId);
@endphp

@if ($this->showForm)
    <div class="erp-contas-caixa-modal" x-data
         x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $event.stopPropagation(); $wire.closeForm(); }
         ">
        <div class="erp-contas-caixa-modal__backdrop" wire:click="closeForm"></div>

        <div class="erp-contas-caixa-modal__dialog" role="dialog" aria-modal="true" aria-label="Cadastro de plano de contas">
            <div class="erp-contas-caixa-modal__titlebar">
                <strong>{{ $isEdit ? 'Alterar plano de contas' : 'Novo plano de contas' }}</strong>
                <button type="button" class="erp-contas-caixa-modal__close" wire:click="closeForm" aria-label="Fechar">&times;</button>
            </div>

            <div class="erp-contas-caixa-modal__body">
                <label class="erp-contas-caixa-modal__field erp-contas-caixa-modal__field--code">
                    <span>Código</span>
                    <input type="number" min="1" wire:model="form.codigo" autofocus>
                </label>
                @error('form.codigo') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Conta completa</span>
                    <input type="text" wire:model="form.conta_completa" maxlength="80" data-erp-uppercase>
                </label>
                @error('form.conta_completa') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Descrição</span>
                    <input type="text" wire:model="form.descricao" maxlength="120" data-erp-uppercase>
                </label>
                @error('form.descricao') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror

                <label class="erp-contas-caixa-modal__field">
                    <span>Tipo</span>
                    <select wire:model="form.dc">
                        <option value="">—</option>
                        <option value="C">CRÉDITO</option>
                        <option value="D">DÉBITO</option>
                    </select>
                </label>
                @error('form.dc') <p class="erp-contas-caixa-modal__error">{{ $message }}</p> @enderror
            </div>

            <div class="erp-contas-caixa-modal__footer">
                <button type="button" class="is-cancel" wire:click="closeForm">Cancelar</button>
                <button type="button" class="is-save" wire:click="savePlanoConta">Gravar</button>
            </div>
        </div>
    </div>
@endif
