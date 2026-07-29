<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->notaFornecedorDanfeModalOpen && $this->notaFornecedorDanfeId): ?>
    <div
        class="erp-lookup-modal erp-nfe-espelho-modal erp-nf-forn-danfe-modal"
        wire:keydown.escape.window="closeNotaFornecedorDanfe"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeNotaFornecedorDanfe"></div>

        <div
            class="erp-lookup-modal__window erp-nfe-espelho-modal__window"
            role="dialog"
            aria-modal="true"
            aria-labelledby="erp-nf-forn-danfe-title"
        >
            <div class="erp-lookup-modal__titlebar erp-nfe-espelho-modal__titlebar">
                <span id="erp-nf-forn-danfe-title">DANFE — Nota de Fornecedor</span>
                <button type="button" class="erp-lookup-modal__close" wire:click="closeNotaFornecedorDanfe" title="Fechar">✕</button>
            </div>

            <div class="erp-nfe-espelho-modal__toolbar">
                <button type="button" wire:click="printNotaFornecedorDanfe" class="erp-nfe-espelho-modal__btn">
                    <span>🖨</span> Imprimir
                </button>
                <button type="button" wire:click="downloadNotaFornecedorDanfePdf" class="erp-nfe-espelho-modal__btn">
                    <span>⬇</span> Salvar PDF
                </button>
                <button type="button" wire:click="closeNotaFornecedorDanfe" class="erp-nfe-espelho-modal__btn erp-nfe-espelho-modal__btn--close">
                    <span>✕</span> Fechar
                </button>
            </div>

            <div class="erp-nfe-espelho-modal__body">
                <iframe
                    class="erp-nfe-espelho-modal__frame"
                    src="<?php echo e(route('erp.reports.nota-fornecedor-danfe', ['nota' => $this->notaFornecedorDanfeId, 'embed' => 1])); ?>"
                    title="DANFE da nota de fornecedor"
                ></iframe>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/notas-fornecedores/danfe-modal.blade.php ENDPATH**/ ?>