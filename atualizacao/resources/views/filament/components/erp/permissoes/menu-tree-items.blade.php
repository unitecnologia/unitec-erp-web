@foreach ($items as $item)
    @if (($item['type'] ?? null) !== 'separator')
        @php
            $children = $item['items'] ?? null;
            $permission = $item['permission'] ?? null;
            $pending = (bool) ($item['pending'] ?? false);
            $disabled = ($isAdminUser && ! $isProfile) || $isSystemProfile;
            $isAlwaysAvailable = ! $pending && $permission === null && (
                filled($item['url'] ?? null) || filled($item['action'] ?? null)
            );
        @endphp

        @if (is_array($children))
            <div class="erp-permissoes__menu-branch" style="--menu-level: {{ $level }}">
                <strong>{{ $item['label'] }}</strong>
                @include('filament.components.erp.permissoes.menu-tree-items', [
                    'items' => $children,
                    'level' => $level + 1,
                    'isAdminUser' => $isAdminUser,
                    'isProfile' => $isProfile,
                    'isAdministratorProfile' => $isAdministratorProfile,
                    'isSystemProfile' => $isSystemProfile,
                ])
            </div>
        @elseif (! $pending && $permission !== null)
            <label class="erp-permissoes__menu-item" style="--menu-level: {{ $level }}">
                <input
                    type="checkbox"
                    @checked($isAdministratorProfile || $this->menuItemAllowed($permission))
                    wire:change="setMenuItemAllowed('{{ $permission }}', $event.target.checked)"
                    @disabled($disabled)
                >
                <span>{{ $item['label'] }}</span>
            </label>
        @elseif ($isAlwaysAvailable)
            <label class="erp-permissoes__menu-item erp-permissoes__menu-item--fixed" style="--menu-level: {{ $level }}">
                <input type="checkbox" checked disabled>
                <span>{{ $item['label'] }} <em>sempre disponível</em></span>
            </label>
        @endif
    @endif
@endforeach
