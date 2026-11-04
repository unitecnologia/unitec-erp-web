@foreach ($items as $item)
    @php
        $hasIcon = filled($item['icon'] ?? null);
        $iconClass = $hasIcon ? ' erp-menu-bar__link--with-icon' : '';
        $useLightIcons = \App\Support\Erp\ErpMenu::SHELL_MENU_FASE2;
    @endphp
    @if (($item['type'] ?? null) === 'separator')
        <div class="erp-menu-bar__separator"></div>
    @elseif (! empty($item['items']))
        <div class="erp-menu-bar__submenu">
            <span class="erp-menu-bar__link erp-menu-bar__link--submenu{{ $iconClass }}" role="button" tabindex="0">
                @if ($hasIcon)
                    @if ($useLightIcons)
                        {!! \App\Support\Erp\ErpMenuIcons::svg((string) $item['icon'], $item['icon_color'] ?? null) !!}
                    @else
                        <x-filament::icon :icon="$item['icon']" @class(['erp-menu-bar__icon', $item['icon_color'] ?? null]) />
                    @endif
                @endif
                <span class="erp-menu-bar__label">{{ $item['label'] }}</span>
            </span>
            <div class="erp-menu-bar__submenu-panel" role="menu">
                @include('filament.components.erp.menu-bar-items', ['items' => $item['items']])
            </div>
        </div>
    @elseif (filled($item['url'] ?? null))
        <a href="{{ $item['url'] }}" wire:navigate class="erp-menu-bar__link{{ $iconClass }}">
            @if ($hasIcon)
                @if ($useLightIcons)
                    {!! \App\Support\Erp\ErpMenuIcons::svg((string) $item['icon'], $item['icon_color'] ?? null) !!}
                @else
                    <x-filament::icon :icon="$item['icon']" @class(['erp-menu-bar__icon', $item['icon_color'] ?? null]) />
                @endif
            @endif
            {{ $item['label'] }}
        </a>
    @elseif (filled($item['action'] ?? null))
        <button type="button" class="erp-menu-bar__link{{ $iconClass }}" data-erp-action="{{ $item['action'] }}">
            @if ($hasIcon)
                @if ($useLightIcons)
                    {!! \App\Support\Erp\ErpMenuIcons::svg((string) $item['icon'], $item['icon_color'] ?? null) !!}
                @else
                    <x-filament::icon :icon="$item['icon']" @class(['erp-menu-bar__icon', $item['icon_color'] ?? null]) />
                @endif
            @endif
            <span class="erp-menu-bar__label">{{ $item['label'] }}</span>
            @if (filled($item['shortcut'] ?? null))
                <kbd class="erp-kbd">{{ $item['shortcut'] }}</kbd>
            @endif
        </button>
    @elseif (! empty($item['pending']))
        <span
            class="erp-menu-bar__link erp-menu-bar__link--pending{{ $iconClass }}"
            title="Em breve"
            aria-disabled="true"
        >
            @if ($hasIcon)
                @if ($useLightIcons)
                    {!! \App\Support\Erp\ErpMenuIcons::svg((string) $item['icon'], $item['icon_color'] ?? 'erp-menu-bar__icon--slate') !!}
                @else
                    <x-filament::icon :icon="$item['icon']" @class(['erp-menu-bar__icon', $item['icon_color'] ?? 'erp-menu-bar__icon--slate']) />
                @endif
            @endif
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
