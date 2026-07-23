<header class="gestor-head">
    <div class="gestor-head__text">
        <p class="gestor-head__eyebrow">{{ $eyebrow ?? 'Unitec Executivo' }}</p>
        <h1 class="gestor-head__title">{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="gestor-head__meta">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="gestor-head__actions">
        @isset($notify_url)
            <a href="{{ $notify_url }}" class="gestor-icon-btn" title="Notificações / aprovações" aria-label="Notificações" wire:navigate>🔔</a>
        @endisset
        <button type="button" class="gestor-icon-btn" wire:click="toggleTema" title="Tema claro/escuro" aria-label="Alternar tema">
            <span class="gestor-icon-btn__glyph" data-theme-toggle></span>
        </button>
        @if (! empty($refresh))
            <button type="button" class="gestor-icon-btn" wire:click="{{ $refresh }}" title="Atualizar" aria-label="Atualizar">
                ↻
            </button>
        @endif
    </div>
</header>
