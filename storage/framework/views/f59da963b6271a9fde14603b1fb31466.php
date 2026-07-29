<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->nfFornFiscalOverlayTitulo)): ?>
    <?php
        $tone = $this->nfFornFiscalOverlayTone ?? 'error';
        $toneClass = match ($tone) {
            'warning' => 'erp-nfe-fiscal-overlay--warning',
            'info' => 'erp-nfe-fiscal-overlay--info',
            default => '',
        };
    ?>
    <div
        class="erp-nfe-fiscal-overlay <?php echo e($toneClass); ?>"
        role="alertdialog"
        aria-labelledby="erp-nf-forn-fiscal-overlay-title"
        aria-live="assertive"
    >
        <div class="erp-nfe-fiscal-overlay__box">
            <div class="erp-nfe-fiscal-overlay__icon" aria-hidden="true">!</div>

            <h2 id="erp-nf-forn-fiscal-overlay-title" class="erp-nfe-fiscal-overlay__title">
                <?php echo e($this->nfFornFiscalOverlayTitulo); ?>

            </h2>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->nfFornFiscalOverlayCodigo)): ?>
                <p class="erp-nfe-fiscal-overlay__codigo">
                    Código SEFAZ: <strong><?php echo e($this->nfFornFiscalOverlayCodigo); ?></strong>
                </p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->nfFornFiscalOverlayMensagem)): ?>
                <div class="erp-nfe-fiscal-overlay__text">
                    <?php echo nl2br(e($this->nfFornFiscalOverlayMensagem)); ?>

                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <p class="erp-nfe-fiscal-overlay__origem">Esta é uma mensagem da SEFAZ — <?php echo e($this->nfFornFiscalOverlayOrigem ?? 'Distribuição DF-e'); ?>.</p>

            <button
                type="button"
                wire:click="closeNfFornFiscalOverlay"
                class="erp-nfe-fiscal-overlay__btn"
                id="erp-nf-forn-fiscal-overlay-entendido"
            >Entendido</button>

            <p class="erp-nfe-fiscal-overlay__hint">Clique em Entendido para continuar.</p>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/fiscal-overlay.blade.php ENDPATH**/ ?>