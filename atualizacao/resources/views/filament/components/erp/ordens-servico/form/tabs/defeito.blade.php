<div class="erp-os-panel erp-os-panel--fill">
    <h3 class="erp-os-panel__title">Defeito / Problema</h3>
    <label class="erp-os-form-label" for="os-problema">Descreva o problema relatado</label>
    <textarea
        id="os-problema"
        wire:model="problema"
        @disabled($readOnly)
        class="erp-os-form-textarea"
        placeholder="Informe o defeito ou problema apresentado..."
    ></textarea>
</div>
