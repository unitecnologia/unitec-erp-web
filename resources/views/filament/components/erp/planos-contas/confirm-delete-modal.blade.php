@if ($this->deleteConfirmOpen)
    <div
        class="erp-plano-conta-confirm"
        x-data
        x-init="$nextTick(() => $el.querySelector('.erp-plano-conta-confirm__delete')?.focus())"
        x-on:keydown.window="
            if ($event.key === 'Escape') { $event.preventDefault(); $event.stopPropagation(); $wire.cancelDeletePlanoConta(); }
            if ($event.key === 'Enter' || $event.key === 'F5') { $event.preventDefault(); $event.stopPropagation(); $wire.confirmDeletePlanoConta(); }
        "
    >
        <div class="erp-plano-conta-confirm__backdrop" wire:click="cancelDeletePlanoConta"></div>

        <section
            class="erp-plano-conta-confirm__dialog"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="erp-plano-conta-confirm-title"
            wire:click.stop
        >
            <div class="erp-plano-conta-confirm__icon" aria-hidden="true">!</div>

            <div class="erp-plano-conta-confirm__content">
                <h2 id="erp-plano-conta-confirm-title">Excluir plano de contas?</h2>
                <p>Esta ação não poderá ser desfeita.</p>
                @if (filled($this->deleteConfirmLabel))
                    <p class="erp-plano-conta-confirm__account">{{ $this->deleteConfirmLabel }}</p>
                @endif
            </div>

            <footer class="erp-plano-conta-confirm__footer">
                <button
                    type="button"
                    class="erp-plano-conta-confirm__cancel"
                    wire:click="cancelDeletePlanoConta"
                >
                    Não, manter
                </button>
                <button
                    type="button"
                    class="erp-plano-conta-confirm__delete"
                    wire:click="confirmDeletePlanoConta"
                    wire:loading.attr="disabled"
                    wire:target="confirmDeletePlanoConta"
                >
                    Sim, excluir
                </button>
            </footer>
        </section>
    </div>
@endif
