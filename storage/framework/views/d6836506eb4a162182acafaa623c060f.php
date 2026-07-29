<div class="erp-produtos-pcad__foto-wrap">
    <div
        class="erp-produtos-pcad__foto"
        title="Dois cliques para alterar a foto."
        role="button"
        tabindex="0"
        x-data="{}"
        x-on:dblclick="$refs.fotoFile.click()"
        x-on:keydown.enter.prevent="$refs.fotoFile.click()"
        wire:loading.class="erp-produtos-pcad__foto--loading"
        wire:target="productFotoUpload"
    >
        <input
            x-ref="fotoFile"
            type="file"
            wire:model.live="productFotoUpload"
            accept="image/jpeg,image/jpg,image/png,image/webp,image/gif"
            class="erp-produtos-pcad__foto-file"
            tabindex="-1"
        >
        <span
            class="erp-produtos-pcad__foto-loading"
            wire:loading
            wire:target="productFotoUpload"
        >Carregando foto…</span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->productFotoPreviewUrl): ?>
            <img
                src="<?php echo e($this->productFotoPreviewUrl); ?>"
                alt="Foto do produto"
                class="erp-produtos-pcad__foto-img"
                wire:loading.remove
                wire:target="productFotoUpload"
                wire:key="product-foto-<?php echo e(md5($this->productFotoPreviewUrl)); ?>"
            >
        <?php else: ?>
            <span
                class="erp-produtos-pcad__foto-hint"
                wire:loading.remove
                wire:target="productFotoUpload"
            >Dois cliques para alterar a foto.</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="erp-produtos-pcad__foto-actions">
        <button
            type="button"
            class="erp-pcad-form__btn"
            wire:click="clearProductPhoto"
            <?php if(! $this->productFotoPreviewUrl): echo 'disabled'; endif; ?>
        >Limpar Imagem</button>
    </div>
</div>
<?php /**PATH C:\Projetos\unitec-erp-web\resources\views/filament/components/erp/produtos/form/product-foto.blade.php ENDPATH**/ ?>