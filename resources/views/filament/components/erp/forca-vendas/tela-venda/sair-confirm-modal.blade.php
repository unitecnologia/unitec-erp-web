{{-- Mesmo padrão visual de filament.components.erp.aviso-modal (Produtos).
     Sem confirm()/beforeunload nativos — só este modal UNI. --}}
@if ($this->fvSairConfirmOpen)
    <div
        class="erp-aviso-modal erp-aviso-modal--warning"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-fv-sair-confirm-title"
        wire:keydown.escape="dismissSairConfirm"
    >
        <div class="erp-aviso-modal__backdrop" wire:click="dismissSairConfirm"></div>

        <div class="erp-aviso-modal__box">
            <div class="erp-aviso-modal__icon" aria-hidden="true">!</div>

            <h2 class="erp-aviso-modal__title" id="erp-fv-sair-confirm-title">Sair da venda?</h2>

            <div class="erp-aviso-modal__body">
                <p class="erp-aviso-modal__text">
                    Existem itens lançados nesta venda. Ao sair, ela será gravada como <strong>PENDENTE</strong>
                    e poderá ser reaberta ou cancelada pelo Monitor.
                </p>
            </div>

            <div class="erp-aviso-modal__actions">
                <button
                    type="button"
                    id="erp-fv-sair-confirm-sim"
                    class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                    wire:loading.attr="disabled"
                    wire:target="confirmSairSalvarPendente,gravarPedidoPendente"
                    x-on:click="
                        if ($wire.gravando) { return; }
                        window.erpFvTvBeginAuthorizedLeave?.();
                        if (window.__erpFvTvEditing) {
                            const v = window.erpFvTvBarValues?.() || {};
                            $wire.confirmSairSalvarPendente(v.qtd ?? null, v.preco ?? null);
                        } else {
                            $wire.confirmSairSalvarPendente();
                        }
                    "
                >Salvar pendente e sair</button>

                <button
                    type="button"
                    id="erp-fv-sair-confirm-nao"
                    class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                    wire:click="dismissSairConfirm"
                    wire:loading.attr="disabled"
                    wire:target="confirmSairSalvarPendente,gravarPedidoPendente"
                >Continuar na venda</button>
            </div>

            <p class="erp-aviso-modal__hint">F3 também grava como pendente. F4 fecha/fatura a venda.</p>
        </div>
    </div>
@endif
