<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->emailModalOpen): ?>
    <div
        class="erp-lookup-modal erp-orc-email-modal"
        wire:keydown.escape="closeEmailModal"
        wire:keydown.f5.prevent="sendOrcamentoEmail"
    >
        <div class="erp-lookup-modal__backdrop" wire:click="closeEmailModal"></div>

        <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-orc-email-title">
            <div class="erp-lookup-modal__titlebar">
                <span id="erp-orc-email-title">Enviar Email</span>
                <button
                    type="button"
                    class="erp-lookup-modal__close"
                    wire:click="closeEmailModal"
                    title="Fechar"
                >✕</button>
            </div>

            <div class="erp-lookup-modal__body erp-orc-email-modal__body">
                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-to">Email:</label>
                    <input
                        id="erp-orc-email-to"
                        type="email"
                        wire:model="emailTo"
                        class="erp-orc-email-modal__input"
                        autocomplete="off"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['emailTo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <span class="erp-orc-email-modal__error"><?php echo e($message); ?></span>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-subject">Assunto:</label>
                    <input
                        id="erp-orc-email-subject"
                        type="text"
                        wire:model="emailSubject"
                        class="erp-orc-email-modal__input"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['emailSubject'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <span class="erp-orc-email-modal__error"><?php echo e($message); ?></span>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="erp-orc-email-modal__field">
                    <label class="erp-orc-email-modal__label" for="erp-orc-email-message">Mensagem:</label>
                    <input
                        id="erp-orc-email-message"
                        type="text"
                        wire:model="emailMessage"
                        class="erp-orc-email-modal__input"
                    >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['emailMessage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <span class="erp-orc-email-modal__error"><?php echo e($message); ?></span>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="erp-orc-email-modal__field">
                    <span class="erp-orc-email-modal__label">Anexo:</span>
                    <div class="erp-orc-email-modal__attachments">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->emailAttachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <button
                                type="button"
                                wire:click="selectEmailAttachment(<?php echo \Illuminate\Support\Js::from($attachment['id'])->toHtml() ?>)"
                                class="erp-orc-email-modal__attachment <?php echo e($this->emailSelectedAttachmentId === $attachment['id'] ? 'is-selected' : ''); ?>"
                            >
                                <?php echo e($attachment['display']); ?>

                            </button>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <span class="erp-orc-email-modal__attachments-empty">Nenhum anexo.</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="erp-orc-email-modal__attachment-actions">
                        <label class="erp-orc-email-modal__mini-btn">
                            <span aria-hidden="true">+</span>
                            Adicionar Anexo
                            <input
                                type="file"
                                wire:model="emailExtraUpload"
                                class="erp-orc-email-modal__file-input"
                            >
                        </label>
                        <button
                            type="button"
                            wire:click="removeSelectedEmailAttachment"
                            class="erp-orc-email-modal__mini-btn erp-orc-email-modal__mini-btn--danger"
                            <?php if(blank($this->emailSelectedAttachmentId)): echo 'disabled'; endif; ?>
                        >
                            <span aria-hidden="true">✕</span>
                            Excluir Anexo
                        </button>
                    </div>

                    <div wire:loading wire:target="emailExtraUpload" class="erp-orc-email-modal__hint">
                        Carregando anexo...
                    </div>
                </div>
            </div>

            <div class="erp-lookup-modal__actions erp-pcad-actions erp-orc-email-modal__actions">
                <button type="button" wire:click="sendOrcamentoEmail" class="erp-pcad-actions__btn" data-erp-key="F5">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                    <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Enviar</span>
                </button>
                <button type="button" wire:click="closeEmailModal" class="erp-pcad-actions__btn" data-erp-key="Escape">
                    <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                    <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
                </button>
            </div>
        </div>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/orcamentos/email-modal.blade.php ENDPATH**/ ?>