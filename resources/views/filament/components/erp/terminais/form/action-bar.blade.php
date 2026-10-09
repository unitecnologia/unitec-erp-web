<div class="erp-produtos-pcad__footer erp-terminais-pcad__footer">
    <div class="erp-pcad-actions erp-terminais-pcad__actions">
        @if ($this->activeTerminalTab === 'aparelhos')
            <button type="button" wire:click="autorizarAparelhoSelecionado" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F2" title="Autorizar aparelho selecionado">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F2</kbd> | Autorizar</span>
            </button>
            <button type="button" wire:click="excluirAparelhoSelecionado" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger" data-erp-key="F4" title="Excluir aparelho selecionado">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Excluir</span>
            </button>
            <button type="button" wire:click="$refresh" class="erp-pcad-actions__btn" data-erp-key="F5" title="Atualizar lista">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Atualizar</span>
            </button>
        @else
            <button type="button" wire:click="deleteTerminal" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger" data-erp-key="F4" title="Excluir terminal">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F4</kbd> | Excluir Terminal</span>
            </button>
            <button type="button" wire:click="saveTerminalForm" class="erp-pcad-actions__btn erp-pcad-actions__btn--primary" data-erp-key="F10" title="Salvar terminal">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F10</kbd> | Salvar</span>
            </button>
            <button type="button" wire:click="reloadTerminal" class="erp-pcad-actions__btn" data-erp-key="F5" title="Atualizar terminal e lista (descarta alterações não salvas)">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/><path d="M3 3v5h5"/></svg>
                </span>
                <span class="erp-pcad-actions__label"><kbd>F5</kbd> | Atualizar</span>
            </button>
        @endif
        <button type="button" wire:click="closeScreen" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger" data-erp-key="Escape" title="Sair">
            <span class="erp-pcad-actions__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </span>
            <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Sair</span>
        </button>
        @if ($this->activeTerminalTab === 'aparelhos')
            <button type="button" wire:click="abrirResetAparelhoSelecionado" class="erp-pcad-actions__btn erp-pcad-actions__btn--danger erp-terminais-pcad__btn--end" title="Autorizar reset da base local do aparelho selecionado (Força de Vendas)">
                <span class="erp-pcad-actions__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3"/><path d="M16 16l5 5M21 16l-5 5"/></svg>
                </span>
                <span class="erp-pcad-actions__label">Autorizar Reset da Base</span>
            </button>
        @endif
    </div>
</div>
