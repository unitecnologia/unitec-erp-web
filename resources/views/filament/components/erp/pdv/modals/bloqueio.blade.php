@if ($this->activeModal === 'bloqueio' || $this->pdvBloqueado)
    <div class="erp-pdv-modal erp-pdv-modal--bloqueio" role="dialog" aria-label="PDV bloqueado">
        <div class="erp-pdv-modal__backdrop erp-pdv-modal__backdrop--solid"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>PDV Bloqueado</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">Inatividade detectada. Informe sua senha para continuar.</p>
                <label class="erp-pdv-modal__label" for="erp-pdv-unlock-password">Senha</label>
                <input
                    id="erp-pdv-unlock-password"
                    type="password"
                    wire:model="pdvUnlockPassword"
                    wire:keydown.enter.prevent="confirmUnlockPdv"
                    class="erp-pdv-modal__input"
                    autocomplete="off"
                >
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmUnlockPdv" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Desbloquear</button>
            </footer>
        </div>
    </div>
@endif
