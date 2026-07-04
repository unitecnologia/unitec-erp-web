@if ($this->duplicateConfirmOpen)
    @php
        $duplicate = $this->duplicateConfirmViewState;
    @endphp

    @if ($duplicate !== [])
        <div
            class="erp-lookup-modal erp-duplicate-modal"
            wire:keydown.escape="handleDuplicateEscape"
        >
            <div class="erp-lookup-modal__backdrop" wire:click="cancelDuplicateConfirmModal"></div>

            <div class="erp-lookup-modal__window" role="dialog" aria-modal="true" aria-labelledby="erp-duplicate-title">
                <div class="erp-lookup-modal__titlebar">
                    <span id="erp-duplicate-title">Produto já cadastrado</span>
                    <button type="button" class="erp-lookup-modal__close" wire:click="cancelDuplicateConfirmModal" title="Fechar">✕</button>
                </div>

                <div class="erp-lookup-modal__body erp-duplicate-modal__body">
                    <p class="erp-duplicate-modal__reason">{{ $duplicate['matchLabel'] }}</p>

                    <dl class="erp-duplicate-modal__details">
                        <div class="erp-duplicate-modal__detail">
                            <dt>Código</dt>
                            <dd>{{ $duplicate['codigo'] }}</dd>
                        </div>
                        <div class="erp-duplicate-modal__detail">
                            <dt>Descrição</dt>
                            <dd>{{ $duplicate['descricao'] }}</dd>
                        </div>
                        @if (filled($duplicate['codigo_barras']))
                            <div class="erp-duplicate-modal__detail">
                                <dt>Código de barras</dt>
                                <dd>{{ $duplicate['codigo_barras'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    <p class="erp-duplicate-modal__question">Deseja editar o produto existente?</p>
                </div>

                <div class="erp-lookup-modal__actions erp-pcad-actions">
                    <button type="button" wire:click="confirmEditExistingProduct" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--save">✓</span>
                        <span class="erp-pcad-actions__label">Sim, editar</span>
                    </button>
                    <button type="button" wire:click="cancelDuplicateConfirmModal" class="erp-pcad-actions__btn">
                        <span class="erp-pcad-actions__icon erp-pcad-actions__icon--exit">✕</span>
                        <span class="erp-pcad-actions__label"><kbd>ESC</kbd> | Não</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
@endif
