@if ($this->cancelModalOpen)
    <div class="erp-comissoes__modal-backdrop" wire:click="$set('cancelModalOpen', false)">
        <div class="erp-comissoes__modal" wire:click.stop>
            <div class="erp-comissoes__modal-title">Cancelar comissão</div>
            <p class="erp-comissoes__modal-hint">O histórico é preservado. Vendas liberadas para novo fechamento.</p>
            <label class="erp-comissoes__field">
                <span>Motivo</span>
                <input type="text" class="erp-comissoes__input" wire:model="cancelMotivo" maxlength="255" />
            </label>
            <div class="erp-comissoes__modal-actions">
                <button type="button" class="erp-comissoes__btn" wire:click="$set('cancelModalOpen', false)">Voltar</button>
                <button type="button" class="erp-comissoes__btn erp-comissoes__btn--danger" wire:click="confirmarCancelarComissao">Confirmar</button>
            </div>
        </div>
    </div>
@endif
