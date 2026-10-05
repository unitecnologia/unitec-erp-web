<nav class="gestor-nav" aria-label="Navegação principal">
    @foreach ($this->bottomNav() as $item)
        @if (! ($item['visible'] ?? true))
            @continue
        @endif
        <a
            href="{{ $item['url'] }}"
            class="gestor-nav__item {{ $item['active'] ? 'is-active' : '' }}"
            wire:navigate
        >
            <span class="gestor-nav__icon" data-icon="{{ $item['icon'] }}" aria-hidden="true"></span>
            <span class="gestor-nav__label">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
