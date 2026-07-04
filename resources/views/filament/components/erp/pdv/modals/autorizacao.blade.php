@if ($this->activeModal === 'autorizacao')
    <div class="erp-pdv-modal" role="dialog" aria-label="Autorização">
        <div class="erp-pdv-modal__backdrop" wire:click="cancelPdvAutorizacao"></div>
        <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
            <header class="erp-pdv-modal__header">
                <h2>Autorização do Supervisor</h2>
            </header>
            <div class="erp-pdv-modal__body">
                <p class="erp-pdv-modal__hint">Informe sua senha para continuar.</p>
                <label class="erp-pdv-modal__label" for="erp-pdv-auth-password">Senha</label>
                <input
                    id="erp-pdv-auth-password"
                    type="password"
                    wire:model="pdvAuthPassword"
                    wire:keydown.enter.prevent="confirmPdvAutorizacao"
                    class="erp-pdv-modal__input"
                    autocomplete="off"
                >
            </div>
            <footer class="erp-pdv-modal__footer">
                <button type="button" wire:click="confirmPdvAutorizacao" class="erp-pdv-modal__btn erp-pdv-modal__btn--primary">Autorizar</button>
                <button type="button" wire:click="cancelPdvAutorizacao" class="erp-pdv-modal__btn">Cancelar</button>
            </footer>
        </div>
    </div>
@endif
