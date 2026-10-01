@if ($this->servicoPrestadoModalOpen)
    <div
        class="erp-pdv-modal erp-os-servico-prestado-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="erp-os-servico-prestado-title"
        wire:keydown.escape.window="cancelarModalServicoPrestado"
    >
        <div class="erp-pdv-modal__backdrop" wire:click="cancelarModalServicoPrestado"></div>

        <div class="erp-pdv-modal__window erp-os-servico-prestado-modal__window">
            <header class="erp-pdv-modal__header">
                <h2 id="erp-os-servico-prestado-title">Serviços prestados</h2>
            </header>

            <div class="erp-pdv-modal__body">
                <p class="erp-os-servico-prestado-modal__hint">
                    Mesmo texto da impressão da OS e do campo enviado pelo app (serviço realizado).
                </p>
                <textarea
                    id="os-servico-prestado-modal-text"
                    wire:model="laudo"
                    class="erp-os-form-textarea erp-os-servico-prestado-modal__textarea"
                    rows="10"
                    maxlength="10000"
                    placeholder="Descreva o serviço prestado..."
                ></textarea>
            </div>

            <footer class="erp-pdv-modal__footer">
                <button
                    type="button"
                    wire:click="confirmarModalServicoPrestado"
                    class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                >OK</button>
                <button
                    type="button"
                    wire:click="cancelarModalServicoPrestado"
                    class="erp-pdv-modal__btn"
                >Cancelar</button>
            </footer>
        </div>
    </div>
@endif
