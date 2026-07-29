<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->consultaChaveModalOpen): ?>
    <?php
        $chaveDigits = preg_replace('/\D/', '', $this->consultaChaveInput) ?? '';
        $chaveLength = strlen($chaveDigits);
    ?>

    <div
        class="erp-lookup-modal erp-nfce-fiscal-modal erp-nf-forn-consulta-chave-modal"
        wire:keydown.escape.window="closeConsultaChaveModal"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeConsultaChaveModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-nf-forn-consulta-chave-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-nf-forn-consulta-chave-title">Consulta por chave</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeConsultaChaveModal" title="Fechar">✕</button>
            </div>

            <div class="erp-lookup-modal__body">
                <p class="erp-nfce-fiscal-modal__hint">
                    Informe a chave de acesso da NF-e (44 dígitos) para consultar o XML na SEFAZ.
                </p>

                <div class="erp-nfce-fiscal-modal__field-group">
                    <label class="erp-nfce-fiscal-modal__label" for="erp-nf-forn-consulta-chave-input">Chave de acesso</label>
                    <input
                        id="erp-nf-forn-consulta-chave-input"
                        type="text"
                        wire:model.live.debounce.150ms="consultaChaveInput"
                        wire:keydown.enter.prevent="confirmarConsultaChave"
                        class="erp-nfce-fiscal-modal__input erp-nfce-fiscal-modal__input--chave"
                        maxlength="54"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="Somente números"
                        data-erp-nf-forn-consulta-chave-input
                    >
                    <p class="<?php echo \Illuminate\Support\Arr::toCssClasses([
                        'erp-nfce-fiscal-modal__counter',
                        'erp-nfce-fiscal-modal__counter--ok' => $chaveLength === 44,
                    ]); ?>">
                        <?php echo e($chaveLength); ?>/44
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($chaveLength > 0 && $chaveLength < 44): ?>
                            — faltam <?php echo e(44 - $chaveLength); ?> dígitos
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions">
                <button
                    type="button"
                    wire:click="confirmarConsultaChave"
                    wire:loading.attr="disabled"
                    wire:target="confirmarConsultaChave"
                    class="erp-pcad-actions__btn"
                    <?php if($chaveLength !== 44): echo 'disabled'; endif; ?>
                >
                    <span class="erp-pcad-actions__icon">🔍</span>
                    <span wire:loading.remove wire:target="confirmarConsultaChave" class="erp-pcad-actions__label">Consultar</span>
                    <span wire:loading wire:target="confirmarConsultaChave" class="erp-pcad-actions__label">Consultando…</span>
                </button>
                <button type="button" wire:click="closeConsultaChaveModal" class="erp-pcad-actions__btn">
                    <span class="erp-pcad-actions__icon">↩</span>
                    <span class="erp-pcad-actions__label">Cancelar</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/consulta-chave-modal.blade.php ENDPATH**/ ?>