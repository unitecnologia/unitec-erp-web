<div class="erp-os-panel erp-os-panel--fill erp-os-panel--defeito">
    <h3 class="erp-os-panel__title">Defeito / Problema</h3>
    <label class="erp-os-form-label" for="os-problema">Descreva o problema relatado</label>
    <textarea
        id="os-problema"
        wire:model="problema"
        @disabled($readOnly)
        class="erp-os-form-textarea erp-os-form-textarea--defeito"
        placeholder="Informe o defeito ou problema apresentado..."
    ></textarea>

    <div class="erp-os-fotos" x-data="{ preview: null }">
        <div class="erp-os-fotos__head">
            <span class="erp-os-form-label erp-os-fotos__label">Fotos do equipamento / defeito</span>
            @if (! $readOnly)
                <label class="erp-os-fotos__add">
                    <input
                        type="file"
                        accept="image/*"
                        wire:model="osFotoUpload"
                        class="erp-os-fotos__file"
                    >
                    <span class="erp-os-fotos__add-btn" wire:loading.attr="disabled" wire:target="osFotoUpload">
                        <span wire:loading.remove wire:target="osFotoUpload">Incluir foto do computador</span>
                        <span wire:loading wire:target="osFotoUpload">Enviando…</span>
                    </span>
                </label>
            @endif
        </div>
        <p class="erp-os-fotos__hint">
            Mesmas fotos da OS no app e na impressão. Após gravar a OS, você pode anexar imagens aqui ou pelo Unitec OS.
        </p>

        @if ($this->osFotos === [])
            <div class="erp-os-fotos__empty">Nenhuma foto anexada</div>
        @else
            <div class="erp-os-fotos__gallery">
                @foreach ($this->osFotos as $foto)
                    <div class="erp-os-fotos__item">
                        <button
                            type="button"
                            class="erp-os-fotos__thumb"
                            @click="preview = @js($foto['url'])"
                            title="Clique para ampliar"
                        >
                            <img src="{{ $foto['url'] }}" alt="Foto da OS" loading="lazy">
                        </button>
                        <span class="erp-os-fotos__meta">{{ $foto['em'] }}</span>
                        @if (! $readOnly)
                            <button
                                type="button"
                                class="erp-os-fotos__remove"
                                wire:click="excluirOsFoto({{ (int) $foto['id'] }})"
                                wire:confirm="Remover esta foto?"
                                title="Remover foto"
                                aria-label="Remover foto"
                            >&times;</button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div
            class="erp-os-fotos__lightbox"
            x-show="preview"
            x-cloak
            x-transition.opacity
            @click="preview = null"
            @keydown.escape.window="if (preview) { preview = null; $event.stopImmediatePropagation(); }"
            role="dialog"
            aria-modal="true"
            aria-label="Foto ampliada"
        >
            <img :src="preview" alt="Foto ampliada" @click.stop>
            <button
                type="button"
                class="erp-os-fotos__lightbox-close"
                @click="preview = null"
                title="Fechar"
                aria-label="Fechar"
            >&times;</button>
        </div>
    </div>
</div>
