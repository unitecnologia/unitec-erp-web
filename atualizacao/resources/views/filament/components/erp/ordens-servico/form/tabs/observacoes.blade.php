<div class="erp-os-panel erp-os-panel--fill">
    <h3 class="erp-os-panel__title">Observações</h3>
    <div class="erp-os-form-row" style="align-items: stretch;">
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-obs">Observações</label>
            <textarea id="os-obs" wire:model="observacoes" @disabled($readOnly) class="erp-os-form-textarea"></textarea>
        </div>
        <div class="erp-os-form-group erp-os-form-group--grow">
            <label class="erp-os-form-label" for="os-laudo">Laudo</label>
            <textarea id="os-laudo" wire:model="laudo" @disabled($readOnly) class="erp-os-form-textarea"></textarea>
        </div>
    </div>
</div>
