@php
    $podeAbrirEditar = count($this->selecionados) === 1;
@endphp
<div class="erp-nfe-actions erp-cargas-actions">
    <button type="button" wire:click="createCarga" class="erp-nfe-actions__btn" data-erp-key="F2" title="Nova carga">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 5v14M5 12h14"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Nova Carga</span>
    </button>
    <button
        type="button"
        wire:click="editCarga"
        class="erp-nfe-actions__btn"
        data-erp-key="F3"
        @disabled(! $podeAbrirEditar)
        x-data
        x-bind:disabled="! Array.isArray($wire.selecionados) || $wire.selecionados.length !== 1"
        title="{{ $podeAbrirEditar ? 'Abrir ou editar carga' : 'Selecione apenas uma carga' }}"
    >
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 20h9"/>
                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Abrir/Editar</span>
    </button>
    <button type="button" wire:click="fecharCargaSelecionada" class="erp-nfe-actions__btn" title="Fechar carga selecionada">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--ok">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 13l4 4L19 7"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Fechar Carga</span>
    </button>
    <button type="button" wire:click="cancelarCargaSelecionada" class="erp-nfe-actions__btn" title="Cancelar carga selecionada">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 6l12 12M18 6 6 18"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Cancelar</span>
    </button>
    <button type="button" wire:click="imprimirRomaneioSelecionado" class="erp-nfe-actions__btn" data-erp-key="F4" title="Imprimir romaneio da(s) carga(s) marcada(s)">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M6 9V3h12v6"/>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                <rect x="6" y="14" width="12" height="7" rx="1"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Imprimir Romaneio</span>
    </button>
    <button type="button" wire:click="imprimirPedidosSelecionados" class="erp-nfe-actions__btn" data-erp-key="F6" title="Imprimir pedidos da(s) carga(s) marcada(s)">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9 13h6M9 17h4"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Imprimir Pedido</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn" data-erp-key="F5" title="Atualizar lista">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 12a9 9 0 1 0 2.6-6.4L3 8"/>
                <path d="M3 3v5h5"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close" title="Fechar tela">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                <path d="M16 17l5-5-5-5"/>
                <path d="M21 12H9"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Fechar</span>
    </button>
</div>
