@php
    use App\Support\Erp\ErpMenu;

    $shortcuts = ErpMenu::shortcuts();
@endphp

<div class="erp-shortcut-bar" aria-label="Atalhos rápidos">
    <div class="erp-shortcut-bar__scroll">
        @foreach ($shortcuts as $shortcut)
            @if ($shortcut['logout'] ?? false)
                <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="erp-shortcut-bar__form">
                    @csrf
                    <button type="submit" class="erp-shortcut erp-shortcut--{{ $shortcut['color'] }}" title="Alt+S">
                        @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                        <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                    </button>
                </form>
            @elseif (filled($shortcut['url'] ?? null) && ! ($shortcut['disabled'] ?? false))
                <a href="{{ $shortcut['url'] }}" wire:navigate="false" class="erp-shortcut erp-shortcut--{{ $shortcut['color'] }}">
                    @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                    <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                </a>
            @else
                <button
                    type="button"
                    class="erp-shortcut erp-shortcut--{{ $shortcut['color'] }} @if ($shortcut['disabled'] ?? false) erp-shortcut--disabled @endif"
                    @if ($shortcut['disabled'] ?? false) disabled @else data-erp-module="{{ $shortcut['label'] }}" @endif
                >
                    @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                    <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                </button>
            @endif
        @endforeach
    </div>
</div>
