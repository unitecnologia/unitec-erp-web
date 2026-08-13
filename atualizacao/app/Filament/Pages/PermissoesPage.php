<?php

namespace App\Filament\Pages;

use App\Models\CaixaConta;
use App\Models\ErpProfile;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\Erp\EmpresaModulos;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpPermissionCatalog;
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

    public string $sidebarSearch = '';

    /** @var array<string, bool> */
    public array $expandedGroups = [];

    /** @var array<string, bool> */
    public array $expandedModules = [];

    /** @var array<string, bool> */
    public array $expandedMenuGroups = [];

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

    /** @var array<string, bool> */
    public array $checked = [];

    public string $profileNome = '';

    public string $profileDescricao = '';

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('acesso.permissoes.manage');
    }

    public function mount(): void
    {
        ErpScreen::set('Permissões / Usuários');

        if ($this->selectedUserId) {
            $this->loadUserPermissions();
        }

        if ($this->selectedProfileId) {
            $this->selectProfile($this->selectedProfileId);
        }

        if ($this->selectedUserId) {
            $this->loadUserForm();
        }
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

    /**
     * @return array<int, string>
     */
    public function userOptions(): array
    {
        return User::query()
            ->where('ativo', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function profileOptions(): array
    {
        return ErpProfile::query()
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    /**
     * @return list<User>
     */
    public function sidebarUsers(): array
    {
        return User::query()
            ->with('erpProfile')
            ->when(
                filled($this->sidebarSearch),
                fn ($query) => $query->where('name', 'like', '%'.mb_strtoupper(trim($this->sidebarSearch), 'UTF-8').'%'),
            )
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->all();
    }

    /**
     * @return list<ErpProfile>
     */
    public function sidebarProfiles(): array
    {
        return ErpProfile::query()
            ->when(
                filled($this->sidebarSearch),
                fn ($query) => $query->where('nome', 'like', '%'.mb_strtoupper(trim($this->sidebarSearch), 'UTF-8').'%'),
            )
            ->orderBy('nome')
            ->limit(100)
            ->get()
            ->all();
    }

    /**
     * @return array<string, array{label: string, modules: array<string, array{label: string, actions: array<string, string>}>}>
     */
    public function permissionGroups(): array
    {
        $groups = ErpPermissionCatalog::groupedForUi();
        $empresa = ErpContext::currentEmpresa();

        foreach ($groups as $groupKey => $group) {
            foreach (array_keys($group['modules']) as $module) {
                if (! EmpresaModulos::enabled($empresa, $module)) {
                    unset($groups[$groupKey]['modules'][$module]);
                }
            }

            if (($groups[$groupKey]['modules'] ?? []) === []) {
                unset($groups[$groupKey]);
            }
        }

        return $groups;
    }

    public function updatedSelectedUserId(): void
    {
        $this->selectedProfileId = null;
        $this->profileTemplateId = null;
        $this->loadUserPermissions();
    }

    public function setSidebarTab(string $tab): void
    {
        if (in_array($tab, ['usuarios', 'perfis'], true)) {
            $this->sidebarTab = $tab;
            $this->sidebarSearch = '';
        }
    }

    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['cadastro', 'permissoes', 'menu', 'empresas', 'caixas'], true)) {
            $this->activeTab = $tab;

            if ($this->sidebarTab !== 'usuarios') {
                return;
            }

            if ($tab === 'empresas') {
                $this->loadUserEmpresas();
            }

            if ($tab === 'caixas') {
                $this->loadUserCaixas();
            }
        }
    }

    public function selectUser(int $userId): void
    {
        if (! User::query()->whereKey($userId)->exists()) {
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
        $this->loadUserPermissions();
        $this->loadUserForm();
        $this->loadUserEmpresas();
        $this->loadUserCaixas();
    }

    public function selectedUser(): ?User
    {
        return $this->selectedUserId
            ? User::query()->find($this->selectedUserId)
            : null;
    }

    public function selectProfile(int $profileId): void
    {
        $profile = ErpProfile::query()->find($profileId);

        if (! $profile) {
            return;
        }

        $this->sidebarTab = 'perfis';
        $this->selectedUserId = null;
        $this->selectedProfileId = $profile->getKey();
        $this->profileNome = $profile->nome;
        $this->profileDescricao = (string) ($profile->descricao ?? '');
        $this->checked = [];
        $this->userEmpresaIds = [];
        $this->userCaixaIds = [];
        $this->userCaixaPadraoId = null;
        $this->caixaEmpresaId = null;

        if (in_array($this->activeTab, ['empresas', 'caixas'], true)) {
            $this->activeTab = 'permissoes';
        }

        foreach ($profile->permissionKeys() as $key) {
            $this->checked[$key] = true;
        }
    }

    public function newProfile(): void
    {
        $this->sidebarTab = 'perfis';
        $this->selectedUserId = null;
        $this->selectedProfileId = null;
        $this->profileNome = '';
        $this->profileDescricao = '';
        $this->checked = [];
        $this->activeTab = 'cadastro';
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
            'password_confirmation' => '',
            'empresa_id' => $empresaId,
            'erp_profile_id' => '',
            'is_admin' => 'N',
            'ativo' => 'S',
        ];
        $this->userEmpresaIds = filled($empresaId) ? [(int) $empresaId] : [];
        $this->selectedBlockedEmpresaId = null;
        $this->selectedLiberatedEmpresaId = null;
        $this->checked = [];
        $this->activeTab = 'cadastro';
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
            'password_confirmation' => '',
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
        ];

        if ($isCreate) {
            $rules['userForm.password'] = ['required', 'string', 'min:2', 'max:60'];
            $rules['userForm.password_confirmation'] = ['required', 'same:userForm.password'];
        } elseif (filled($this->userForm['password'] ?? null)) {
            $rules['userForm.password'] = ['string', 'min:2', 'max:60'];
            $rules['userForm.password_confirmation'] = ['required', 'same:userForm.password'];
        }

        $this->validate($rules);

        // vendedor_id não é editável aqui — único escritor: RH → Funcionários (aba Operador).
        $data = [
            'name' => mb_strtoupper(trim((string) $this->userForm['name']), 'UTF-8'),
            'empresa_id' => (int) $this->userForm['empresa_id'],
            'erp_profile_id' => filled($this->userForm['erp_profile_id'] ?? null) ? (int) $this->userForm['erp_profile_id'] : null,
            'is_admin' => ($this->userForm['is_admin'] ?? 'N') === 'S',
            'ativo' => ($this->userForm['ativo'] ?? 'S') === 'S',
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
        $this->loadUserForm();
        $this->loadUserEmpresas();
        $this->loadUserPermissions();
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

    public function toggleGroup(string $group): void
    {
        $this->expandedGroups[$group] = ! ($this->expandedGroups[$group] ?? true);
    }

    public function toggleModule(string $module): void
    {
        $this->expandedModules[$module] = ! ($this->expandedModules[$module] ?? false);
    }

    public function isGroupExpanded(string $group): bool
    {
        return $this->expandedGroups[$group] ?? true;
    }

    public function isModuleExpanded(string $module): bool
    {
        return $this->expandedModules[$module] ?? false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function menuAccessItems(): array
    {
        return \App\Support\Erp\ErpMenu::allMenus();
    }

    public function menuItemAllowed(?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        if ($this->isAdministratorProfileSelected()) {
            return true;
        }

        return (bool) ($this->checked[$permission] ?? false);
    }

    public function setMenuItemAllowed(string $permission, bool $allowed): void
    {
        $this->checked[$permission] = $allowed;
    }

    public function markMenuGroupItems(string $group, bool $allowed): void
    {
        $menu = collect($this->menuAccessItems())
            ->first(fn (array $item): bool => $item['label'] === $group);

        if (! $menu) {
            return;
        }

        foreach ($this->menuPermissionsFromItems($menu['items'] ?? []) as $permission) {
            $this->checked[$permission] = $allowed;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string>
     */
    private function menuPermissionsFromItems(array $items): array
    {
        $permissions = [];

        foreach ($items as $item) {
            if (isset($item['permission'])) {
                $permissions[] = $item['permission'];
            }

            if (isset($item['items']) && is_array($item['items'])) {
                $permissions = [...$permissions, ...$this->menuPermissionsFromItems($item['items'])];
            }
        }

        return array_values(array_unique($permissions));
    }

    public function setPermissionAllowed(string $permission, bool $allowed): void
    {
        if (! in_array($permission, ErpPermissionCatalog::allKeys(), true)) {
            return;
        }

        $this->checked[$permission] = $allowed;
    }

    public function isAdministratorProfileSelected(): bool
    {
        if ($this->sidebarTab !== 'perfis' || ! $this->selectedProfileId) {
            return false;
        }

        return ErpProfile::query()
            ->whereKey($this->selectedProfileId)
            ->where('nome', 'ADMINISTRADOR')
            ->exists();
    }

    public function toggleMenuGroup(string $group): void
    {
        $this->expandedMenuGroups[$group] = ! ($this->expandedMenuGroups[$group] ?? false);
    }

    public function isMenuGroupExpanded(string $group): bool
    {
        return $this->expandedMenuGroups[$group] ?? false;
    }

    public function loadUserPermissions(): void
    {
        $this->checked = [];

        if (! $this->selectedUserId) {
            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        if ($user->is_admin) {
            foreach (ErpPermissionCatalog::allKeys() as $key) {
                $this->checked[$key] = true;
            }

            return;
        }

        foreach ($user->effectivePermissionKeys() as $key) {
            $this->checked[$key] = true;
        }
    }

    public function loadProfileTemplate(): void
    {
        if (! $this->selectedUserId) {
            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        if (! $this->profileTemplateId) {
            $user->update(['erp_profile_id' => null]);
            $freshUser = $user->fresh();
            ErpAccess::storeInSession($freshUser, $freshUser->effectivePermissionKeys());

            Notification::make()
                ->title('Perfil desvinculado do usuário.')
                ->body('As permissões avulsas atuais foram mantidas.')
                ->success()
                ->send();

            return;
        }

        $profile = ErpProfile::query()->find($this->profileTemplateId);

        if (! $profile) {
            return;
        }

        $this->checked = [];

        foreach ($profile->permissionKeys() as $key) {
            $this->checked[$key] = true;
        }

        $user->update(['erp_profile_id' => $profile->getKey()]);
        $freshUser = $user->fresh();
        ErpAccess::storeInSession($freshUser, $freshUser->effectivePermissionKeys());

        Notification::make()
            ->title('Perfil aplicado ao usuário.')
            ->body('O perfil foi vinculado e suas permissões foram carregadas.')
            ->success()
            ->send();
    }

    public function markGroup(string $group, bool $value): void
    {
        foreach ($this->permissionGroups()[$group]['modules'] ?? [] as $module => $meta) {
            foreach (array_keys($meta['actions']) as $action) {
                $this->checked[ErpPermissionCatalog::key($module, $action)] = $value;
            }
        }
    }

    public function markModule(string $module, bool $value): void
    {
        $meta = ErpPermissionCatalog::modules()[$module] ?? null;

        if (! $meta || ! EmpresaModulos::enabled(ErpContext::currentEmpresa(), $module)) {
            return;
        }

        foreach (array_keys($meta['actions']) as $action) {
            $this->checked[ErpPermissionCatalog::key($module, $action)] = $value;
        }
    }

    public function savePermissions(): void
    {
        if ($this->sidebarTab === 'perfis') {
            $this->saveProfile();

            return;
        }

        if (! $this->selectedUserId) {
            Notification::make()
                ->title('Selecione um usuário.')
                ->warning()
                ->send();

            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (! $user) {
            return;
        }

        if ($user->is_admin) {
            Notification::make()
                ->title('Usuário administrador possui acesso total.')
                ->body('Não é necessário salvar permissões individuais para este usuário.')
                ->warning()
                ->send();

            return;
        }

        $keys = array_keys(array_filter($this->checked));
        ErpAccess::syncUserPermissions($user, $keys);

        Notification::make()
            ->title('Permissões do usuário salvas.')
            ->success()
            ->send();

        $this->closeScreen();
    }

    protected function saveProfile(): void
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
        } else {
            if ($profile->is_system) {
                Notification::make()
                    ->title('Perfil do sistema não pode ser alterado.')
                    ->warning()
                    ->send();

                return;
            }

            $profile->update([
                'nome' => $nome,
                'descricao' => $this->profileDescricao ?: null,
            ]);
        }

        $keys = array_keys(array_filter($this->checked));
        ErpAccess::syncProfilePermissions($profile, $keys);

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
