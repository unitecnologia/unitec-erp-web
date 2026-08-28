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
        <label>
            <span>Confirmar senha</span>
            <input type="password" wire:model="userForm.password_confirmation" autocomplete="new-password">
            @error('userForm.password_confirmation') <small class="erp-permissoes__error">{{ $message }}</small> @enderror
        </label>
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
