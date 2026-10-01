@php
    $isNewUser = ! $this->selectedUserId;
@endphp

<div class="erp-permissoes__user-form">
    <label>
        <span>Usuário</span>
        <input type="text" wire:model="userForm.name" data-erp-uppercase autocomplete="off">
        @error('userForm.name') <small class="erp-permissoes__error">{{ $message }}</small> @enderror
    </label>

    <div class="erp-permissoes__form-grid">
        <label>
            <span>{{ $isNewUser ? 'Senha' : 'Nova senha (opcional)' }}</span>
            <input type="password" wire:model="userForm.password" autocomplete="new-password">
            @error('userForm.password') <small class="erp-permissoes__error">{{ $message }}</small> @enderror
        </label>
        @unless ($isNewUser)
            <label>
                <span>Senha atual</span>
                <input
                    type="text"
                    wire:model="userForm.senha_atual"
                    readonly
                    tabindex="-1"
                    autocomplete="off"
                    class="erp-permissoes__input-readonly"
                >
            </label>
        @endunless
        <label>
            <span>Senha do app</span>
            <input
                type="text"
                wire:model="userForm.senha_app_forca_vendas"
                autocomplete="off"
                placeholder="Mesma senha para os apps liberados"
            >
            @error('userForm.senha_app_forca_vendas') <small class="erp-permissoes__error">{{ $message }}</small> @enderror
        </label>
        <div
            class="erp-permissoes__apps"
            x-data="{
                open: false,
                labels: {
                    acesso_app_forca_vendas: 'Força de Vendas',
                    acesso_app_vendas_internas: 'Vendas Internas',
                    acesso_app_unitec_os: 'Unitec OS',
                    acesso_app_entregas: 'Entregas',
                    acesso_app_gestao: 'Gestão',
                },
                summary() {
                    const form = $wire.userForm || {};
                    const selected = Object.keys(this.labels).filter((key) => !!form[key]).map((key) => this.labels[key]);
                    return selected.length ? selected.join(', ') : '— Nenhum —';
                },
                count() {
                    const form = $wire.userForm || {};
                    return Object.keys(this.labels).filter((key) => !!form[key]).length;
                }
            }"
            @click.outside="open = false"
            @keydown.escape.window="open = false"
        >
            <span>Apps liberados</span>
            <button type="button" class="erp-permissoes__apps-toggle" @click="open = !open" :aria-expanded="open">
                <span class="erp-permissoes__apps-summary" :class="{ 'is-empty': count() === 0 }" x-text="summary()"></span>
                <svg class="erp-permissoes__apps-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" :style="open ? 'transform:rotate(180deg)' : ''"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="erp-permissoes__apps-panel" x-show="open" x-cloak x-transition.opacity role="listbox" aria-label="Apps liberados">
                <label class="erp-permissoes__apps-check">
                    <input type="checkbox" wire:model.live="userForm.acesso_app_forca_vendas">
                    <span>Força de Vendas</span>
                </label>
                <label class="erp-permissoes__apps-check">
                    <input type="checkbox" wire:model.live="userForm.acesso_app_vendas_internas">
                    <span>Vendas Internas</span>
                </label>
                <label class="erp-permissoes__apps-check">
                    <input type="checkbox" wire:model.live="userForm.acesso_app_unitec_os">
                    <span>Unitec OS</span>
                </label>
                <label class="erp-permissoes__apps-check">
                    <input type="checkbox" wire:model.live="userForm.acesso_app_entregas">
                    <span>Entregas</span>
                </label>
                <label class="erp-permissoes__apps-check">
                    <input type="checkbox" wire:model.live="userForm.acesso_app_gestao">
                    <span>Gestão</span>
                </label>
            </div>
        </div>
        <label>
            <span>Empresa padrão</span>
            <select wire:model="userForm.empresa_id">
                <option value="">— Selecione —</option>
                @foreach ($this->empresaOptions() as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Perfil</span>
            <select wire:model="userForm.erp_profile_id">
                <option value="">— Sem perfil —</option>
                @foreach ($this->profileOptions() as $id => $nome)
                    <option value="{{ $id }}">{{ $nome }}</option>
                @endforeach
            </select>
        </label>
        <div class="erp-perm-user-vinculo">
            <span>Operador (RH)</span>
            <div class="erp-perm-user-vinculo__box {{ $this->userOperadorVinculado() ? 'is-linked' : 'is-empty' }}">
                {{ $this->userOperadorVinculoInfo() }}
            </div>
            <small class="erp-perm-user-vinculo__hint">Somente leitura — vínculo em RH → Funcionários → aba Operador.</small>
        </div>
        <label>
            <span>Situação</span>
            <select wire:model="userForm.ativo">
                <option value="S">Ativo</option>
                <option value="N">Inativo</option>
            </select>
        </label>
        <label>
            <span>Administrador</span>
            <select wire:model="userForm.is_admin">
                <option value="N">Não</option>
                <option value="S">Sim</option>
            </select>
        </label>
    </div>

    <div class="erp-permissoes__form-actions">
        @if (! $isNewUser)
            <button type="button" wire:click="deleteUser" class="is-danger">Excluir usuário</button>
        @endif
        <button type="button" wire:click="saveUserForm" class="is-primary">{{ $isNewUser ? 'Cadastrar usuário' : 'Salvar cadastro' }}</button>
    </div>
</div>
