@php
    $somenteLeituraAceitas = $this->statusFilter === 'aceita';
@endphp

<div class="erp-nfe-actions erp-nf-forn-actions">
    <button
        type="button"
        wire:click="openConsultaChaveModal"
        class="erp-nfe-actions__btn"
        data-erp-key="F2"
        @disabled($somenteLeituraAceitas)
        title="{{ $somenteLeituraAceitas ? 'Indisponível na aba Aceitas' : '' }}"
    >
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="7"/>
                <path d="M21 21l-4.3-4.3"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F2</kbd> | Consulta Chave</span>
    </button>
    <button
        type="button"
        wire:click="consultarLote"
        class="erp-nfe-actions__btn"
        data-erp-key="F3"
        @disabled($somenteLeituraAceitas)
        title="{{ $somenteLeituraAceitas ? 'Indisponível na aba Aceitas' : '' }}"
    >
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M3 6h13"/>
                <path d="M3 12h13"/>
                <path d="M3 18h9"/>
                <path d="M17.5 15.5l2 2 3.5-4"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F3</kbd> | Consulta Lote</span>
    </button>
    <button
        type="button"
        wire:click="confirmarNota"
        class="erp-nfe-actions__btn"
        data-erp-key="F4"
        @disabled($somenteLeituraAceitas)
        title="{{ $somenteLeituraAceitas ? 'Indisponível na aba Aceitas' : '' }}"
    >
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--new">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20 6L9 17l-5-5"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F4</kbd> | Confirmar</span>
    </button>
    <button
        type="button"
        wire:click="desconhecerNota"
        class="erp-nfe-actions__btn"
        data-erp-key="F5"
        @disabled($somenteLeituraAceitas)
        title="{{ $somenteLeituraAceitas ? 'Indisponível na aba Aceitas' : '' }}"
    >
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--cancel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6L6 18"/>
                <path d="M6 6l12 12"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F5</kbd> | Desconhecer</span>
    </button>
    <button
        type="button"
        wire:click="openLerXmlSelecionada"
        class="erp-nfe-actions__btn"
        data-erp-key="F6"
        title="Ler XML da nota selecionada ou buscar arquivo (e-mail / pasta)"
    >
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--xml">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/>
                <path d="M14 3v5h5"/>
                <path d="M9.5 12.5l2 2-2 2"/>
                <path d="M14.5 12.5l-2 2 2 2"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label"><kbd>F6</kbd> | Ler XML</span>
    </button>
    <button type="button" wire:click="openNotaFornecedorVisualizarSelecionada" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12z"/>
                <circle cx="12" cy="12" r="2.75"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Visualizar DANFE</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-nfe-actions__btn">
        <span class="erp-nfe-actions__icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.5 12a8.5 8.5 0 1 1-2.6-6.1"/>
                <path d="M20.5 4v5h-5"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Atualizar</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-nfe-actions__btn erp-nfe-actions__btn--close">
        <span class="erp-nfe-actions__icon erp-nfe-actions__icon--close">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 6L6 18"/>
                <path d="M6 6l12 12"/>
            </svg>
        </span>
        <span class="erp-nfe-actions__label">Fechar</span>
    </button>
</div>
