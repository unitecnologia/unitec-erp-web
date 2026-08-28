<header class="gestor-head">
    <div class="gestor-head__text">
        <p class="gestor-head__eyebrow">{{ $eyebrow ?? 'Unitec Executivo' }}</p>
        <h1 class="gestor-head__title">{{ $title }}</h1>
        @php
            $empresasOpts = method_exists($this, 'empresasGestor') ? $this->empresasGestor() : [];
            $multiEmpresa = count($empresasOpts) > 1;
            $metaExtra = $subtitle ?? null;
            if ($multiEmpresa && is_string($metaExtra) && $metaExtra !== '') {
                $nomeEmpresa = method_exists($this, 'empresaNome') ? (string) $this->empresaNome() : '';
                if ($nomeEmpresa !== '') {
                    $metaExtra = trim((string) preg_replace('/^\s*'.preg_quote($nomeEmpresa, '/').'\s*[·•\-]\s*/u', '', $metaExtra));
                }
            }
        @endphp
        @if ($multiEmpresa)
            <label class="gestor-empresa">
                <span class="gestor-empresa__label">Empresa</span>
                <select
                    class="gestor-empresa__select"
                    wire:model.live="empresaGestorId"
                    aria-label="Trocar empresa"
                >
                    @foreach ($empresasOpts as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </label>
            @if (filled($metaExtra))
                <p class="gestor-head__meta">{{ $metaExtra }}</p>
            @endif
        @elseif (! empty($subtitle))
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
