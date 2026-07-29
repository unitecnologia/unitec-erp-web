<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->showEtiquetas): ?>
    <div
        class="erp-balanca-etq"
        role="dialog"
        aria-modal="true"
        aria-labelledby="balanca-etq-title"
        wire:ignore.self
        wire:keydown.f5.window.prevent="salvarEtiquetas"
        x-on:keydown.escape.window="
            $event.stopImmediatePropagation();
            $event.preventDefault();
            $wire.closeEtiquetas();
        "
    >
        <div class="erp-balanca-etq__backdrop" wire:click="closeEtiquetas" aria-hidden="true"></div>

        <div class="erp-balanca-etq__dialog">
            <header class="erp-balanca-etq__titlebar">
                <span id="balanca-etq-title" class="erp-balanca-etq__title">Configuração de Etiquetas / Código de Barras</span>
                <button
                    type="button"
                    class="erp-balanca__close"
                    wire:click="closeEtiquetas"
                    title="ESC | Fechar"
                    aria-label="Fechar"
                >&times;</button>
            </header>

            <div class="erp-balanca-etq__body">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->etiquetaFeedbackMsg)): ?>
                    <div
                        class="erp-balanca__feedback erp-balanca__feedback--<?php echo e($this->etiquetaFeedbackTipo); ?>"
                        role="status"
                        wire:key="balanca-etq-feedback-<?php echo e(md5($this->etiquetaFeedbackMsg.$this->etiquetaFeedbackTipo)); ?>"
                    >
                        <p class="erp-balanca__feedback-text"><?php echo e($this->etiquetaFeedbackMsg); ?></p>
                        <button type="button" class="erp-balanca__feedback-close" wire:click="dismissEtiquetaFeedback" aria-label="Fechar mensagem">&times;</button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <p class="erp-balanca-etq__intro">
                    Padrões EAN de etiqueta de balança — sempre iniciam com o prefixo
                    <strong><?php echo e($this->etiquetaPrefixo); ?></strong>.
                    Selecione o modelo ou ajuste prefixo e dígitos conforme a balança.
                </p>

                <div class="erp-balanca-etq__diagrams" role="list">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->etiquetaDiagrams(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $diagram): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button
                            type="button"
                            class="erp-balanca-etq__card <?php echo e((int) $this->etiquetaModelo === (int) $diagram['modelo'] ? 'is-active' : ''); ?>"
                            wire:click="selectEtiquetaDiagram(<?php echo e((int) $diagram['modelo']); ?>)"
                            role="listitem"
                            title="Usar modelo <?php echo e($diagram['title']); ?>"
                        >
                            <span class="erp-balanca-etq__card-num"><?php echo e($diagram['title']); ?></span>
                            <div class="erp-balanca-etq__barcode" aria-hidden="true">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $diagram['parts']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $part): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span class="erp-balanca-etq__seg erp-balanca-etq__seg--<?php echo e($part['role']); ?>">
                                        <span class="erp-balanca-etq__seg-val"><?php echo e($part['v']); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($part['cap'])): ?>
                                            <span class="erp-balanca-etq__seg-cap"><?php echo e($part['cap']); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <span class="erp-balanca-etq__card-meta">
                                <?php echo e($diagram['digitos']); ?> dig. · <?php echo e($diagram['valor'] === 'total' ? 'Total' : 'Peso'); ?>

                            </span>
                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="erp-balanca-etq__form">
                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-modelo">Modelo</label>
                        <select
                            id="balanca-etq-modelo"
                            class="erp-balanca__select"
                            wire:model.live="etiquetaModelo"
                        >
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->etiquetaModeloOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </select>
                    </div>

                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-prefixo">Prefixo Cód.Barra</label>
                        <input
                            id="balanca-etq-prefixo"
                            type="text"
                            class="erp-balanca__input erp-balanca-etq__input--narrow"
                            wire:model.blur="etiquetaPrefixo"
                            maxlength="2"
                            inputmode="numeric"
                            spellcheck="false"
                        >
                    </div>

                    <div class="erp-balanca__field">
                        <label class="erp-balanca__label" for="balanca-etq-digitos">Dígitos</label>
                        <select
                            id="balanca-etq-digitos"
                            class="erp-balanca__select erp-balanca-etq__input--narrow"
                            wire:model.live="etiquetaDigitos"
                        >
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                        </select>
                    </div>
                </div>
            </div>

            <footer class="erp-balanca__footer erp-pcad-actions erp-pcad-actions--split">
                <div class="erp-balanca__footer-left">
                    <button
                        type="button"
                        class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                        data-erp-key="F5"
                        wire:click="salvarEtiquetas"
                        wire:loading.attr="disabled"
                        wire:target="salvarEtiquetas"
                    >
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label" wire:loading.remove wire:target="salvarEtiquetas"><kbd>F5</kbd> | Gravar</span>
                        <span class="erp-pcad-actions__label" wire:loading wire:target="salvarEtiquetas">Gravando…</span>
                    </button>
                </div>

                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
                    data-erp-key="Escape"
                    wire:click="closeEtiquetas"
                >
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </footer>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/balanca/etiquetas-modal.blade.php ENDPATH**/ ?>