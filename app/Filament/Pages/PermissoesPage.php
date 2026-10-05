<?php

namespace App\Filament\Pages;

use App\Models\CaixaConta;
use App\Models\ErpProfile;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class PermissoesPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = '';

    protected static ?string $slug = 'permissoes';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'usuario')]
    public ?int $selectedUserId = null;

    #[Url(as: 'perfil')]
    public ?int $selectedProfileId = null;

    public ?int $profileTemplateId = null;

    #[Url(as: 'lista')]
    public string $sidebarTab = 'usuarios';

    #[Url(as: 'aba')]
    public string $activeTab = 'permissoes';

    public string $contextTitle = 'Selecione um usuário';

    public bool $permissionFullAccess = false;

    public bool $permissionLocked = false;

    /**
     * Lista carregada uma vez. A busca filtra no navegador.
     *
     * @var list<array{id: int, name: string, profile: string, ativo: bool}>
     */
    public array $userRows = [];

    /**
     * @var list<array{id: int, nome: string, descricao: string, system: bool}>
     */
    public array $profileRows = [];

    /** @var array{keys: list<string>, fullAccess: bool, locked: bool}|null */
    protected ?array $permBoot = null;

    /** @var array<string, mixed> */
    public array $userForm = [];

    /** @var list<int> IDs de empresas liberadas para o usuário selecionado. */
    public array $userEmpresaIds = [];

    public ?int $selectedBlockedEmpresaId = null;

    public ?int $selectedLiberatedEmpresaId = null;

    public string $empresaSearchBlocked = '';

    public string $empresaSearchLiberated = '';

    public ?int $caixaEmpresaId = null;

    /** @var list<int> IDs de caixas liberados para o usuário na empresa selecionada. */
    public array $userCaixaIds = [];

    public ?int $userCaixaPadraoId = null;

    public ?int $selectedBlockedCaixaId = null;

    public ?int $selectedLiberatedCaixaId = null;

    public string $caixaSearchBlocked = '';

    public string $caixaSearchLiberated = '';

    public string $profileNome = '';

    public string $profileDescricao = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('acesso.permissoes.manage');
    }

    public function mount(): void
    {
        ErpScreen::set('Permissões / Usuários');

        if ($this->activeTab === 'menu') {
            $this->activeTab = 'permissoes';
        }

        $this->refreshDirectory();
        $this->selectLoggedUserWhenNone();

        if ($this->selectedProfileId) {
            $this->permBoot = $this->captureProfile($this->selectedProfileId);
        } elseif ($this->selectedUserId) {
            $this->permBoot = $this->captureUser($this->selectedUserId);
        }

        $this->applyPermissionHeader($this->permBoot);

        if ($this->activeTab === 'cadastro' && $this->sidebarTab === 'usuarios' && $this->selectedUserId) {
            $this->loadUserForm();
        }
    }

    /**
     * @return array{keys: list<string>, fullAccess: bool, locked: bool}
     */
    public function permissionBoot(): array
    {
        return $this->permBoot ?? [
            'keys' => [],
            'fullAccess' => false,
            'locked' => false,
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-form-page', 'erp-os-form-page', 'erp-permissoes-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.permissoes.screen'),
            ]);
    }

    public function refreshDirectory(): void
    {
        $this->userRows = User::query()
            ->leftJoin('erp_profiles', 'erp_profiles.id', '=', 'users.erp_profile_id')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.ativo', 'erp_profiles.nome as profile_nome'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'profile' => (string) ($user->getAttribute('profile_nome') ?: 'Sem perfil'),
                'ativo' => (bool) $user->ativo,
            ])
            ->all();

        $this->profileRows = ErpProfile::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'descricao', 'is_system'])
            ->map(static fn (ErpProfile $profile): array => [
                'id' => (int) $profile->id,
                'nome' => (string) $profile->nome,
                'descricao' => (string) ($profile->descricao ?: ($profile->is_system ? 'Perfil do sistema' : 'Perfil personalizado')),
                'system' => (bool) $profile->is_system,
            ])
            ->all();
    }

    protected function selectLoggedUserWhenNone(): void
    {
        if ($this->sidebarTab !== 'usuarios' || $this->selectedUserId || $this->selectedProfileId) {
            return;
        }

        $authId = (int) Auth::id();

        if ($authId <= 0) {
            return;
        }

        foreach ($this->userRows as $row) {
            if ((int) $row['id'] === $authId) {
                $this->selectedUserId = $authId;

                return;
            }
        }
    }

    public function setSidebarTab(string $tab): void
    {
        if (in_array($tab, ['usuarios', 'perfis'], true)) {
            $this->sidebarTab = $tab;
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['cadastro', 'permissoes', 'empresas', 'caixas'], true)) {
            return;
        }

        $this->activeTab = $tab;

        if ($this->sidebarTab !== 'usuarios' || ! $this->selectedUserId) {
            return;
        }

        if ($tab === 'cadastro') {
            $this->loadUserForm();
        } elseif ($tab === 'empresas') {
            $this->loadUserEmpresas();
        } elseif ($tab === 'caixas') {
            $this->loadUserCaixas();
        }
    }

    public function selectUser(int $userId): void
    {
        $state = $this->captureUser($userId);

        if ($state === null) {
            return;
        }

        $this->sidebarTab = 'usuarios';
        $this->selectedUserId = $userId;
        $this->selectedProfileId = null;
        $this->profileTemplateId = null;
        $this->selectedBlockedEmpresaId = null;
        $this->selectedLiberatedEmpresaId = null;
        $this->empresaSearchBlocked = '';
        $this->empresaSearchLiberated = '';
        $this->selectedBlockedCaixaId = null;
        $this->selectedLiberatedCaixaId = null;
        $this->caixaSearchBlocked = '';
        $this->caixaSearchLiberated = '';
        $this->applyPermissionHeader($state);
        $this->dispatch('erp-permissoes-sync', ...$state);

        if ($this->activeTab === 'cadastro') {
            $this->loadUserForm();
        } elseif ($this->activeTab === 'empresas') {
            $this->loadUserEmpresas();
        } elseif ($this->activeTab === 'caixas') {
            $this->loadUserCaixas();
        }
    }

    public function selectProfile(int $profileId): void
    {
        $state = $this->captureProfile($profileId);

        if ($state === null) {
            return;
        }

        $this->sidebarTab = 'perfis';
        $this->selectedUserId = null;
        $this->selectedProfileId = $profileId;
        $this->profileNome = $state['name'];
        $this->profileDescricao = $state['descricao'];
        $this->userEmpresaIds = [];
        $this->userCaixaIds = [];
        $this->userCaixaPadraoId = null;
        $this->caixaEmpresaId = null;

        if (in_array($this->activeTab, ['empresas', 'caixas'], true)) {
            $this->activeTab = 'permissoes';
        }

        $this->applyPermissionHeader($state);
        $this->dispatch('erp-permissoes-sync', ...$state);
    }

    public function newProfile(): void
    {
        $this->sidebarTab = 'perfis';
        $this->selectedUserId = null;
        $this->selectedProfileId = null;
        $this->profileNome = '';
        $this->profileDescricao = '';
        $this->contextTitle = 'Novo perfil';
        $this->permissionFullAccess = false;
        $this->permissionLocked = false;
        $this->activeTab = 'cadastro';
        $this->dispatch('erp-permissoes-sync', keys: [], fullAccess: false, locked: false);
    }

    public function newUser(): void
    {
        $this->sidebarTab = 'usuarios';
        $this->selectedUserId = null;
        $this->selectedProfileId = null;
        $empresaId = (string) (session('erp_empresa_id') ?? Auth::user()?->empresa_id ?? '');
        $this->userForm = [
            'name' => '',
            'password' => '',
            'senha_atual' => '',
            'senha_app_forca_vendas' => '',
            'acesso_app_forca_vendas' => false,
            'acesso_app_vendas_internas' => false,
            'acesso_app_unitec_os' => false,
            'acesso_app_entregas' => false,
            'acesso_app_gestao' => false,
            'acesso_app_inventario' => false,
            'empresa_id' => $empresaId,
            'erp_profile_id' => '',
            'is_admin' => 'N',
            'ativo' => 'S',
        ];
        $this->userEmpresaIds = filled($empresaId) ? [(int) $empresaId] : [];
        $this->selectedBlockedEmpresaId = null;
        $this->selectedLiberatedEmpresaId = null;
        $this->contextTitle = 'Novo usuário';
        $this->permissionFullAccess = false;
        $this->permissionLocked = false;
        $this->activeTab = 'cadastro';
        $this->dispatch('erp-permissoes-sync', keys: [], fullAccess: false, locked: false);
    }

    public function loadUserForm(): void
    {
        $user = $this->selectedUserId ? User::query()->find($this->selectedUserId) : null;

        if (! $user) {
            return;
        }

        $this->userForm = [
            'name' => $user->name,
            'password' => '',
            'senha_atual' => (string) ($user->senha ?? ''),
            'senha_app_forca_vendas' => (string) ($user->senha_app_forca_vendas ?? ''),
            'acesso_app_forca_vendas' => (bool) $user->acesso_app_forca_vendas,
            'acesso_app_vendas_internas' => (bool) $user->acesso_app_vendas_internas,
            'acesso_app_unitec_os' => (bool) $user->acesso_app_unitec_os,
            'acesso_app_entregas' => (bool) $user->acesso_app_entregas,
            'acesso_app_gestao' => (bool) $user->acesso_app_gestao,
            'acesso_app_inventario' => (bool) $user->acesso_app_inventario,
            'empresa_id' => (string) ($user->empresa_id ?? ''),
            'erp_profile_id' => $user->erp_profile_id ? (string) $user->erp_profile_id : '',
            'is_admin' => $user->is_admin ? 'S' : 'N',
            'ativo' => $user->ativo ? 'S' : 'N',
        ];
    }

    public function loadUserEmpresas(): void
    {
        $user = $this->selectedUserId ? User::query()->find($this->selectedUserId) : null;

        if (! $user) {
            $defaultId = (int) ($this->userForm['empresa_id'] ?? 0);
            $this->userEmpresaIds = $defaultId > 0 ? [$defaultId] : [];

            return;
        }

        $ids = $user->empresas()
            ->pluck('empresas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if (filled($user->empresa_id)) {
            $ids[] = (int) $user->empresa_id;
        }

        $this->userEmpresaIds = array_values(array_unique($ids));
        $this->userForm['empresa_id'] = (string) ($user->empresa_id ?? ($this->userEmpresaIds[0] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    public function empresaOptions(): array
    {
        return Empresa::query()->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all();
    }

    public function userOperadorVinculado(): bool
    {
        if (! $this->selectedUserId) {
            return false;
        }

        return User::query()
            ->whereKey($this->selectedUserId)
            ->whereNotNull('vendedor_id')
            ->exists();
    }

    public function userOperadorVinculoInfo(): string
    {
        if (! $this->selectedUserId) {
            return 'Ainda não vinculado — configure em RH → Funcionários.';
        }

        $user = User::query()->with('vendedor')->find($this->selectedUserId);
        $vendedor = $user?->vendedor;

        if (! $vendedor) {
            return 'Não vinculado — configure em RH → Funcionários → aba Operador.';
        }

        $label = trim(($vendedor->codigo !== null && $vendedor->codigo !== '' ? $vendedor->codigo.' — ' : '').(string) $vendedor->nome);

        return $vendedor->ativo
            ? $label
            : $label.' (operador inativo)';
    }

    public function saveUserForm(): void
    {
        $isCreate = ! $this->selectedUserId;
        $permission = $isCreate ? 'acesso.usuarios.create' : 'acesso.usuarios.update';

        if (! ErpAccess::currentCan($permission)) {
            Notification::make()->title('Sem permissão para esta operação.')->danger()->send();

            return;
        }

        $rules = [
            'userForm.name' => ['required', 'string', 'max:80', Rule::unique('users', 'name')->ignore($this->selectedUserId)],
            'userForm.empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'userForm.erp_profile_id' => ['nullable', 'integer', 'exists:erp_profiles,id'],
            'userForm.is_admin' => ['required', 'in:S,N'],
            'userForm.ativo' => ['required', 'in:S,N'],
            'userForm.senha_app_forca_vendas' => ['nullable', 'string', 'max:60'],
        ];

        if ($isCreate) {
            $rules['userForm.password'] = ['required', 'string', 'min:2', 'max:60'];
        } elseif (filled($this->userForm['password'] ?? null)) {
            $rules['userForm.password'] = ['string', 'min:2', 'max:60'];
        }

        $this->validate($rules);

        // vendedor_id não é editável aqui — único escritor: RH → Funcionários (aba Operador).
        $data = [
            'name' => mb_strtoupper(trim((string) $this->userForm['name']), 'UTF-8'),
            'empresa_id' => (int) $this->userForm['empresa_id'],
            'erp_profile_id' => filled($this->userForm['erp_profile_id'] ?? null) ? (int) $this->userForm['erp_profile_id'] : null,
            'is_admin' => ($this->userForm['is_admin'] ?? 'N') === 'S',
            'ativo' => ($this->userForm['ativo'] ?? 'S') === 'S',
            'senha_app_forca_vendas' => filled($this->userForm['senha_app_forca_vendas'] ?? null)
                ? (string) $this->userForm['senha_app_forca_vendas']
                : null,
            'acesso_app_forca_vendas' => filter_var($this->userForm['acesso_app_forca_vendas'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'acesso_app_vendas_internas' => filter_var($this->userForm['acesso_app_vendas_internas'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'acesso_app_unitec_os' => filter_var($this->userForm['acesso_app_unitec_os'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'acesso_app_entregas' => filter_var($this->userForm['acesso_app_entregas'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'acesso_app_gestao' => filter_var($this->userForm['acesso_app_gestao'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'acesso_app_inventario' => filter_var($this->userForm['acesso_app_inventario'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];

        if (filled($this->userForm['password'] ?? null)) {
            $plain = (string) $this->userForm['password'];
            $data['password'] = Hash::make($plain);
            $data['senha'] = $plain;
        }

        $user = $isCreate
            ? User::query()->create($data)
            : tap(User::query()->findOrFail($this->selectedUserId), fn (User $record) => $record->update($data));

        $liberadas = array_values(array_unique(array_map('intval', $this->userEmpresaIds)));
        if (! in_array((int) $data['empresa_id'], $liberadas, true)) {
            $liberadas[] = (int) $data['empresa_id'];
        }
        if ($liberadas === []) {
            $liberadas = [(int) $data['empresa_id']];
        }
        $user->empresas()->sync($liberadas);
        $this->userEmpresaIds = $liberadas;

        $this->selectedUserId = $user->getKey();
        $this->selectedProfileId = null;
        $this->profileTemplateId = null;
        $this->refreshDirectory();
        $state = $this->captureUser((int) $user->getKey());
        $this->applyPermissionHeader($state);
        $this->dispatch('erp-permissoes-sync', ...($state ?? ['keys' => [], 'fullAccess' => false, 'locked' => false]));
        $this->activeTab = 'permissoes';

        Notification::make()
            ->title($isCreate ? 'Usuário cadastrado.' : 'Usuário atualizado.')
            ->success()
            ->send();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function empresasCatalogo(): array
    {
        return Empresa::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome', 'razao_social', 'fantasia'])
            ->map(function (Empresa $empresa): array {
                $nome = trim((string) ($empresa->nome ?: $empresa->fantasia ?: $empresa->razao_social ?: 'Empresa'));
                $codigo = filled($empresa->codigo) ? (string) $empresa->codigo : (string) $empresa->id;

                return [
                    'id' => (int) $empresa->id,
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'label' => $codigo.' — '.$nome,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, label: string}>
     */
    public function empresasBloqueadas(): array
    {
        $liberadas = array_map('intval', $this->userEmpresaIds);
        $search = mb_strtolower(trim($this->empresaSearchBlocked), 'UTF-8');

        return array_values(array_filter(
            $this->empresasCatalogo(),
            function (array $empresa) use ($liberadas, $search): bool {
                if (in_array($empresa['id'], $liberadas, true)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($empresa['label'], 'UTF-8'), $search);
            },
        ));
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, label: string, padrao: bool}>
     */
    public function empresasLiberadas(): array
    {
        $liberadas = array_map('intval', $this->userEmpresaIds);
        $padrao = (int) ($this->userForm['empresa_id'] ?? 0);
        $search = mb_strtolower(trim($this->empresaSearchLiberated), 'UTF-8');
        $catalogo = collect($this->empresasCatalogo())->keyBy('id');

        $items = [];
        foreach ($liberadas as $id) {
            $empresa = $catalogo->get($id);
            if (! $empresa) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($empresa['label'], 'UTF-8'), $search)) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'codigo' => $empresa['codigo'],
                'nome' => $empresa['nome'],
                'label' => $empresa['label'],
                'padrao' => $padrao === $id,
            ];
        }

        return $items;
    }

    public function selectBlockedEmpresa(int $empresaId): void
    {
        $this->selectedBlockedEmpresaId = $empresaId;
        $this->selectedLiberatedEmpresaId = null;
    }

    public function selectLiberatedEmpresa(int $empresaId): void
    {
        $this->selectedLiberatedEmpresaId = $empresaId;
        $this->selectedBlockedEmpresaId = null;
    }

    public function liberarEmpresaSelecionada(): void
    {
        if (! $this->selectedBlockedEmpresaId) {
            return;
        }

        $this->liberarEmpresa((int) $this->selectedBlockedEmpresaId);
    }

    public function bloquearEmpresaSelecionada(): void
    {
        if (! $this->selectedLiberatedEmpresaId) {
            return;
        }

        $this->bloquearEmpresa((int) $this->selectedLiberatedEmpresaId);
    }

    public function liberarEmpresa(int $empresaId): void
    {
        $this->liberarEmpresas([$empresaId]);
        $this->selectedBlockedEmpresaId = null;
        $this->selectedLiberatedEmpresaId = $empresaId;
    }

    public function bloquearEmpresa(int $empresaId): void
    {
        $this->bloquearEmpresas([$empresaId]);
        $this->selectedLiberatedEmpresaId = null;
        $this->selectedBlockedEmpresaId = $empresaId;
    }

    public function liberarTodasEmpresas(): void
    {
        $ids = array_map(
            static fn (array $empresa): int => (int) $empresa['id'],
            $this->empresasCatalogo(),
        );
        $this->liberarEmpresas($ids);
    }

    public function bloquearTodasEmpresas(): void
    {
        $this->bloquearEmpresas(array_map('intval', $this->userEmpresaIds));
    }

    /**
     * @param  list<int>  $ids
     */
    protected function liberarEmpresas(array $ids): void
    {
        $merged = array_values(array_unique([
            ...array_map('intval', $this->userEmpresaIds),
            ...array_map('intval', $ids),
        ]));
        $this->userEmpresaIds = $merged;

        if (! filled($this->userForm['empresa_id'] ?? null) && $merged !== []) {
            $this->userForm['empresa_id'] = (string) $merged[0];
        }
    }

    /**
     * @param  list<int>  $ids
     */
    protected function bloquearEmpresas(array $ids): void
    {
        $bloquear = array_map('intval', $ids);
        $restantes = array_values(array_filter(
            array_map('intval', $this->userEmpresaIds),
            static fn (int $id): bool => ! in_array($id, $bloquear, true),
        ));

        $this->userEmpresaIds = $restantes;

        $padrao = (int) ($this->userForm['empresa_id'] ?? 0);
        if ($padrao > 0 && in_array($padrao, $bloquear, true)) {
            $this->userForm['empresa_id'] = $restantes !== [] ? (string) $restantes[0] : '';
        }
    }

    public function definirEmpresaPadrao(int $empresaId): void
    {
        if (! in_array($empresaId, array_map('intval', $this->userEmpresaIds), true)) {
            return;
        }

        $this->userForm['empresa_id'] = (string) $empresaId;
        $this->selectedLiberatedEmpresaId = $empresaId;
    }

    public function saveUserEmpresas(): void
    {
        if ($this->sidebarTab === 'perfis' || ! $this->selectedUserId) {
            Notification::make()
                ->title('Selecione um usuário para definir as empresas.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::currentCan('acesso.usuarios.update')) {
            Notification::make()->title('Sem permissão para alterar usuários.')->danger()->send();

            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        $liberadas = array_values(array_unique(array_map('intval', $this->userEmpresaIds)));
        $padrao = (int) ($this->userForm['empresa_id'] ?? 0);

        if ($liberadas === []) {
            Notification::make()
                ->title('Libere ao menos uma empresa.')
                ->warning()
                ->send();

            return;
        }

        if ($padrao <= 0 || ! in_array($padrao, $liberadas, true)) {
            $padrao = $liberadas[0];
            $this->userForm['empresa_id'] = (string) $padrao;
        }

        $user->forceFill(['empresa_id' => $padrao])->save();
        $user->empresas()->sync($liberadas);
        $this->userEmpresaIds = $liberadas;
        $this->loadUserForm();

        Notification::make()
            ->title('Empresas do usuário salvas.')
            ->success()
            ->send();
    }

    public function loadUserCaixas(): void
    {
        $user = $this->selectedUserId ? User::query()->find($this->selectedUserId) : null;

        $empresaOptions = $this->caixaEmpresaOptions();
        $empresaIds = array_map('intval', array_keys($empresaOptions));
        $empresaAtivaId = ErpContext::currentEmpresaId();

        if ($empresaAtivaId > 0 && in_array($empresaAtivaId, $empresaIds, true)) {
            $this->caixaEmpresaId = $empresaAtivaId;
        } elseif ($this->caixaEmpresaId === null || ! in_array((int) $this->caixaEmpresaId, $empresaIds, true)) {
            $preferida = (int) ($this->userForm['empresa_id'] ?? ($user?->empresa_id ?? 0));
            $this->caixaEmpresaId = in_array($preferida, $empresaIds, true)
                ? $preferida
                : ($empresaIds[0] ?? null);
        }

        if (! $user || ! $this->caixaEmpresaId) {
            $this->userCaixaIds = [];
            $this->userCaixaPadraoId = null;

            return;
        }

        $rows = DB::table('caixa_conta_user')
            ->where('user_id', $user->getKey())
            ->where('empresa_id', (int) $this->caixaEmpresaId)
            ->orderByDesc('is_padrao')
            ->orderBy('caixa_conta_id')
            ->get(['caixa_conta_id', 'is_padrao']);

        $this->userCaixaIds = $rows
            ->pluck('caixa_conta_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $padrao = $rows->firstWhere('is_padrao', true)?->caixa_conta_id
            ?? $rows->first()?->caixa_conta_id;

        $this->userCaixaPadraoId = $padrao ? (int) $padrao : null;
        $this->selectedBlockedCaixaId = null;
        $this->selectedLiberatedCaixaId = null;
    }

    public function updatedCaixaEmpresaId(): void
    {
        $this->loadUserCaixas();
    }

    /**
     * @return array<int, string>
     */
    public function caixaEmpresaOptions(): array
    {
        $ids = array_map('intval', $this->userEmpresaIds);

        if ($ids === [] && filled($this->userForm['empresa_id'] ?? null)) {
            $ids[] = (int) $this->userForm['empresa_id'];
        }

        if ($ids === [] && $this->selectedUserId) {
            $user = User::query()->find($this->selectedUserId);
            if ($user) {
                $ids = $user->accessibleEmpresaIds();
            }
        }

        if ($ids === []) {
            return Empresa::query()
                ->where('ativo', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nome', 'razao_social'])
                ->mapWithKeys(fn (Empresa $empresa): array => [
                    (int) $empresa->id => $this->empresaLabel($empresa),
                ])
                ->all();
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nome', 'razao_social'])
            ->mapWithKeys(fn (Empresa $empresa): array => [
                (int) $empresa->id => $this->empresaLabel($empresa),
            ])
            ->all();
    }

    protected function empresaLabel(Empresa $empresa): string
    {
        $codigo = filled($empresa->codigo)
            ? str_pad((string) $empresa->codigo, 3, '0', STR_PAD_LEFT)
            : (string) $empresa->id;
        $nome = trim((string) ($empresa->nome ?: $empresa->razao_social ?: 'Empresa'));

        return $codigo.' — '.$nome;
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, label: string}>
     */
    public function caixasCatalogo(): array
    {
        CaixaConta::ensurePdvOperacional();

        return CaixaConta::query()
            ->assignable()
            ->orderBy('codigo')
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->map(function (CaixaConta $caixa): array {
                $codigo = filled($caixa->codigo) ? (string) $caixa->codigo : (string) $caixa->id;
                $nome = mb_strtoupper(trim((string) $caixa->nome), 'UTF-8');

                return [
                    'id' => (int) $caixa->id,
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'label' => $codigo.' — '.$nome,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, label: string}>
     */
    public function caixasBloqueados(): array
    {
        $liberados = array_map('intval', $this->userCaixaIds);
        $search = mb_strtolower(trim($this->caixaSearchBlocked), 'UTF-8');

        return array_values(array_filter(
            $this->caixasCatalogo(),
            function (array $caixa) use ($liberados, $search): bool {
                if (in_array($caixa['id'], $liberados, true)) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($caixa['label'], 'UTF-8'), $search);
            },
        ));
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, label: string, padrao: bool}>
     */
    public function caixasLiberados(): array
    {
        $liberados = array_map('intval', $this->userCaixaIds);
        $padrao = (int) ($this->userCaixaPadraoId ?? 0);
        $search = mb_strtolower(trim($this->caixaSearchLiberated), 'UTF-8');
        $catalogo = collect($this->caixasCatalogo())->keyBy('id');

        $items = [];
        foreach ($liberados as $id) {
            $caixa = $catalogo->get($id);
            if (! $caixa) {
                continue;
            }

            if ($search !== '' && ! str_contains(mb_strtolower($caixa['label'], 'UTF-8'), $search)) {
                continue;
            }

            $items[] = [
                'id' => $id,
                'codigo' => $caixa['codigo'],
                'nome' => $caixa['nome'],
                'label' => $caixa['label'],
                'padrao' => $padrao === $id,
            ];
        }

        return $items;
    }

    public function selectBlockedCaixa(int $caixaId): void
    {
        $this->selectedBlockedCaixaId = $caixaId;
        $this->selectedLiberatedCaixaId = null;
    }

    public function selectLiberatedCaixa(int $caixaId): void
    {
        $this->selectedLiberatedCaixaId = $caixaId;
        $this->selectedBlockedCaixaId = null;
    }

    public function liberarCaixaSelecionado(): void
    {
        if ($this->selectedBlockedCaixaId) {
            $this->liberarCaixa((int) $this->selectedBlockedCaixaId);
        }
    }

    public function bloquearCaixaSelecionado(): void
    {
        if ($this->selectedLiberatedCaixaId) {
            $this->bloquearCaixa((int) $this->selectedLiberatedCaixaId);
        }
    }

    public function liberarTodosCaixas(): void
    {
        $this->liberarCaixas(array_map(
            static fn (array $caixa): int => (int) $caixa['id'],
            $this->caixasCatalogo(),
        ));
    }

    public function bloquearTodosCaixas(): void
    {
        $this->bloquearCaixas(array_map('intval', $this->userCaixaIds));
    }

    public function liberarCaixa(int $caixaId): void
    {
        $this->liberarCaixas([$caixaId]);
        $this->selectedBlockedCaixaId = null;
        $this->selectedLiberatedCaixaId = $caixaId;
    }

    public function bloquearCaixa(int $caixaId): void
    {
        $this->bloquearCaixas([$caixaId]);
        $this->selectedLiberatedCaixaId = null;
        $this->selectedBlockedCaixaId = $caixaId;
    }

    /**
     * @param  list<int>  $ids
     */
    protected function liberarCaixas(array $ids): void
    {
        $merged = array_values(array_unique([
            ...array_map('intval', $this->userCaixaIds),
            ...array_map('intval', $ids),
        ]));
        $this->userCaixaIds = $merged;

        if (! $this->userCaixaPadraoId && $merged !== []) {
            $this->userCaixaPadraoId = $merged[0];
        }
    }

    /**
     * @param  list<int>  $ids
     */
    protected function bloquearCaixas(array $ids): void
    {
        $bloquear = array_map('intval', $ids);
        $restantes = array_values(array_filter(
            array_map('intval', $this->userCaixaIds),
            static fn (int $id): bool => ! in_array($id, $bloquear, true),
        ));

        $this->userCaixaIds = $restantes;

        if ($this->userCaixaPadraoId && in_array((int) $this->userCaixaPadraoId, $bloquear, true)) {
            $this->userCaixaPadraoId = $restantes[0] ?? null;
        }
    }

    public function definirCaixaPadrao(int $caixaId): void
    {
        if (! in_array($caixaId, array_map('intval', $this->userCaixaIds), true)) {
            return;
        }

        $this->userCaixaPadraoId = $caixaId;
        $this->selectedLiberatedCaixaId = $caixaId;
    }

    public function saveUserCaixas(): void
    {
        if ($this->sidebarTab === 'perfis' || ! $this->selectedUserId) {
            Notification::make()
                ->title('Selecione um usuário para definir os caixas.')
                ->warning()
                ->send();

            return;
        }

        if (! ErpAccess::currentCan('acesso.usuarios.update')) {
            Notification::make()->title('Sem permissão para alterar usuários.')->danger()->send();

            return;
        }

        $empresaId = (int) ($this->caixaEmpresaId ?? 0);

        if ($empresaId <= 0) {
            Notification::make()
                ->title('Selecione a empresa.')
                ->warning()
                ->send();

            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        $liberados = array_values(array_unique(array_map('intval', $this->userCaixaIds)));
        $padrao = (int) ($this->userCaixaPadraoId ?? 0);

        if ($liberados !== [] && ($padrao <= 0 || ! in_array($padrao, $liberados, true))) {
            $padrao = $liberados[0];
            $this->userCaixaPadraoId = $padrao;
        }

        DB::table('caixa_conta_user')
            ->where('user_id', $user->getKey())
            ->where('empresa_id', $empresaId)
            ->delete();

        $now = now();
        foreach ($liberados as $caixaId) {
            DB::table('caixa_conta_user')->insert([
                'user_id' => $user->getKey(),
                'empresa_id' => $empresaId,
                'caixa_conta_id' => $caixaId,
                'is_padrao' => $padrao === $caixaId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Notification::make()
            ->title('Caixas do usuário salvos.')
            ->success()
            ->send();
    }

    public function deleteUser(): void
    {
        if (! $this->selectedUserId || ! ErpAccess::currentCan('acesso.usuarios.delete')) {
            return;
        }

        if ((int) Auth::id() === $this->selectedUserId) {
            Notification::make()->title('Não é possível excluir o usuário logado.')->warning()->send();

            return;
        }

        User::query()->whereKey($this->selectedUserId)->delete();
        $this->newUser();
        Notification::make()->title('Usuário excluído.')->success()->send();
    }

    /**
     * @return array{keys: list<string>, fullAccess: bool, locked: bool, name: string}|null
     */
    protected function captureUser(int $userId): ?array
    {
        $user = User::query()->select(['id', 'name', 'is_admin', 'erp_profile_id'])->find($userId);

        if (! $user) {
            return null;
        }

        if ($user->is_admin) {
            return [
                'keys' => [],
                'fullAccess' => true,
                'locked' => true,
                'name' => (string) $user->name,
            ];
        }

        return [
            'keys' => $this->grantedKeys((int) $user->id, $user->erp_profile_id ? (int) $user->erp_profile_id : null),
            'fullAccess' => false,
            'locked' => false,
            'name' => (string) $user->name,
        ];
    }

    /**
     * @return array{keys: list<string>, fullAccess: bool, locked: bool, name: string, descricao: string}|null
     */
    protected function captureProfile(int $profileId): ?array
    {
        $profile = ErpProfile::query()->select(['id', 'nome', 'descricao', 'is_system'])->find($profileId);

        if (! $profile) {
            return null;
        }

        $fullAccess = $profile->nome === 'ADMINISTRADOR';

        return [
            'keys' => $fullAccess ? [] : $this->profilePermissionKeys((int) $profile->id),
            'fullAccess' => $fullAccess,
            'locked' => (bool) $profile->is_system,
            'name' => (string) $profile->nome,
            'descricao' => (string) ($profile->descricao ?? ''),
        ];
    }

    /**
     * @param  array{keys?: list<string>, fullAccess?: bool, locked?: bool, name?: string}|null  $state
     */
    protected function applyPermissionHeader(?array $state): void
    {
        if ($state === null) {
            $this->contextTitle = $this->sidebarTab === 'perfis' ? 'Novo perfil' : 'Selecione um usuário';
            $this->permissionFullAccess = false;
            $this->permissionLocked = false;

            return;
        }

        $this->contextTitle = (string) ($state['name'] ?? $this->contextTitle);
        $this->permissionFullAccess = (bool) ($state['fullAccess'] ?? false);
        $this->permissionLocked = (bool) ($state['locked'] ?? false);
    }

    /**
     * Uma consulta para as chaves do usuário e do perfil vinculado.
     *
     * @return list<string>
     */
    protected function grantedKeys(int $userId, ?int $profileId): array
    {
        $query = DB::table('user_permissions')
            ->where('user_id', $userId)
            ->select('permission_key');

        if ($profileId) {
            $query->union(
                DB::table('erp_profile_permissions')
                    ->where('erp_profile_id', $profileId)
                    ->select('permission_key')
            );
        }

        return array_values(array_unique($query->pluck('permission_key')->map(
            static fn ($key): string => (string) $key,
        )->all()));
    }

    /**
     * @return list<string>
     */
    protected function profilePermissionKeys(int $profileId): array
    {
        return DB::table('erp_profile_permissions')
            ->where('erp_profile_id', $profileId)
            ->pluck('permission_key')
            ->map(static fn ($key): string => (string) $key)
            ->all();
    }

    /**
     * @param  list<string>  $keys
     */
    public function savePermissionKeys(array $keys): void
    {
        if ($this->permissionLocked || $this->permissionFullAccess) {
            Notification::make()
                ->title($this->sidebarTab === 'perfis'
                    ? 'Perfil do sistema não pode ser alterado.'
                    : 'Usuário administrador possui acesso total.')
                ->warning()
                ->send();

            return;
        }

        if ($this->sidebarTab === 'perfis') {
            $this->saveProfile($keys);

            return;
        }

        if (! $this->selectedUserId) {
            Notification::make()->title('Selecione um usuário.')->warning()->send();

            return;
        }

        $user = User::query()->select(['id', 'is_admin'])->find($this->selectedUserId);

        if (! $user || $user->is_admin) {
            Notification::make()
                ->title('Usuário administrador possui acesso total.')
                ->warning()
                ->send();

            return;
        }

        ErpAccess::syncUserPermissions($user, $keys);

        Notification::make()->title('Permissões do usuário salvas.')->success()->send();
        $this->closeScreen();
    }

    public function loadProfileTemplate(): void
    {
        if (! $this->selectedUserId) {
            return;
        }

        $user = User::query()->select(['id', 'erp_profile_id'])->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        if (! $this->profileTemplateId) {
            $user->update(['erp_profile_id' => null]);

            if ((int) Auth::id() === (int) $user->getKey()) {
                ErpAccess::storeInSession($user, $this->grantedKeys((int) $user->getKey(), null));
            }

            Notification::make()
                ->title('Perfil desvinculado do usuário.')
                ->body('As permissões avulsas atuais foram mantidas.')
                ->success()
                ->send();

            return;
        }

        $state = $this->captureProfile((int) $this->profileTemplateId);

        if ($state === null) {
            return;
        }

        $user->update(['erp_profile_id' => (int) $this->profileTemplateId]);
        $this->dispatch('erp-permissoes-sync', keys: $state['keys'], fullAccess: $state['fullAccess'], locked: false);

        if ((int) Auth::id() === (int) $user->getKey()) {
            ErpAccess::storeInSession($user, $this->grantedKeys((int) $user->getKey(), (int) $this->profileTemplateId));
        }

        Notification::make()
            ->title('Perfil aplicado ao usuário.')
            ->body('O perfil foi vinculado e suas permissões foram carregadas.')
            ->success()
            ->send();
    }

    /**
     * @param  list<string>  $keys
     */
    protected function saveProfile(array $keys): void
    {
        $this->validate([
            'profileNome' => ['required', 'string', 'max:80'],
            'profileDescricao' => ['nullable', 'string', 'max:255'],
        ], [], [
            'profileNome' => 'nome do perfil',
        ]);

        $nome = mb_strtoupper(trim($this->profileNome), 'UTF-8');

        $profile = $this->selectedProfileId
            ? ErpProfile::query()->find($this->selectedProfileId)
            : null;

        if (! $profile) {
            $profile = ErpProfile::query()->create([
                'nome' => $nome,
                'descricao' => $this->profileDescricao ?: null,
            ]);
            $this->selectedProfileId = $profile->getKey();
        } elseif ($profile->is_system) {
            Notification::make()
                ->title('Perfil do sistema não pode ser alterado.')
                ->warning()
                ->send();

            return;
        } else {
            $profile->update([
                'nome' => $nome,
                'descricao' => $this->profileDescricao ?: null,
            ]);
        }

        ErpAccess::syncProfilePermissions($profile, $keys);
        $this->refreshDirectory();
        $this->contextTitle = $nome;

        Notification::make()
            ->title('Perfil salvo.')
            ->success()
            ->send();
    }
    public function deleteProfile(): void
    {
        if (! $this->selectedProfileId) {
            return;
        }

        $profile = ErpProfile::query()->find($this->selectedProfileId);

        if (! $profile || $profile->is_system) {
            Notification::make()
                ->title('Perfil do sistema não pode ser excluído.')
                ->warning()
                ->send();

            return;
        }

        if (User::query()->where('erp_profile_id', $profile->getKey())->exists()) {
            Notification::make()
                ->title('Perfil está em uso.')
                ->body('Altere o perfil dos usuários vinculados antes de excluí-lo.')
                ->warning()
                ->send();

            return;
        }

        $profile->delete();
        $this->refreshDirectory();
        $this->newProfile();

        Notification::make()
            ->title('Perfil excluído.')
            ->success()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
