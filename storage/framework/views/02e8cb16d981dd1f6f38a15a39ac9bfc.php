<div
    class="erp-balanca"
    wire:ignore.self
    x-on:keydown.escape.window="
        if ($wire.showEtiquetas) { return; }
        if ($wire.running) { $event.preventDefault(); return; }
        $event.preventDefault();
        $wire.handleEscape();
    "
>
    <header class="erp-balanca__titlebar">
        <span class="erp-balanca__title">Configurações de Balança</span>
        <button
            type="button"
            class="erp-balanca__close"
            wire:click="handleEscape"
            title="ESC | Fechar"
            aria-label="Fechar"
            <?php if($this->running): echo 'disabled'; endif; ?>
        >&times;</button>
    </header>

    <div class="erp-balanca__body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->feedbackMsg)): ?>
            <div
                class="erp-balanca__feedback erp-balanca__feedback--<?php echo e($this->feedbackTipo); ?>"
                role="status"
                wire:key="balanca-feedback-<?php echo e(md5($this->feedbackMsg.$this->feedbackTipo)); ?>"
            >
                <p class="erp-balanca__feedback-text"><?php echo e($this->feedbackMsg); ?></p>
                <button type="button" class="erp-balanca__feedback-close" wire:click="dismissFeedback" aria-label="Fechar mensagem">&times;</button>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="erp-balanca__config">
            <div class="erp-balanca__field erp-balanca__field--modelo">
                <label class="erp-balanca__label" for="balanca-modelo">Modelo</label>
                <div class="erp-balanca__modelo-row">
                    <select
                        id="balanca-modelo"
                        class="erp-balanca__select"
                        wire:model.live="modelo"
                        <?php if($this->running): echo 'disabled'; endif; ?>
                    >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->modeloOptions(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($value); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>

                    <label
                        class="erp-balanca__flag <?php echo e($this->isModeloPadrao() ? 'is-active' : ''); ?>"
                        title="<?php echo e($this->isModeloPadrao() ? 'Este modelo já é o padrão' : 'Definir este modelo como padrão'); ?>"
                    >
                        <input
                            type="checkbox"
                            wire:model.live="usarComoPadrao"
                            <?php if($this->running): echo 'disabled'; endif; ?>
                        >
                        <span class="erp-balanca__flag-text">Padrão</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->isModeloPadrao()): ?>
                            <span class="erp-balanca__badge" aria-hidden="true">★</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </label>
                </div>
            </div>

            <div class="erp-balanca__field erp-balanca__field--dir">
                <label class="erp-balanca__label" for="balanca-dir">Diretório dos arquivos</label>
                <div class="erp-balanca__path-row">
                    <input
                        id="balanca-dir"
                        type="text"
                        class="erp-balanca__input"
                        wire:model="diretorio"
                        wire:blur="salvarDiretorio"
                        spellcheck="false"
                        placeholder="C:\UNITECNOLOGIA_WEB\balanca"
                        <?php if($this->running): echo 'disabled'; endif; ?>
                    >
                    <button
                        type="button"
                        class="erp-balanca__browse"
                        wire:click="selecionarPasta"
                        wire:loading.attr="disabled"
                        wire:target="selecionarPasta"
                        title="Escolher pasta"
                        <?php if($this->running): echo 'disabled'; endif; ?>
                    >
                        …
                    </button>
                </div>
            </div>
        </div>

        <p class="erp-balanca__note">
            Só entram produtos com código balança (Prefixo) preenchido no cadastro.
        </p>

        <div class="erp-balanca__status-block">
            <span class="erp-balanca__label">Status</span>
            <div class="erp-balanca__status" role="status" aria-live="polite">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->running): ?>
                    Gerando arquivo…
                <?php elseif(filled($this->status)): ?>
                    <?php echo e($this->status); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($this->arquivos) > 0): ?>
            <ul class="erp-balanca__files">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $this->arquivos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li>
                        <strong><?php echo e($file['name']); ?></strong>
                        <span><?php echo e(number_format($file['bytes'] / 1024, 1, ',', '.')); ?> KB</span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </ul>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <footer class="erp-balanca__footer erp-pcad-actions erp-pcad-actions--split">
        <div class="erp-balanca__footer-left">
            <button
                type="button"
                class="erp-pcad-actions__btn erp-pcad-actions__btn--primary"
                data-erp-key="F5"
                wire:click="gerarArquivo"
                wire:loading.attr="disabled"
                wire:target="gerarArquivo"
                <?php if($this->running || $this->showEtiquetas): echo 'disabled'; endif; ?>
            >
                <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                <span class="erp-pcad-actions__label" wire:loading.remove wire:target="gerarArquivo">Gerar Arquivo</span>
                <span class="erp-pcad-actions__label" wire:loading wire:target="gerarArquivo">Gerando…</span>
            </button>

            <button
                type="button"
                class="erp-pcad-actions__btn erp-balanca__etiquetas-btn"
                wire:click="openEtiquetas"
                <?php if($this->running): echo 'disabled'; endif; ?>
                title="Configurar layout de etiquetas / código de barras"
            >
                <span class="erp-pcad-actions__icon">▦</span>
                <span class="erp-pcad-actions__label">Config. Etiquetas</span>
            </button>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($this->downloadPath)): ?>
                <button
                    type="button"
                    class="erp-pcad-actions__btn erp-balanca__download-btn"
                    wire:click="downloadArquivo"
                    <?php if($this->running): echo 'disabled'; endif; ?>
                    title="Baixar o arquivo gerado"
                >
                    <span class="erp-pcad-actions__icon">↓</span>
                    <span class="erp-pcad-actions__label">Download</span>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <button
            type="button"
            class="erp-pcad-actions__btn erp-pcad-actions__btn--danger"
            data-erp-key="Escape"
            wire:click="closeScreen"
            <?php if($this->running): echo 'disabled'; endif; ?>
        >
            <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Fechar</span>
        </button>
    </footer>

    <?php echo $__env->make('filament.components.erp.balanca.etiquetas-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/balanca/screen.blade.php ENDPATH**/ ?>