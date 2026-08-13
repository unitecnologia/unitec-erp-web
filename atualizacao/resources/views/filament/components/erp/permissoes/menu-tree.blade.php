<div class="erp-permissoes__menu-tree">
    <p class="erp-permissoes__hint">Controle quais itens reais do menu ficam disponíveis para este {{ $isProfile ? 'perfil' : 'usuário' }}. A permissão também bloqueia o acesso direto à tela.</p>

    @foreach ($this->menuAccessItems() as $menu)
        @php
            $menuKey = \Illuminate\Support\Str::slug((string) $menu['label']);
            $expanded = $this->isMenuGroupExpanded($menuKey);
        @endphp
        <section class="erp-permissoes__menu-section">
            <header>
                <button type="button" wire:click="toggleMenuGroup('{{ $menuKey }}')">
                    <span>{{ $expanded ? '⌄' : '›' }}</span> {{ $menu['label'] }}
                </button>
                <span class="erp-permissoes__menu-section-actions">
                    <button type="button" wire:click="markMenuGroupItems('{{ $menu['label'] }}', true)" @disabled(($isAdminUser && ! $isProfile) || ($selectedProfile?->is_system ?? false))>Marcar todos</button>
                    <button type="button" wire:click="markMenuGroupItems('{{ $menu['label'] }}', false)" @disabled(($isAdminUser && ! $isProfile) || ($selectedProfile?->is_system ?? false))>Desmarcar</button>
                </span>
            </header>
            @if ($expanded)
                <div class="erp-permissoes__menu-items">
                    @include('filament.components.erp.permissoes.menu-tree-items', [
                        'items' => $menu['items'] ?? [],
                        'level' => 0,
                        'isAdminUser' => $isAdminUser,
                        'isProfile' => $isProfile,
                    'isAdministratorProfile' => $isAdministratorProfile,
                        'isSystemProfile' => $selectedProfile?->is_system ?? false,
                    ])
                </div>
            @endif
        </section>
    @endforeach
</div>
