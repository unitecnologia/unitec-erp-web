<?php
namespace App\Filament\Resources\UserResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\ErpProfile;
use App\Models\User;
use App\Models\Vendedor;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpOnboarding;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

trait ManagesUserFormModal
{
    public bool $userModalOpen = false;
    public ?int $userModalRecordId = null;

    /** @var array<string, mixed> */
    public array $userForm = [];

    public function createUser(): void
    {
        $onboardingUsuario = ErpOnboarding::step() === ErpOnboarding::STEP_USUARIO;
        if (! $onboardingUsuario && ! $this->erpAuthorizeOrNotify('acesso.usuarios.create')) {
            return;
        }
        if ($this->userModalOpen) {
            return;
        }
        $this->userModalRecordId = null;
        $this->userForm = $this->defaultUserFormData();
        $this->userModalOpen = true;
    }

    public function editUser(): void
    {
        if (! $this->erpAuthorizeOrNotify('acesso.usuarios.update')) {
            return;
        }
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }
        $record = User::query()->with('empresas')->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()
                ->title('Usuário não encontrado.')
                ->warning()
                ->send();
            return;
        }
        $this->userModalRecordId = $record->getKey();
        $this->userForm = $this->userFormDataFromRecord($record);
        $this->userModalOpen = true;
    }

    public function closeUserModal(): void
    {
        if (ErpOnboarding::step() === ErpOnboarding::STEP_USUARIO) {
            Notification::make()
                ->title('Cadastre o usuário para continuar o primeiro acesso.')
                ->warning()
                ->send();

            return;
        }

        $this->userModalOpen = false;
        $this->userModalRecordId = null;
        $this->userForm = [];
    }

    public function selectCopyPermissionsUser(int $userId): void
    {
        $this->userForm['copiar_permissoes_de'] = (string) $userId;
    }

    public function setCopiarPermissoes(bool $checked): void
    {
        $this->userForm['copiar_permissoes'] = $checked ? 'S' : 'N';
        if (! $checked) {
            $this->userForm['copiar_permissoes_de'] = '';
            return;
        }
        if (! filled($this->userForm['copiar_permissoes_de'] ?? null)) {
            $first = $this->userCopyPermissionUsers()[0] ?? null;
            if ($first) {
                $this->userForm['copiar_permissoes_de'] = (string) $first['id'];
            }
        }
    }

    public function getCopiarPermissoesAtivoProperty(): bool
    {
        return ($this->userForm['copiar_permissoes'] ?? 'N') === 'S';
    }

    public function saveUser(): void
    {
        $isCreate = $this->userModalRecordId === null;
        $onboardingUsuario = ErpOnboarding::step() === ErpOnboarding::STEP_USUARIO;
        $permission = $isCreate ? 'acesso.usuarios.create' : 'acesso.usuarios.update';
        if (! $onboardingUsuario && ! $this->erpAuthorizeOrNotify($permission)) {
            return;
        }

        $this->normalizeUserFormBeforeSave();

        $rules = [
            'userForm.name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('users', 'name')->ignore($this->userModalRecordId),
            ],
            'userForm.empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'userForm.empresas' => ['required', 'array', 'min:1'],
            'userForm.empresas.*' => ['integer', 'exists:empresas,id'],
            'userForm.erp_profile_id' => ['nullable', 'integer', 'exists:erp_profiles,id'],
            'userForm.vendedor_id' => ['nullable', 'integer', 'exists:vendedores,id'],
            'userForm.senha_app_forca_vendas' => ['nullable', 'string', 'max:60'],
            'userForm.is_admin' => ['required', 'in:S,N'],
            'userForm.is_supervisor' => ['required', 'in:S,N'],
            'userForm.ativo' => ['required', 'in:S,N'],
            'userForm.copiar_permissoes' => ['required', 'in:S,N'],
        ];
        if ($isCreate) {
            $rules['userForm.password'] = ['required', 'string', 'min:2', 'max:60'];
            $rules['userForm.password_confirmation'] = ['required', 'same:userForm.password'];
        } else {
            $rules['userForm.password'] = ['nullable', 'string', 'min:2', 'max:60'];
            if (filled($this->userForm['password'] ?? null)) {
                $rules['userForm.password_confirmation'] = ['required', 'same:userForm.password'];
            } else {
                $rules['userForm.password_confirmation'] = ['nullable', 'same:userForm.password'];
            }
        }
        if (($this->userForm['copiar_permissoes'] ?? 'N') === 'S') {
            $rules['userForm.copiar_permissoes_de'] = [
                'required',
                'integer',
                Rule::exists('users', 'id'),
            ];
            if ($this->userModalRecordId) {
                $rules['userForm.copiar_permissoes_de'][] = Rule::notIn([$this->userModalRecordId]);
            }
        } else {
            $rules['userForm.copiar_permissoes_de'] = ['nullable', 'integer', 'exists:users,id'];
        }

        $this->validate($rules, [
            'userForm.name.required' => 'Informe o usuário.',
            'userForm.name.unique' => 'Este usuário já está em uso.',
            'userForm.password.required' => 'Informe a senha.',
            'userForm.password_confirmation.required' => 'Confirme a senha.',
            'userForm.password_confirmation.same' => 'A confirmação de senha não confere.',
            'userForm.empresa_id.required' => 'Selecione a empresa padrão.',
            'userForm.empresa_id.exists' => 'Empresa padrão inválida.',
            'userForm.empresas.required' => 'Selecione ao menos uma empresa liberada.',
            'userForm.empresas.min' => 'Selecione ao menos uma empresa liberada.',
            'userForm.empresas.*.exists' => 'Empresa liberada inválida.',
            'userForm.copiar_permissoes_de.required' => 'Selecione o usuário para copiar permissões.',
        ], [
            'userForm.name' => 'usuário',
            'userForm.password' => 'senha',
            'userForm.password_confirmation' => 'confirmação de senha',
            'userForm.senha_app_forca_vendas' => 'senha app força de vendas',
            'userForm.empresa_id' => 'empresa padrão',
            'userForm.empresas' => 'empresas liberadas',
            'userForm.erp_profile_id' => 'perfil',
            'userForm.vendedor_id' => 'vendedor padrão',
            'userForm.copiar_permissoes_de' => 'usuário para copiar permissões',
        ]);
        $name = mb_strtoupper(trim((string) $this->userForm['name']), 'UTF-8');
        $data = [
            'name' => $name,
            'empresa_id' => (int) $this->userForm['empresa_id'],
            'erp_profile_id' => filled($this->userForm['erp_profile_id'] ?? null)
                ? (int) $this->userForm['erp_profile_id']
                : null,
            'vendedor_id' => filled($this->userForm['vendedor_id'] ?? null)
                ? (int) $this->userForm['vendedor_id']
                : null,
            'senha_app_forca_vendas' => filled($this->userForm['senha_app_forca_vendas'] ?? null)
                ? (string) $this->userForm['senha_app_forca_vendas']
                : null,
            'is_admin' => ($this->userForm['is_admin'] ?? 'N') === 'S',
            'is_supervisor' => ($this->userForm['is_supervisor'] ?? 'N') === 'S',
            'ativo' => ($this->userForm['ativo'] ?? 'S') === 'S',
        ];
        if (filled($this->userForm['password'] ?? null)) {
            $plainPassword = (string) $this->userForm['password'];
            $data['password'] = Hash::make($plainPassword);
            $data['senha'] = $plainPassword;
        }
        $empresaIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($this->userForm['empresas'] ?? []))
        )));

        if (! in_array((int) $data['empresa_id'], $empresaIds, true)) {
            $empresaIds[] = (int) $data['empresa_id'];
        }

        if ($isCreate) {
            $record = User::query()->create($data);
        } else {
            $record = User::query()->findOrFail($this->userModalRecordId);
            $record->update($data);
        }

        $record->empresas()->sync($empresaIds);

        if (($this->userForm['copiar_permissoes'] ?? 'N') === 'S' && filled($this->userForm['copiar_permissoes_de'] ?? null)) {
            $this->copyUserPermissionsFrom((int) $this->userForm['copiar_permissoes_de'], $record);
        }
        if ((int) Auth::id() === $record->getKey()) {
            ErpAccess::storeInSession($record->fresh(), $record->effectivePermissionKeys());
        }

        if ($onboardingUsuario && $isCreate) {
            ErpOnboarding::advanceToColaborador();
            $this->userModalOpen = false;
            $this->userModalRecordId = null;
            $this->userForm = [];
            Notification::make()
                ->title('Usuário cadastrado. Agora cadastre o colaborador.')
                ->success()
                ->send();
            $this->redirect(\App\Filament\Resources\VendedorResource::getUrl('index'));

            return;
        }

        $this->closeUserModal();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()
            ->title($isCreate ? 'Usuário cadastrado.' : 'Usuário atualizado.')
            ->success()
            ->send();
    }

    protected function normalizeUserFormBeforeSave(): void
    {
        $form = $this->userForm;

        foreach (['name', 'password', 'password_confirmation', 'senha_app_forca_vendas'] as $field) {
            if (! array_key_exists($field, $form) || $form[$field] === null) {
                $form[$field] = '';
            } else {
                $form[$field] = is_string($form[$field]) ? trim($form[$field]) : (string) $form[$field];
            }
        }

        $form['empresas'] = array_values(array_unique(array_filter(
            array_map(static fn ($id): string => (string) ((int) $id), (array) ($form['empresas'] ?? [])),
            static fn (string $id): bool => $id !== '0' && $id !== ''
        )));

        $empresaId = (string) ((int) ($form['empresa_id'] ?? 0));
        if ($empresaId !== '0') {
            $form['empresa_id'] = $empresaId;
            if (! in_array($empresaId, $form['empresas'], true)) {
                $form['empresas'][] = $empresaId;
            }
        }

        foreach (['erp_profile_id', 'vendedor_id', 'copiar_permissoes_de'] as $field) {
            if (($form[$field] ?? '') === '' || ($form[$field] ?? null) === null) {
                $form[$field] = '';
            } else {
                $form[$field] = (string) ((int) $form[$field]);
            }
        }

        foreach (['is_admin', 'is_supervisor', 'ativo', 'copiar_permissoes'] as $field) {
            $form[$field] = (($form[$field] ?? 'N') === 'S') ? 'S' : 'N';
        }

        $this->userForm = $form;
    }

    public function deleteUser(): void
    {
        if (! $this->erpAuthorizeOrNotify('acesso.usuarios.delete')) {
            return;
        }
        $recordId = $this->highlightedRecordIdOrNotify('delete');
        if (! $recordId) {
            return;
        }
        if ((int) Auth::id() === $recordId) {
            Notification::make()
                ->title('Não é possível excluir o usuário logado.')
                ->warning()
                ->send();
            return;
        }
        $record = User::query()->find($recordId);
        if (! $record) {
            return;
        }
        $record->delete();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()
            ->title('Usuário excluído.')
            ->success()
            ->send();
    }

    public function openUserPermissions(): void
    {
        $recordId = $this->highlightedRecordIdOrNotify('edit');
        if (! $recordId) {
            return;
        }
        if (! ErpAccess::currentCan('acesso.permissoes.manage')) {
            Notification::make()
                ->title('Sem permissão para gerenciar permissões.')
                ->danger()
                ->send();
            return;
        }
        $this->redirect(\App\Filament\Pages\PermissoesPage::getUrl(['usuario' => $recordId]));
    }

    /**
     * @return array<string, mixed>
     */

    protected function defaultUserFormData(): array
    {
        $defaultCopyFrom = User::query()
            ->orderBy('name')
            ->value('id');
        $onboarding = ErpOnboarding::step() === ErpOnboarding::STEP_USUARIO;

        return [
            'name' => '',
            'password' => '',
            'password_confirmation' => '',
            'senha_app_forca_vendas' => '',
            'empresa_id' => (string) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 1),
            'empresas' => [(string) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 1)],
            'erp_profile_id' => '',
            'vendedor_id' => '',
            'is_admin' => $onboarding ? 'S' : 'N',
            'is_supervisor' => 'N',
            'ativo' => 'S',
            'copiar_permissoes' => 'S',
            'copiar_permissoes_de' => $defaultCopyFrom ? (string) $defaultCopyFrom : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */

    protected function userFormDataFromRecord(User $record): array
    {
        $defaultCopyFrom = User::query()
            ->whereKeyNot($record->getKey())
            ->orderBy('name')
            ->value('id');
        return [
            'name' => $record->name,
            'password' => (string) ($record->senha ?? ''),
            'password_confirmation' => (string) ($record->senha ?? ''),
            'senha_app_forca_vendas' => (string) ($record->senha_app_forca_vendas ?? ''),
            'empresa_id' => (string) ($record->empresa_id ?? ''),
            'empresas' => $record->empresas->pluck('id')->map(fn ($id): string => (string) $id)->values()->all()
                ?: array_values(array_filter([(string) ($record->empresa_id ?? '')])),
            'erp_profile_id' => $record->erp_profile_id ? (string) $record->erp_profile_id : '',
            'vendedor_id' => $record->vendedor_id ? (string) $record->vendedor_id : '',
            'is_admin' => $record->is_admin ? 'S' : 'N',
            'is_supervisor' => $record->is_supervisor ? 'S' : 'N',
            'ativo' => $record->ativo ? 'S' : 'N',
            'copiar_permissoes' => 'N',
            'copiar_permissoes_de' => $defaultCopyFrom ? (string) $defaultCopyFrom : '',
        ];
    }

    protected function copyUserPermissionsFrom(int $sourceUserId, User $target): void
    {
        if ($sourceUserId === $target->getKey()) {
            return;
        }
        $source = User::query()->find($sourceUserId);
        if (! $source) {
            return;
        }
        $keys = $source->userPermissions()->pluck('permission_key')->all();
        ErpAccess::syncUserPermissions($target, $keys);
        $target->update(['erp_profile_id' => $source->erp_profile_id]);
        if ((int) Auth::id() === $target->getKey()) {
            ErpAccess::storeInSession($target->fresh(), $target->effectivePermissionKeys());
        }
    }

    public function updatedUserFormEmpresaId(mixed $value): void
    {
        $empresaId = (string) ((int) $value);
        if ($empresaId === '0') {
            return;
        }

        $empresas = array_map('strval', (array) ($this->userForm['empresas'] ?? []));
        if (! in_array($empresaId, $empresas, true)) {
            $empresas[] = $empresaId;
            $this->userForm['empresas'] = array_values($empresas);
        }
    }

    /**
     * @return array<int|string, string>
     */
    public function userEmpresaOptions(): array
    {
        return Empresa::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function userEmpresaCodigos(): array
    {
        return Empresa::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->pluck('codigo', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function userProfileOptions(): array
    {
        return ErpProfile::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */

    public function userVendedorOptions(): array
    {
        return Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */

    public function userCopyPermissionUsers(): array
    {
        return User::query()
            ->when(
                $this->userModalRecordId,
                fn ($query) => $query->whereKeyNot($this->userModalRecordId),
            )
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
            ])
            ->values()
            ->all();
    }
}
