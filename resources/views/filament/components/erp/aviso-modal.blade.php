@php
    $tone = $tone ?? 'warning';
    $titleId = $titleId ?? 'erp-aviso-modal-title';
    $icon = $icon ?? '!';
    $lines = $lines ?? [];
    $hint = $hint ?? null;
    $primaryLabel = $primaryLabel ?? 'OK';
    $primaryAction = $primaryAction ?? null;
    $secondaryLabel = $secondaryLabel ?? null;
    $secondaryAction = $secondaryAction ?? null;
    $escapeAction = $escapeAction ?? null;
    $backdropAction = $backdropAction ?? $secondaryAction;
@endphp

@if ($open ?? false)
    <div
        @class(['erp-aviso-modal', 'erp-aviso-modal--' . $tone])
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        @if ($escapeAction) wire:keydown.escape="{{ $escapeAction }}" @endif
    >
        <div
            class="erp-aviso-modal__backdrop"
            @if ($backdropAction) wire:click="{{ $backdropAction }}" @endif
        ></div>

        <div class="erp-aviso-modal__box">
            <div class="erp-aviso-modal__icon" aria-hidden="true">{{ $icon }}</div>

            <h2 class="erp-aviso-modal__title" id="{{ $titleId }}">{{ $title ?? 'Aviso' }}</h2>

            <div class="erp-aviso-modal__body">
                @foreach ($lines as $line)
                    <p class="erp-aviso-modal__text">{!! $line !!}</p>
                @endforeach
            </div>

            <div class="erp-aviso-modal__actions">
                @if ($primaryAction)
                    <button
                        type="button"
                        wire:click="{{ $primaryAction }}"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--primary"
                    >{{ $primaryLabel }}</button>
                @endif

                @if ($secondaryAction && $secondaryLabel)
                    <button
                        type="button"
                        wire:click="{{ $secondaryAction }}"
                        class="erp-aviso-modal__btn erp-aviso-modal__btn--secondary"
                    >{{ $secondaryLabel }}</button>
                @endif
            </div>

            @if ($hint)
                <p class="erp-aviso-modal__hint">{{ $hint }}</p>
            @endif
        </div>
    </div>
@endif
