<?php



namespace App\Filament\Resources\UserResource\Pages\Concerns;



use App\Models\ErpProfile;

use App\Models\User;

use App\Models\Vendedor;

use App\Support\Erp\ErpAccess;

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

        if (! $this->erpAuthorizeOrNotify('acesso.usuarios.create')) {

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



        $record = User::query()->find($this->highlightedRecordId);



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

        $permission = $isCreate ? 'acesso.usuarios.create' : 'acesso.usuarios.update';



        if (! $this->erpAuthorizeOrNotify($permission)) {

            return;

        }



        $rules = [

            'userForm.name' => ['required', 'string', 'max:80'],

            'userForm.email' => [

                'required',

                'email',

                'max:120',

                Rule::unique('users', 'email')->ignore($this->userModalRecordId),

            ],

            'userForm.empresa_id' => ['required', 'integer', 'exists:empresas,id'],

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



        $this->validate($rules, [], [

            'userForm.name' => 'nome',

            'userForm.email' => 'e-mail',

            'userForm.password' => 'senha',

            'userForm.password_confirmation' => 'confirmação de senha',

            'userForm.senha_app_forca_vendas' => 'senha app força de vendas',

            'userForm.empresa_id' => 'empresa',

            'userForm.erp_profile_id' => 'perfil',

            'userForm.vendedor_id' => 'vendedor padrão',

            'userForm.copiar_permissoes_de' => 'usuário para copiar permissões',

        ]);



        $data = [

            'name' => mb_strtoupper(trim((string) $this->userForm['name']), 'UTF-8'),

            'email' => mb_strtolower(trim((string) $this->userForm['email']), 'UTF-8'),

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



        if ($isCreate) {

            $record = User::query()->create($data);

        } else {

            $record = User::query()->findOrFail($this->userModalRecordId);

            $record->update($data);

        }



        if (($this->userForm['copiar_permissoes'] ?? 'N') === 'S' && filled($this->userForm['copiar_permissoes_de'] ?? null)) {

            $this->copyUserPermissionsFrom((int) $this->userForm['copiar_permissoes_de'], $record);

        }



        if ((int) Auth::id() === $record->getKey()) {

            ErpAccess::storeInSession($record->fresh(), $record->effectivePermissionKeys());

        }



        $this->closeUserModal();

        $this->clearListSelection();

        $this->resetTable();



        Notification::make()

            ->title($isCreate ? 'Usuário cadastrado.' : 'Usuário atualizado.')

            ->success()

            ->send();

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



        return [

            'name' => '',

            'email' => '',

            'password' => '',

            'password_confirmation' => '',

            'senha_app_forca_vendas' => '',

            'empresa_id' => (string) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? 1),

            'erp_profile_id' => '',

            'vendedor_id' => '',

            'is_admin' => 'N',

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

            'email' => $record->email,

            'password' => (string) ($record->senha ?? ''),

            'password_confirmation' => (string) ($record->senha ?? ''),

            'senha_app_forca_vendas' => (string) ($record->senha_app_forca_vendas ?? ''),

            'empresa_id' => (string) ($record->empresa_id ?? ''),

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

