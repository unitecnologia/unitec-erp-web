@php
    use App\Support\Erp\ErpContext;
    use App\Support\Erp\ErpPermissionCatalog;

    $isProfile = $this->sidebarTab === 'perfis';
    $catalog = ErpPermissionCatalog::editorTree(ErpContext::currentEmpresa());
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
                <input type="search" data-perm-search class="erp-permissoes__search" placeholder="Pesquisar..." autocomplete="off">
                <div class="erp-permissoes__list">
                    @if ($this->sidebarTab === 'usuarios')
                        @forelse ($this->userRows as $user)
                            <button
                                type="button"
                                wire:click="selectUser({{ $user['id'] }})"
                                data-perm-row="{{ mb_strtolower($user['name'], 'UTF-8') }}"
                                @class(['erp-permissoes__list-row', 'is-selected' => $this->selectedUserId === $user['id']])
                            >
                                <strong>{{ $user['name'] }}</strong>
                                <small>{{ $user['profile'] }} · {{ $user['ativo'] ? 'Ativo' : 'Inativo' }}</small>
                            </button>
                        @empty
                            <p class="erp-permissoes__empty">Nenhum usuário encontrado.</p>
                        @endforelse
                    @else
                        @forelse ($this->profileRows as $profile)
                            <button
                                type="button"
                                wire:click="selectProfile({{ $profile['id'] }})"
                                data-perm-row="{{ mb_strtolower($profile['nome'], 'UTF-8') }}"
                                @class(['erp-permissoes__list-row', 'is-selected' => $this->selectedProfileId === $profile['id']])
                            >
                                <strong>{{ $profile['nome'] }}</strong>
                                <small>{{ $profile['descricao'] }}</small>
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
                        <h1>{{ $this->contextTitle }}</h1>
                    </div>
                    @if (! $isProfile && $this->selectedUserId)
                        <label class="erp-permissoes__template">
                            <span>Aplicar perfil</span>
                            <select wire:model="profileTemplateId">
                                <option value="">— Sem perfil —</option>
                                @foreach ($this->profileRows as $profile)
                                    <option value="{{ $profile['id'] }}">{{ $profile['nome'] }}</option>
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
                            @if ($this->permissionLocked)
                                <p class="erp-permissoes__notice">Perfil do sistema: somente consulta.</p>
                            @else
                                <p class="erp-permissoes__hint">Crie ou ajuste o perfil aqui e configure os acessos na aba Permissões.</p>
                            @endif
                        @else
                            @include('filament.components.erp.permissoes.user-form')
                        @endif
                    </div>
                @elseif ($this->activeTab === 'empresas' && ! $isProfile)
                    @include('filament.components.erp.permissoes.empresas-transfer')
                @elseif ($this->activeTab === 'caixas' && ! $isProfile)
                    @include('filament.components.erp.permissoes.caixas-transfer')
                @endif

                <div @class(['erp-permissoes__perm-host', 'is-hidden' => $this->activeTab !== 'permissoes'])>
                    @if ($this->permissionFullAccess && ! $isProfile)
                        <p class="erp-permissoes__notice">Usuário administrador possui acesso total ao sistema.</p>
                    @endif
                    <div wire:ignore id="erp-perm-root" class="erp-permissoes__tree">
                        <script type="application/json" id="erp-perm-catalog">@json($catalog)</script>
                        <script type="application/json" id="erp-perm-boot">@json($this->permissionBoot())</script>
                        <div id="erp-perm-groups"></div>
                    </div>
                </div>

                <footer class="erp-permissoes__actions">
                    @if ($isProfile && $this->selectedProfileId && ! $this->permissionLocked)
                        <button type="button" wire:click="deleteProfile" class="is-danger">Excluir perfil</button>
                    @endif
                    @if ($this->activeTab === 'empresas' && ! $isProfile)
                        <button
                            type="button"
                            wire:click="saveUserEmpresas"
                            class="is-primary"
                            @disabled(! $this->selectedUserId)
                        >Salvar empresas</button>
                    @elseif ($this->activeTab === 'caixas' && ! $isProfile)
                        <button
                            type="button"
                            wire:click="saveUserCaixas"
                            class="is-primary"
                            @disabled(! $this->selectedUserId || ! $this->caixaEmpresaId)
                        >Salvar caixas</button>
                    @elseif ($this->activeTab !== 'cadastro')
                        <button
                            type="button"
                            data-perm-save
                            class="is-primary"
                            @disabled($this->permissionLocked || $this->permissionFullAccess)
                        >Salvar</button>
                    @endif
                    <button type="button" wire:click="closeScreen">Fechar</button>
                </footer>
            </section>
        </div>
</div>
