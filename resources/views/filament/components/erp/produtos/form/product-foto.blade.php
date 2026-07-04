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
    @if ($this->productFotoPreviewUrl)
        <img
            src="{{ $this->productFotoPreviewUrl }}"
            alt="Foto do produto"
            class="erp-produtos-pcad__foto-img"
            wire:loading.remove
            wire:target="productFotoUpload"
            wire:key="product-foto-{{ md5($this->productFotoPreviewUrl) }}"
        >
    @else
        <span
            class="erp-produtos-pcad__foto-hint"
            wire:loading.remove
            wire:target="productFotoUpload"
        >Dois cliques para alterar a foto.</span>
    @endif
</div>
