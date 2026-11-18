@php
    $pdvConsulta = $this->caixaContaPdvSomenteConsulta;
    $livroCaixa = $this->caixaF8PodeUsar;
@endphp
<div class="erp-caixa-actions" data-erp-caixa-actions>
    <button
        type="button"
        wire:click="createLancamento"
        class="erp-caixa-actions__btn"
        data-erp-key="F2"
        data-erp-caixa-operacional
        @if ($pdvConsulta) hidden @endif
        title="Novo lançamento de caixa"
    >
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--new">+</span>
        <span class="erp-caixa-actions__label"><kbd>F2</kbd> | Novo</span>
    </button>
    <button
        type="button"
        wire:click="editLancamento"
        class="erp-caixa-actions__btn"
        data-erp-key="F3"
        data-erp-caixa-operacional
        @if ($pdvConsulta) hidden @endif
        title="Alterar lançamento manual selecionado"
    >
        <span class="erp-caixa-actions__icon">✎</span>
        <span class="erp-caixa-actions__label"><kbd>F3</kbd> | Alterar</span>
    </button>
    <button
        type="button"
        wire:click="deleteLancamento"
        class="erp-caixa-actions__btn"
        data-erp-key="Delete"
        data-erp-caixa-operacional
        @if ($pdvConsulta) hidden @endif
    >
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--cancel">✕</span>
        <span class="erp-caixa-actions__label"><kbd>Del</kbd> | Excluir</span>
    </button>
    <button type="button" wire:click="refreshTable" class="erp-caixa-actions__btn" data-erp-key="F5">
        <span class="erp-caixa-actions__icon">↻</span>
        <span class="erp-caixa-actions__label"><kbd>F5</kbd> | Atualizar</span>
    </button>
    <button type="button" wire:click="imprimirMovimentoCaixa" class="erp-caixa-actions__btn" data-erp-key="F6" title="Imprimir movimento de caixa do período filtrado">
        <span class="erp-caixa-actions__icon">🖨</span>
        <span class="erp-caixa-actions__label"><kbd>F6</kbd> | Imprimir</span>
    </button>
    <button
        type="button"
        wire:click="openCaixaTransferencia"
        class="erp-caixa-actions__btn"
        data-erp-key="F7"
        data-erp-caixa-operacional
        @if ($pdvConsulta) hidden @endif
        title="Transferir valor entre contas financeiras"
    >
        <span class="erp-caixa-actions__icon">⇄</span>
        <span class="erp-caixa-actions__label"><kbd>F7</kbd> | Transferir</span>
    </button>
    <button
        type="button"
        wire:click="openCaixaF8"
        class="erp-caixa-actions__btn"
        data-erp-key="F8"
        data-erp-caixa-livro
        @unless ($livroCaixa) hidden @endunless
        title="{{ $livroCaixa && $this->caixaMovimentoAberto ? 'Fechar movimento do Livro Caixa' : 'Abrir movimento do Livro Caixa' }}"
    >
        <span class="erp-caixa-actions__icon" data-erp-caixa-livro-icon>{{ $livroCaixa && $this->caixaMovimentoAberto ? '▣' : '▢' }}</span>
        <span class="erp-caixa-actions__label"><kbd>F8</kbd> | <span data-erp-caixa-livro-label>{{ $livroCaixa ? $this->caixaF8Label : 'Abrir Caixa' }}</span></span>
    </button>
    <button
        type="button"
        wire:click="openCaixaFechamentoHistorico"
        class="erp-caixa-actions__btn"
        data-erp-caixa-livro
        @unless ($livroCaixa) hidden @endunless
        title="Reimprimir fechamentos anteriores desta conta"
    >
        <span class="erp-caixa-actions__icon">📋</span>
        <span class="erp-caixa-actions__label">Hist. Fech.</span>
    </button>
    <button type="button" wire:click="closeScreen" class="erp-caixa-actions__btn erp-caixa-actions__btn--close">
        <span class="erp-caixa-actions__icon erp-caixa-actions__icon--close">✕</span>
        <span class="erp-caixa-actions__label">Fechar</span>
    </button>
</div>
