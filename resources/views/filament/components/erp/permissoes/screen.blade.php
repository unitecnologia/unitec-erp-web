@php
    use App\Support\Erp\ErpPermissionCatalog;

    $groups = $this->permissionGroups();
    $selectedUser = $this->selectedUser();
    $isAdminUser = $selectedUser?->is_admin ?? false;
    $selectedProfile = $this->selectedProfileId
        ? \App\Models\ErpProfile::query()->find($this->selectedProfileId)
        : null;
    $isProfile = $this->sidebarTab === 'perfis';
    $isAdministratorProfile = $this->isAdministratorProfileSelected();
    $selectedName = $isProfile
        ? ($selectedProfile?->nome ?? 'Novo perfil')
        : ($selectedUser?->name ?? 'Selecione um usuário');
@endphp

<div class="erp-os-window erp-permissoes">
        <header class="erp-os-window__titlebar">
            <span>Usuários e permissões</span>
            <button type="button" class="erp-os-window__close" wire:click="closeScreen" title="Fechar" aria-label="Fechar">&times;</button>
        </header>

        <div class="erp-permissoes__body">
            <aside class="erp-permissoes__sidebar">
                <div class="erp-permissoes__sidebar-tabs">
                    <button type="button" wire:click="setSidebarTab('usuarios')" @class(['is-active' => $this->sidebarTab === 'usuarios'])>Usuários</button>
                    <button type="button" wire:click="setSidebarTab('perfis')" @class(['is-active' => $this->sidebarTab === 'perfis'])>Perfis</button>
                </div>
                <input type="search" wire:model.live.debounce.300ms="sidebarSearch" class="erp-permissoes__search" placeholder="Pesquisar...">
                <div class="erp-permissoes__list">
                    @if ($this->sidebarTab === 'usuarios')
                        @forelse ($this->sidebarUsers() as $user)
                            <button type="button" wire:click="selectUser({{ $user->id }})" @class(['erp-permissoes__list-row', 'is-selected' => $selectedUser?->id === $user->id])>
                                <strong>{{ $user->name }}</strong>
                                <small>{{ $user->erpProfile?->nome ?? 'Sem perfil' }} · {{ $user->ativo ? 'Ativo' : 'Inativo' }}</small>
                            </button>
                        @empty
                            <p class="erp-permissoes__empty">Nenhum usuário encontrado.</p>
                        @endforelse
                    @else
                        @forelse ($this->sidebarProfiles() as $profile)
                            <button type="button" wire:click="selectProfile({{ $profile->id }})" @class(['erp-permissoes__list-row', 'is-selected' => $selectedProfile?->id === $profile->id])>
                                <strong>{{ $profile->nome }}</strong>
                                <small>{{ $profile->descricao ?: ($profile->is_system ? 'Perfil do sistema' : 'Perfil personalizado') }}</small>
                            </button>
                        @empty
                            <p class="erp-permissoes__empty">Nenhum perfil encontrado.</p>
                        @endforelse
                    @endif
                </div>
                <div class="erp-permissoes__sidebar-actions">
                    @if ($this->sidebarTab === 'perfis')
                        <button type="button" wire:click="newProfile">Novo perfil</button>
                    @else
                        <button type="button" wire:click="newUser">Novo usuário</button>
                    @endif
                </div>
            </aside>

            <section class="erp-permissoes__workspace">
                <header class="erp-permissoes__context">
                    <div>
                        <span>{{ $isProfile ? 'Perfil selecionado' : 'Usuário selecionado' }}</span>
                        <h1>{{ $selectedName }}</h1>
                    </div>
                    @if (! $isProfile && $selectedUser)
                        <label class="erp-permissoes__template">
                            <span>Aplicar perfil</span>
                            <select wire:model="profileTemplateId">
                                <option value="">— Sem perfil —</option>
                                @foreach ($this->profileOptions() as $id => $nome)
                                    <option value="{{ $id }}">{{ $nome }}</option>
                                @endforeach
                            </select>
                            <button type="button" wire:click="loadProfileTemplate">Aplicar</button>
                        </label>
                    @endif
                </header>

                <div class="erp-permissoes__main-tabs">
                    <button type="button" wire:click="setActiveTab('cadastro')" @class(['is-active' => $this->activeTab === 'cadastro'])>Cadastro</button>
                    @if (! $isProfile)
                        <button type="button" wire:click="setActiveTab('empresas')" @class(['is-active' => $this->activeTab === 'empresas'])>Empresas</button>
                        <button type="button" wire:click="setActiveTab('caixas')" @class(['is-active' => $this->activeTab === 'caixas'])>Caixas</button>
                    @endif
                    <button type="button" wire:click="setActiveTab('permissoes')" @class(['is-active' => $this->activeTab === 'permissoes'])>Permissões</button>
                    <button type="button" wire:click="setActiveTab('menu')" @class(['is-active' => $this->activeTab === 'menu'])>Acesso ao menu</button>
                </div>

                @if ($this->activeTab === 'cadastro')
                    <div class="erp-permissoes__editor">
                        @if ($isProfile)
                            <label>
                                <span>Nome do perfil</span>
                                <input type="text" wire:model="profileNome" data-erp-uppercase placeholder="Ex.: CAIXA">
                            </label>
                            <label>
                                <span>Descrição</span>
                                <input type="text" wire:model="profileDescricao" placeholder="Descrição do perfil">
                            </label>
                            @if ($selectedProfile?->is_system)
                                <p class="erp-permissoes__notice">Perfil do sistema: somente consulta.</p>
                            @else
                                <p class="erp-permissoes__hint">Crie ou ajuste o perfil aqui e configure os acessos na aba Permissões.</p>
                            @endif
                        @elseif ($selectedUser)
                            @include('filament.components.erp.permissoes.user-form')
                        @else
                            @include('filament.components.erp.permissoes.user-form')
                        @endif
                    </div>
                @elseif ($this->activeTab === 'empresas' && ! $isProfile)
                    @include('filament.components.erp.permissoes.empresas-transfer')
                @elseif ($this->activeTab === 'caixas' && ! $isProfile)
                    @include('filament.components.erp.permissoes.caixas-transfer')
                @elseif ($this->activeTab === 'permissoes')
                    @if ($isAdminUser && ! $isProfile)
                        <p class="erp-permissoes__notice">Usuário administrador possui acesso total ao sistema.</p>
                    @endif
                    <div class="erp-permissoes__tree">
                        @foreach ($groups as $groupKey => $group)
                            <section class="erp-permissoes__tree-group">
                                <header>
                                    <button type="button" class="erp-permissoes__tree-toggle" wire:click="toggleGroup('{{ $groupKey }}')">
                                        {{ $this->isGroupExpanded($groupKey) ? '⌄' : '›' }} {{ $group['label'] }}
                                    </button>
                                    <span>
                                        <button type="button" wire:click="markGroup('{{ $groupKey }}', true)">Liberar tudo</button>
                                        <button type="button" wire:click="markGroup('{{ $groupKey }}', false)">Bloquear</button>
                                    </span>
                                </header>
                                @if ($this->isGroupExpanded($groupKey))
                                    @foreach ($group['modules'] as $module => $meta)
                                        <div class="erp-permissoes__tree-module">
                                            <div>
                                                <button type="button" class="erp-permissoes__tree-toggle" wire:click="toggleModule('{{ $module }}')">
                                                    {{ $this->isModuleExpanded($module) ? '⌄' : '›' }} {{ $meta['label'] }}
                                                </button>
                                                <span>
                                                    <button type="button" wire:click="markModule('{{ $module }}', true)">Liberar</button>
                                                    <button type="button" wire:click="markModule('{{ $module }}', false)">Bloquear</button>
                                                </span>
                                            </div>
                                            @if ($this->isModuleExpanded($module))
                                                <div class="erp-permissoes__tree-actions">
                                                    @foreach ($meta['actions'] as $action => $label)
                                                        @php $permKey = ErpPermissionCatalog::key($module, $action); @endphp
                                                        <label>
                                                            <input
                                                                type="checkbox"
                                                                @checked($isAdministratorProfile || (bool) ($this->checked[$permKey] ?? false))
                                                                wire:change="setPermissionAllowed('{{ $permKey }}', $event.target.checked)"
                                                                @disabled(($isAdminUser && ! $isProfile) || $selectedProfile?->is_system)
                                                            >
                                                            <span>{{ $label }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </section>
                        @endforeach
                    </div>
                @elseif ($this->activeTab === 'menu')
                    @include('filament.components.erp.permissoes.menu-tree')
                @endif

                <footer class="erp-permissoes__actions">
                    @if ($isProfile && $selectedProfile && ! $selectedProfile->is_system)
                        <button type="button" wire:click="deleteProfile" class="is-danger">Excluir perfil</button>
                    @endif
                    @if ($this->activeTab === 'empresas' && ! $isProfile)
                        <button
                            type="button"
                            wire:click="saveUserEmpresas"
                            class="is-primary"
                            @disabled(! $selectedUser)
                        >Salvar empresas</button>
                    @elseif ($this->activeTab === 'caixas' && ! $isProfile)
                        <button
                            type="button"
                            wire:click="saveUserCaixas"
                            class="is-primary"
                            @disabled(! $selectedUser || ! $this->caixaEmpresaId)
                        >Salvar caixas</button>
                    @elseif ($this->activeTab !== 'cadastro')
                        <button
                            type="button"
                            wire:click="savePermissions"
                            class="is-primary"
                            @disabled(($isAdminUser && ! $isProfile) || ($selectedProfile?->is_system ?? false))
                        >Salvar</button>
                    @endif
                    <button type="button" wire:click="closeScreen">Fechar</button>
                </footer>
            </section>
        </div>
</div>
