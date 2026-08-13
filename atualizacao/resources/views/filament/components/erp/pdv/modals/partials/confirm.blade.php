@php
    $titleId = $titleId ?? 'erp-pdv-confirm-title';
    $confirmLabel = $confirmLabel ?? 'Sim';
    $cancelLabel = $cancelLabel ?? 'Não';
@endphp

<div class="erp-pdv-modal" role="dialog" aria-labelledby="{{ $titleId }}">
    <div class="erp-pdv-modal__backdrop" wire:click="{{ $cancelAction }}"></div>

    <div class="erp-pdv-modal__window erp-pdv-modal__window--small">
        <header class="erp-pdv-modal__header">
            <h2 id="{{ $titleId }}">{{ $title }}</h2>
        </header>

        <div class="erp-pdv-modal__body">
            <p class="erp-pdv-modal__confirm-text">{{ $message }}</p>
        </div>

        <footer class="erp-pdv-modal__footer">
            <button
                type="button"
                wire:click="{{ $confirmAction }}"
                class="erp-pdv-modal__btn erp-pdv-modal__btn--primary"
                @if (! empty($confirmId)) id="{{ $confirmId }}" @endif
            >{{ $confirmLabel }}</button>
            <button
                type="button"
                wire:click="{{ $cancelAction }}"
                class="erp-pdv-modal__btn"
                @if (! empty($cancelId)) id="{{ $cancelId }}" @endif
            >{{ $cancelLabel }}</button>
        </footer>
    </div>
</div>
