@foreach ($items as $item)
    @if (($item['type'] ?? null) === 'separator')
        <div class="erp-menu-bar__separator"></div>
    @elseif (! empty($item['items']))
        <div class="erp-menu-bar__submenu">
            <span class="erp-menu-bar__link erp-menu-bar__link--submenu" role="button" tabindex="0">{{ $item['label'] }}</span>
            <div class="erp-menu-bar__submenu-panel" role="menu">
                @include('filament.components.erp.menu-bar-items', ['items' => $item['items']])
            </div>
        </div>
    @elseif (filled($item['url'] ?? null))
        <a href="{{ $item['url'] }}" wire:navigate class="erp-menu-bar__link">{{ $item['label'] }}</a>
    @elseif (filled($item['action'] ?? null))
        <button type="button" class="erp-menu-bar__link" data-erp-action="{{ $item['action'] }}">
            {{ $item['label'] }}
            @if (filled($item['shortcut'] ?? null))
                <kbd class="erp-kbd">{{ $item['shortcut'] }}</kbd>
            @endif
        </button>
    @elseif (! empty($item['pending']))
        <span
            class="erp-menu-bar__link erp-menu-bar__link--pending"
            title="Em breve"
            aria-disabled="true"
        >
            <span>{{ $item['label'] }}</span>
            <span class="erp-menu-bar__badge">Em breve</span>
            @if (filled($item['shortcut'] ?? null))
                <kbd class="erp-kbd">{{ $item['shortcut'] }}</kbd>
            @endif
        </span>
    @else
        <span class="erp-menu-bar__link erp-menu-bar__link--pending" title="Em breve" aria-disabled="true">
            <span>{{ $item['label'] }}</span>
            <span class="erp-menu-bar__badge">Em breve</span>
        </span>
    @endif
@endforeach
