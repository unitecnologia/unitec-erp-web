@if ($this->nfeEmitenteModalOpen)
    <div
        class="erp-fv-mon-nfe-emitente-modal"
        x-data
        x-init="$nextTick(() => document.getElementById('erp-fv-mon-nfe-emitente-0')?.focus())"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $wire.closeNfeEmitenteModal(); }
        "
    >
        <div class="erp-fv-mon-nfe-emitente-modal__backdrop" wire:click="closeNfeEmitenteModal"></div>

        <div
            class="erp-fv-mon-nfe-emitente-modal__dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-fv-mon-nfe-emitente-title"
        >
            <div class="erp-fv-mon-nfe-emitente-modal__titlebar">
                <span id="erp-fv-mon-nfe-emitente-title">Empresa emitente da NF-e</span>
                <button
                    type="button"
                    class="erp-fv-mon-nfe-emitente-modal__close"
                    wire:click="closeNfeEmitenteModal"
                    aria-label="Fechar"
                >&times;</button>
            </div>

            <div class="erp-fv-mon-nfe-emitente-modal__body">
                <p class="erp-fv-mon-nfe-emitente-modal__hint">
                    Selecione a empresa que emitirá a NF-e
                    @if (count($this->nfeEmitentePendingVendaIds) > 1)
                        das {{ count($this->nfeEmitentePendingVendaIds) }} vendas selecionadas
                    @else
                        da venda selecionada
                    @endif.
                    A empresa logada não será alterada.
                </p>

                <fieldset class="erp-fv-mon-nfe-emitente-modal__list">
                    <legend style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0;">Empresas emitentes</legend>
                    @foreach ($this->nfeEmitenteOpcoes as $i => $opcao)
                        <label class="erp-fv-mon-nfe-emitente-modal__item">
                            <input
                                id="erp-fv-mon-nfe-emitente-{{ $i }}"
                                type="radio"
                                name="nfeEmpresaEmitenteId"
                                value="{{ $opcao['id'] }}"
                                wire:model.live="nfeEmpresaEmitenteId"
                            >
                            <span>
                                <strong>{{ $opcao['nome'] !== '' ? $opcao['nome'] : ($opcao['fantasia'] ?: 'Empresa #'.$opcao['id']) }}</strong>
                                @if ($opcao['fantasia'] !== '' && $opcao['fantasia'] !== $opcao['nome'])
                                    <span class="erp-fv-mon-nfe-emitente-modal__fantasia">{{ $opcao['fantasia'] }}</span>
                                @endif
                                @if ($opcao['cnpj'] !== '')
                                    <span class="erp-fv-mon-nfe-emitente-modal__cnpj">CNPJ {{ $opcao['cnpj'] }}</span>
                                @endif
                            </span>
                        </label>
                    @endforeach
                </fieldset>
            </div>

            <div class="erp-fv-mon-nfe-emitente-modal__actions">
                <button
                    type="button"
                    class="erp-fv-mon-nfe-emitente-modal__btn erp-fv-mon-nfe-emitente-modal__btn--ok"
                    wire:click="confirmarEmpresaEmitenteNfe"
                    @disabled(! $this->nfeEmpresaEmitenteId)
                >Confirmar</button>
                <button
                    type="button"
                    class="erp-fv-mon-nfe-emitente-modal__btn"
                    wire:click="closeNfeEmitenteModal"
                >Cancelar</button>
            </div>
        </div>
    </div>
@endif
