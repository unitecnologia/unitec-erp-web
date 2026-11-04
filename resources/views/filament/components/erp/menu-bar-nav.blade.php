@php
    /** @var array<int, array{label: string, items: array<int, array<string, mixed>>}> $menus */
@endphp
<nav class="erp-menu-bar" aria-label="Menu principal" @if (! empty($fp)) data-erp-menu-fp="{{ $fp }}" @endif>
    <ul class="erp-menu-bar__list">
        @foreach ($menus as $menu)
            <li class="erp-menu-bar__item">
                <div class="erp-menu-bar__details">
                    <button type="button" class="erp-menu-bar__trigger">{{ $menu['label'] }}</button>
                    <div class="erp-menu-bar__dropdown">
                        @include('filament.components.erp.menu-bar-items', ['items' => $menu['items']])
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</nav>
