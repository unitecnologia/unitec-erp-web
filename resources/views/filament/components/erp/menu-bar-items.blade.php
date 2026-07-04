@foreach ($items as $item)
    @if (($item['type'] ?? null) === 'separator')
        <div class="erp-menu-bar__separator"></div>
    @elseif (! empty($item['items']))
        <details class="erp-menu-bar__submenu">
            <summary class="erp-menu-bar__link erp-menu-bar__link--submenu">{{ $item['label'] }}</summary>
            <div class="erp-menu-bar__submenu-panel">
                @include('filament.components.erp.menu-bar-items', ['items' => $item['items']])
            </div>
        </details>
    @elseif (filled($item['url'] ?? null))
        <a href="{{ $item['url'] }}" wire:navigate="false" class="erp-menu-bar__link">{{ $item['label'] }}</a>
    @elseif (filled($item['action'] ?? null))
        <button type="button" class="erp-menu-bar__link" data-erp-action="{{ $item['action'] }}">
            {{ $item['label'] }}
            @if (filled($item['shortcut'] ?? null))
                <kbd class="erp-kbd">{{ $item['shortcut'] }}</kbd>
            @endif
        </button>
    @else
        <button type="button" class="erp-menu-bar__link" data-erp-module="{{ $item['label'] }}">
            {{ $item['label'] }}
            @if (filled($item['shortcut'] ?? null))
                <kbd class="erp-kbd">{{ $item['shortcut'] }}</kbd>
            @endif
        </button>
    @endif
@endforeach
