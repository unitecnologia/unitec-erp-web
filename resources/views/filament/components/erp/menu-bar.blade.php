@php
    use App\Support\Erp\ErpMenu;

    $menus = ErpMenu::mainMenus();
@endphp

<nav class="erp-menu-bar" aria-label="Menu principal">
    <ul class="erp-menu-bar__list">
        @foreach ($menus as $menu)
            <li class="erp-menu-bar__item">
                <details class="erp-menu-bar__details">
                    <summary class="erp-menu-bar__trigger">{{ $menu['label'] }}</summary>
                    <div class="erp-menu-bar__dropdown">
                        @include('filament.components.erp.menu-bar-items', ['items' => $menu['items']])
                    </div>
                </details>
            </li>
        @endforeach
    </ul>
</nav>
