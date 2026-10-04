@php
    use App\Support\Erp\ErpMenu;

    $shortcuts = ErpMenu::shortcuts();
    $activeShortcut = ErpMenu::activeShortcutKey($shortcuts);
@endphp

<div class="erp-shortcut-bar" aria-label="Atalhos rápidos">
    <div class="erp-shortcut-bar__scroll">
        @foreach ($shortcuts as $shortcut)
            @php
                $isActive = ($shortcut['key'] ?? null) === $activeShortcut;
                $shortcutClass = 'erp-shortcut erp-shortcut--'.$shortcut['color'].($isActive ? ' erp-shortcut--active' : '');
            @endphp
            @if ($shortcut['logout'] ?? false)
                <form method="POST" action="{{ filament()->getLogoutUrl() }}" class="erp-shortcut-bar__form">
                    @csrf
                    <button type="submit" class="{{ $shortcutClass }}" title="Alt+S">
                        @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                        <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                    </button>
                </form>
            @elseif (filled($shortcut['url'] ?? null) && ! ($shortcut['disabled'] ?? false))
                <a
                    href="{{ $shortcut['url'] }}"
                    wire:navigate
                    @class([$shortcutClass])
                    @if ($isActive) aria-current="page" @endif
                >
                    @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                    <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                </a>
            @else
                <button
                    type="button"
                    class="{{ $shortcutClass }} @if ($shortcut['disabled'] ?? false) erp-shortcut--disabled @endif"
                    @if ($shortcut['disabled'] ?? false) disabled @else data-erp-module="{{ $shortcut['label'] }}" @endif
                >
                    @include('filament.components.erp.shortcut-icon', ['shortcut' => $shortcut])
                    <span class="erp-shortcut__label">{{ $shortcut['label'] }}</span>
                </button>
            @endif
        @endforeach
    </div>
</div>
