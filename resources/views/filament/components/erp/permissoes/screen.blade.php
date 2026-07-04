@php
    use App\Support\Erp\ErpPermissionCatalog;

    $groups = ErpPermissionCatalog::groupedForUi();
    $editingProfile = $this->editingProfile;
    $selectedProfileId = $this->selectedProfileId;
    $selectedUser = $this->selectedUserId
        ? \App\Models\User::query()->find($this->selectedUserId)
        : null;
    $isAdminUser = $selectedUser?->is_admin ?? false;
@endphp
<div class="erp-permissoes" wire:ignore.self>
    <div class="erp-permissoes__toolbar">
        <div class="erp-permissoes__mode">
            <label class="erp-permissoes__field">
                <span>Usuário</span>
                <select wire:model.live="selectedUserId" class="erp-permissoes__select" @disabled($editingProfile)>
                    <option value="">— Selecione —</option>
                    @foreach ($this->userOptions() as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </label>

            <label class="erp-permissoes__field">
                <span>Perfil (modelo)</span>
                <select wire:model.live="selectedProfileId" class="erp-permissoes__select">
                    <option value="">— Selecione —</option>
                    @foreach ($this->profileOptions() as $id => $nome)
                        <option value="{{ $id }}">{{ $nome }}</option>
                    @endforeach
                </select>
            </label>

            <button type="button" wire:click="loadProfileTemplate" class="erp-permissoes__btn" @disabled(! $selectedProfileId || $editingProfile)>
                Carregar perfil no usuário
            </button>
            <button type="button" wire:click="startEditProfile" class="erp-permissoes__btn erp-permissoes__btn--secondary">
                {{ $editingProfile ? 'Editando perfil' : 'Editar perfil' }}
            </button>
        </div>

        @if ($editingProfile)
            <div class="erp-permissoes__profile-form">
                <label class="erp-permissoes__field">
                    <span>Nome do perfil</span>
                    <input type="text" wire:model="profileNome" class="erp-permissoes__input" data-erp-uppercase>
                </label>
                <label class="erp-permissoes__field erp-permissoes__field--grow">
                    <span>Descrição</span>
                    <input type="text" wire:model="profileDescricao" class="erp-permissoes__input">
                </label>
            </div>
        @endif

        @if ($isAdminUser && ! $editingProfile)
            <p class="erp-permissoes__notice">Usuário administrador possui acesso total ao sistema.</p>
        @endif
    </div>

    <div class="erp-permissoes__grid">
        @foreach ($groups as $groupKey => $group)
            <fieldset class="erp-permissoes__group">
                <legend class="erp-permissoes__group-title">
                    {{ $group['label'] }}
                    <span class="erp-permissoes__group-actions">
                        <button type="button" wire:click="markGroup('{{ $groupKey }}', true)" class="erp-permissoes__link">Marcar</button>
                        <button type="button" wire:click="markGroup('{{ $groupKey }}', false)" class="erp-permissoes__link">Desmarcar</button>
                    </span>
                </legend>

                @foreach ($group['modules'] as $module => $meta)
                    @php $moduleKey = $module; @endphp
                    <div class="erp-permissoes__module">
                        <div class="erp-permissoes__module-title">
                            {{ $meta['label'] }}
                            <span class="erp-permissoes__group-actions">
                                <button type="button" wire:click="markModule('{{ $moduleKey }}', true)" class="erp-permissoes__link">Todos</button>
                                <button type="button" wire:click="markModule('{{ $moduleKey }}', false)" class="erp-permissoes__link">Nenhum</button>
                            </span>
                        </div>
                        <div class="erp-permissoes__checks">
                            @foreach ($meta['actions'] as $action => $label)
                                @php $permKey = ErpPermissionCatalog::key($module, $action); @endphp
                                <label class="erp-permissoes__check">
                                    <input
                                        type="checkbox"
                                        wire:model.live="checked.{{ $permKey }}"
                                        @disabled($isAdminUser && ! $editingProfile)
                                    >
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </fieldset>
        @endforeach
    </div>

    <div class="erp-permissoes-actions">
        <button type="button" wire:click="savePermissions" class="erp-permissoes-actions__btn erp-permissoes-actions__btn--primary" @disabled($isAdminUser && ! $editingProfile)>
            Salvar
        </button>
        <button type="button" wire:click="closeScreen" class="erp-permissoes-actions__btn erp-permissoes-actions__btn--close">
            Fechar
        </button>
    </div>
</div>
