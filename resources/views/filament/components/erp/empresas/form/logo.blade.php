<div class="erp-empresas-logo">
    <div class="erp-empresas-logo__title">Logomarca</div>
    <div class="erp-empresas-logo__preview-wrap">
        @if ($this->logoPreviewUrl)
            <img
                src="{{ $this->logoPreviewUrl }}"
                alt="Logomarca da empresa"
                class="erp-empresas-logo__preview"
                wire:key="logo-preview-{{ md5($this->logoPreviewUrl) }}"
            >
        @else
            <div class="erp-empresas-logo__preview erp-empresas-logo__preview--empty"></div>
        @endif
    </div>
    <label class="erp-pcad-form__btn erp-empresas-logo__upload">
        <input type="file" wire:model="logoUpload" accept="image/*" class="erp-empresas-logo__file">
        Carregar imagem
    </label>
    <button type="button" wire:click="clearEmpresaLogo" class="erp-pcad-form__btn erp-empresas-logo__clear">Limpar Logomarca</button>
</div>
