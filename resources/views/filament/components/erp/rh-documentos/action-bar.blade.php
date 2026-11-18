<div class="erp-rh-documentos-actions">
    <button type="button" wire:click="createDocumento" class="erp-rh-documentos-actions__btn" data-erp-key="F2">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--new">+</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button type="button" wire:click="editDocumento" class="erp-rh-documentos-actions__btn" data-erp-key="F3">
        <span class="erp-rh-documentos-actions__icon">✎</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button type="button" wire:click="deleteDocumento" class="erp-rh-documentos-actions__btn" data-erp-key="Delete">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--cancel">✕</span>
        <span class="erp-rh-documentos-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="abrirAnexoSelecionado" class="erp-rh-documentos-actions__btn" title="Abrir arquivo do documento">
        <span class="erp-rh-documentos-actions__icon">↗</span>
        <span class="erp-rh-documentos-actions__label">Abrir</span>
    </button>
    <button type="button" wire:click="imprimirDocumentos" class="erp-rh-documentos-actions__btn" data-erp-key="F4">
        <span class="erp-rh-documentos-actions__icon">🖨</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F4</kbd> | Imprimir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-rh-documentos-actions__btn" data-erp-key="F5">
        <span class="erp-rh-documentos-actions__icon">↻</span>
        <span class="erp-rh-documentos-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-rh-documentos-actions__btn erp-rh-documentos-actions__btn--close">
        <span class="erp-rh-documentos-actions__icon erp-rh-documentos-actions__icon--close">✕</span>
        <span class="erp-rh-documentos-actions__label">Fechar</span>
    </button>
</div>

@once
    <script>
        document.addEventListener('livewire:init', () => {
            if (window.__erpRhDocOpenBound) return;
            window.__erpRhDocOpenBound = true;
            Livewire.on('erp-open-url', (payload) => {
                const url = payload?.url ?? payload?.[0]?.url ?? null;
                if (!url) return;
                window.open(url, '_blank', 'noopener');
            });
        });
    </script>
@endonce
