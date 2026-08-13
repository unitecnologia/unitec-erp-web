<div class="erp-logistica-actions">
    <button type="button" wire:click="refreshTable" class="erp-logistica-actions__btn" data-erp-key="F5">
        <span class="erp-logistica-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="abrirDetalheSelecionado" class="erp-logistica-actions__btn" data-erp-key="F3">
        <span class="erp-logistica-actions__label"><kbd>F3</kbd> | Detalhe / Itens</span>
    </button>
    @if (in_array($this->modo, ['carregamento', 'historico'], true))
        <button type="button" wire:click="avancarEntregaSelecionada" class="erp-logistica-actions__btn">
            <span class="erp-logistica-actions__label">Avançar status</span>
        </button>
    @endif
    <button type="button" wire:click="closeScreen" class="erp-logistica-actions__btn erp-logistica-actions__btn--close">
        <span class="erp-logistica-actions__label">Fechar</span>
    </button>
</div>
