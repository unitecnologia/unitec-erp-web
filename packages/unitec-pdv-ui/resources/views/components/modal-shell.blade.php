{{--
  Chrome moderno (padrão Fechar Caixa / Vendedor).
  Preservar IDs e classes --form/--small/--wide no $windowClass para o erp-pdv.js.
--}}
@props([
    'title' => '',
    'titleId' => null,
    'eyebrow' => null,
    'subtitle' => null,
    'ariaLabel' => null,
    'closeAction' => 'closePdvModal',
    'windowClass' => '',
])

@php
    $aria = $ariaLabel ?? $title;
@endphp

<div
    {{ $attributes->class(['erp-pdv-modal', 'erp-pdv-shell-modal']) }}
    role="dialog"
    aria-label="{{ $aria }}"
    @if ($titleId) aria-labelledby="{{ $titleId }}" @endif
    aria-modal="true"
>
    <div class="erp-pdv-modal__backdrop" wire:click="{{ $closeAction }}"></div>

    <div @class([
        'erp-pdv-modal__window',
        'erp-pdv-shell-modal__window',
        $windowClass,
    ])>
        <header class="erp-pdv-shell-modal__hero">
            @isset($icon)
                <div class="erp-pdv-shell-modal__hero-icon" aria-hidden="true">
                    {{ $icon }}
                </div>
            @endisset
            <div class="erp-pdv-shell-modal__hero-text">
                @if (filled($eyebrow))
                    <p class="erp-pdv-shell-modal__eyebrow">{{ $eyebrow }}</p>
                @endif
                <h2 @if ($titleId) id="{{ $titleId }}" @endif>{{ $title }}</h2>
                @if (filled($subtitle))
                    <p class="erp-pdv-shell-modal__subtitle">{{ $subtitle }}</p>
                @endif
            </div>
            <button
                type="button"
                class="erp-pdv-shell-modal__x"
                wire:click="{{ $closeAction }}"
                title="Fechar"
                aria-label="Fechar"
            >×</button>
        </header>

        <div class="erp-pdv-shell-modal__body erp-pdv-modal__body">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="erp-pdv-shell-modal__footer">
                {{ $footer }}
            </footer>
        @endisset
    </div>
</div>
