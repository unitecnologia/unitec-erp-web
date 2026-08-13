@php
    use App\Support\Erp\ErpMenu;

    $menus = ErpMenu::mainMenus();
@endphp

<nav class="erp-menu-bar" aria-label="Menu principal">
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
