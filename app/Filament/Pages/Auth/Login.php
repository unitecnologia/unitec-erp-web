<?php

namespace App\Filament\Pages\Auth;

use App\Models\Empresa;
use App\Models\User;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\CnpjLookupService;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\Atualizacao\AtualizacaoLog;
use App\Support\Erp\Atualizacao\AtualizacaoPasta;
use App\Support\Erp\Hotfix\HotfixLauncher;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use App\Support\Erp\RequestOrigin;
use App\Support\Gestor\GestorLoginDiag;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Facades\Filament;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected Width | string | null $maxWidth = Width::SevenExtraLarge;

    private const REMEMBER_COOKIE = 'erp_login_remember';

    public bool $showUpdatePrompt = false;

    public ?string $pendingUpdateVersion = null;

    public bool $applyingUpdate = false;

    public ?string $updateApplyError = null;

    public ?string $schemaMigrateError = null;

    public ?string $schemaMigrateOk = null;

    #[Locked]
    public bool $configuracaoInicial = false;

    public string $inicialRazao = '';

    public string $inicialFantasia = '';

    public string $inicialCnpj = '';

    public string $inicialIe = '';

    public string $inicialCep = '';

    public string $inicialEndereco = '';

    public string $inicialNumero = '';

    public string $inicialComplemento = '';

    public string $inicialBairro = '';

    public string $inicialCidade = '';

    public string $inicialUf = '';

    public string $inicialTelefone = '';

    public string $inicialEmail = '';

    #[Locked]
    public string $inicialCidadeCodigo = '';

    public ?string $inicialCepAviso = null;

    public ?string $inicialCnpjAviso = null;

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function mount(): void
    {
        $this->schemaMigrateError = session()->pull('erp_migrate_error')
            ?: (\Illuminate\Support\Facades\Cache::pull('erp.schema.migrate_error') ?: null);
        $this->schemaMigrateOk = session()->pull('erp_migrate_ok');

        $this->configuracaoInicial = Empresa::configuracaoInicialPendente();
        if ($this->configuracaoInicial) {
            return;
        }

        // Já autenticado aguardando Sim/Não da atualização.
        if (filament()->auth()->check() && session('erp_awaiting_update_choice')) {
            $snapshot = $this->snapshotForAtualizacaoGate();
            if ($this->shouldSkipAtualizacaoPrompt($snapshot)) {
                $this->clearUpdatePromptSession();
                $this->finishLoginAfterUpdateChoice($snapshot);

                return;
            }

            $this->showUpdatePrompt = true;
            $this->pendingUpdateVersion = (string) session('erp_pending_update_version', '');

            return;
        }

        // Já autenticado: vai para o painel. NÃO fazer logout aqui —
        // o login usa JS (UnitecLoginBoot.succeed) e ainda fica um instante nesta
        // página; logout no remount cancelava a sessão e devolvia à tela de login.
        if (filament()->auth()->check()) {
            $this->redirect(RequestOrigin::toBrowserUrl(session()->pull('url.intended', filament()->getUrl())));

            return;
        }

        $this->form->fill($this->getDefaultFormState());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultFormState(): array
    {
        try {
            $remembered = $this->readRememberedLogin();

            $empresaId = (int) ($remembered['empresa_id'] ?? 0);
            $userId = (int) ($remembered['user_id'] ?? 0);
            $remember = $remembered !== null;

            if ($empresaId <= 0 || ! Empresa::query()->whereKey($empresaId)->where('ativo', true)->exists()) {
                $empresaId = (int) (Empresa::query()->where('ativo', true)->orderBy('nome')->value('id') ?? 0);
            }

            if ($userId > 0) {
                $user = User::query()->find($userId);
                if (! $user || ! $user->ativo || ($empresaId > 0 && ! $user->canAccessEmpresa($empresaId))) {
                    $userId = 0;
                }
            }

            return [
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
                'user_id' => $userId > 0 ? $userId : null,
                'login_senha' => null,
                'remember_user' => $remember,
            ];
        } catch (\Throwable) {
            // Banco inacessível / tabelas ausentes: não derrubar a tela de login com 500.
            return [
                'empresa_id' => null,
                'user_id' => null,
                'login_senha' => null,
                'remember_user' => false,
            ];
        }
    }

    public function getEmpresaLogoUrl(): ?string
    {
        $empresaId = (int) ($this->data['empresa_id'] ?? 0);

        if ($empresaId <= 0) {
            return null;
        }

        return Empresa::query()->find($empresaId)?->logoUrl();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->extraAttributes([
                'autocomplete' => 'off',
                'data-lpignore' => 'true',
                'data-1p-ignore' => 'true',
                'data-bwignore' => 'true',
            ])
            ->components([
                $this->getEmpresaFormComponent(),
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberUserFormComponent(),
            ]);
    }

    protected function getEmpresaFormComponent(): Component
    {
        return Select::make('empresa_id')
            ->label('EMPRESA')
            ->options(fn (): array => $this->empresaOptions())
            ->live()
            ->afterStateUpdated(function (): void {
                $empresaId = (int) ($this->data['empresa_id'] ?? 0);
                $userId = (int) ($this->data['user_id'] ?? 0);
                $users = $this->userOptionsForEmpresa($empresaId);

                if ($userId > 0 && ! array_key_exists($userId, $users)) {
                    $this->data['user_id'] = null;
                }
            })
            ->visible(fn (): bool => $this->temEmpresaCadastrada())
            ->selectablePlaceholder(false)
            ->required(fn (): bool => $this->temEmpresaCadastrada())
            ->native()
            ->extraInputAttributes([
                'tabindex' => 1,
                'autocomplete' => 'off',
                'data-lpignore' => 'true',
                'data-1p-ignore' => 'true',
                'data-bwignore' => 'true',
                'data-form-type' => 'other',
            ]);
    }

    protected function getLoginFormComponent(): Component
    {
        $userCount = User::query()->where('ativo', true)->count();

        // Select nativo com poucos usuários: mais confiável após update
        // (searchable do Filament às vezes mostra "sem opções" mesmo com dados).
        if ($userCount > 0 && $userCount <= 100) {
            return Select::make('user_id')
                ->label('USUÁRIO')
                ->placeholder('SELECIONE O USUÁRIO')
                ->options(fn (): array => $this->userOptionsForEmpresa((int) ($this->data['empresa_id'] ?? 0)))
                ->native()
                ->live()
                ->selectablePlaceholder(true)
                ->required()
                ->extraInputAttributes([
                    'tabindex' => 2,
                    'autocomplete' => 'off',
                    'autocapitalize' => 'characters',
                    'style' => 'text-transform: uppercase;',
                    'data-lpignore' => 'true',
                    'data-1p-ignore' => 'true',
                    'data-bwignore' => 'true',
                    'data-form-type' => 'other',
                ]);
        }

        return Select::make('user_id')
            ->label('USUÁRIO')
            ->placeholder('DIGITE O NOME DO USUÁRIO')
            ->options(fn (): array => $this->userOptionsForEmpresa((int) ($this->data['empresa_id'] ?? 0)))
            ->searchable()
            ->native(false)
            ->live()
            ->selectablePlaceholder(true)
            ->required()
            ->noOptionsMessage('Nenhum usuário cadastrado.')
            ->noSearchResultsMessage('Nenhum usuário encontrado.')
            ->extraInputAttributes([
                'tabindex' => 2,
                'autocomplete' => 'off',
                'autocapitalize' => 'characters',
                'style' => 'text-transform: uppercase;',
                'data-lpignore' => 'true',
                'data-1p-ignore' => 'true',
                'data-bwignore' => 'true',
                'data-form-type' => 'other',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        // type=text + máscara CSS: evita o popup "senha forte" do Chrome (type=password dispara).
        return TextInput::make('login_senha')
            ->label('SENHA')
            ->type('text')
            ->autocomplete('off')
            ->required()
            ->extraFieldWrapperAttributes([
                'class' => 'unitec-login__senha-wrap',
            ])
            ->extraInputAttributes([
                'id' => 'unitec-login-senha',
                'tabindex' => 3,
                'autocomplete' => 'off',
                'autocapitalize' => 'off',
                'autocorrect' => 'off',
                'spellcheck' => 'false',
                'inputmode' => 'text',
                'data-lpignore' => 'true',
                'data-1p-ignore' => 'true',
                'data-bwignore' => 'true',
                'data-form-type' => 'other',
            ]);
    }

    protected function getRememberUserFormComponent(): Component
    {
        return Checkbox::make('remember_user')
            ->label('Lembrar usuário')
            ->extraInputAttributes([
                'tabindex' => 4,
            ]);
    }

    protected function temEmpresaCadastrada(): bool
    {
        return Empresa::query()->exists();
    }

    /**
     * @return array<int|string, string>
     */
    protected function empresaOptions(): array
    {
        return Empresa::query()
            ->where('ativo', true)
            ->orderByRaw('COALESCE(NULLIF(fantasia, ""), NULLIF(nome, ""), razao_social) ASC')
            ->get(['id', 'nome', 'fantasia', 'razao_social'])
            ->mapWithKeys(fn (Empresa $e): array => [
                $e->id => (string) ($e->fantasia ?: ($e->nome ?: $e->razao_social)),
            ])
            ->all();
    }

    /**
     * Usuários ativos com acesso à empresa (digite o nome para filtrar na lista).
     *
     * @return array<int|string, string>
     */
    protected function userOptionsForEmpresa(int $empresaId): array
    {
        $query = User::query()
            ->where('ativo', true)
            ->orderBy('name');

        if ($empresaId > 0) {
            $query->where(function ($q) use ($empresaId): void {
                $q->where('is_admin', true)
                    ->orWhere('empresa_id', $empresaId)
                    ->orWhereHas('empresas', fn ($eq) => $eq->where('empresas.id', $empresaId));
            });
        }

        return $query->pluck('name', 'id')->all();
    }

    public function authenticate(): ?LoginResponse
    {
        if (Empresa::configuracaoInicialPendente()) {
            $this->configuracaoInicial = true;

            return null;
        }

        $state = $this->form->getState();
        $empresaId = (int) ($state['empresa_id'] ?? 0);
        $userId = (int) ($state['user_id'] ?? 0);
        $rememberUser = (bool) ($state['remember_user'] ?? false);
        $primeiroAcesso = ! $this->temEmpresaCadastrada();

        $user = User::query()->find($userId);

        if (! $user || ! $user->ativo) {
            GestorLoginDiag::log('recusado', [
                'reason' => ! $user ? 'user_not_found' : 'inactive',
                'user_id' => $userId > 0 ? $userId : null,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
            ]);
            $this->throwFailureValidationException();
        }

        if ($primeiroAcesso) {
            if (! $user->is_admin) {
                GestorLoginDiag::log('recusado', [
                    'reason' => 'primeiro_acesso_nao_admin',
                    'user_id' => $userId,
                    'empresa_id' => $empresaId > 0 ? $empresaId : null,
                ]);
                throw ValidationException::withMessages([
                    'data.login_senha' => 'Primeiro acesso: use o usuário administrador para cadastrar a empresa.',
                ]);
            }
        } elseif ($empresaId <= 0 || ! $user->canAccessEmpresa($empresaId)) {
            GestorLoginDiag::log('recusado', [
                'reason' => 'empresa',
                'user_id' => $userId,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
            ]);
            throw ValidationException::withMessages([
                'data.empresa_id' => 'Selecione uma empresa liberada para este usuário.',
            ]);
        }

        try {
            $response = parent::authenticate();
        } catch (ValidationException $e) {
            GestorLoginDiag::log('recusado', [
                'reason' => 'credentials',
                'user_id' => $userId,
                'empresa_id' => $empresaId > 0 ? $empresaId : null,
            ]);

            throw $e;
        }

        if ($response === null) {
            return null;
        }

        $authUser = Auth::user();

        GestorLoginDiag::log('aceito', [
            'user_id' => $authUser instanceof User ? $authUser->id : $userId,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'primeiro_acesso' => $primeiroAcesso,
        ]);

        if ($authUser instanceof User) {
            ErpAccess::storeInSession($authUser, $authUser->effectivePermissionKeys());
        }

        if ($rememberUser) {
            $this->writeRememberedLogin($userId, $empresaId);
        } else {
            $this->forgetRememberedLogin();
        }

        if ($primeiroAcesso) {
            session()->forget('erp_empresa_id');
            $target = RequestOrigin::toBrowserUrl(\App\Filament\Resources\EmpresaResource::getUrl('create'));
            $this->js('window.UnitecLoginBoot && window.UnitecLoginBoot.succeed('.Js::from($target).')');

            return null;
        }

        session(['erp_empresa_id' => $empresaId]);
        \App\Support\Erp\ErpContext::clearMemo();

        // Atualização pronta: pergunta Sim/Não, salvo se o portal bloquear a instalação.
        if (AtualizacaoPasta::ensurePendingReady()) {
            $snapshot = $this->snapshotForAtualizacaoGate();
            if ($this->shouldSkipAtualizacaoPrompt($snapshot)) {
                $this->finishLoginAfterUpdateChoice($snapshot);

                return null;
            }

            $version = AtualizacaoPasta::pendingVersion() ?? '';
            session([
                'erp_awaiting_update_choice' => true,
                'erp_pending_update_version' => $version,
            ]);
            $this->showUpdatePrompt = true;
            $this->pendingUpdateVersion = $version;
            $this->js('window.UnitecLoginBoot && window.UnitecLoginBoot.hide && window.UnitecLoginBoot.hide()');

            return null;
        }

        $this->finishLoginAfterUpdateChoice();

        return null;
    }

    public function aceitarAtualizacao(): void
    {
        if (! filament()->auth()->check()) {
            return;
        }

        $this->applyingUpdate = true;
        $this->updateApplyError = null;
        AtualizacaoApplyService::initializeProgress(base_path());

        $usuario = Auth::user();
        $nome = $usuario instanceof User ? trim((string) $usuario->name) : '';
        $pacote = trim((string) ($this->pendingUpdateVersion ?? AtualizacaoPasta::pendingVersion() ?? ''));
        AtualizacaoLog::line(
            'Sim',
            'usuario='.($nome !== '' ? $nome : 'desconhecido')
            .' instalada='.(string) config('unitec.versao', '')
            .' pacote='.($pacote !== '' ? $pacote : 'desconhecida'),
        );

        if (! ErpUpdateProcessLauncher::launch(base_path())) {
            $this->applyingUpdate = false;
            $this->updateApplyError = 'Não foi possível iniciar o processo de atualização.';
            AtualizacaoLog::line('Disparo', $this->updateApplyError);
            throw ValidationException::withMessages([
                'data.login_senha' => $this->updateApplyError,
            ]);
        }
    }

    public function finalizarAtualizacaoAplicada(): void
    {
        if (! filament()->auth()->check()) {
            return;
        }

        $progress = AtualizacaoApplyService::readProgress(base_path());
        if (($progress['state'] ?? '') !== 'completed') {
            return;
        }

        $this->clearUpdatePromptSession();
        $this->showUpdatePrompt = false;
        $this->applyingUpdate = false;
        $this->updateApplyError = null;
        $this->finishLoginAfterUpdateChoice();
    }

    public function falharAtualizacaoAplicada(string $message = ''): void
    {
        $this->applyingUpdate = false;
        $this->updateApplyError = trim($message) !== ''
            ? trim($message)
            : 'Não foi possível aplicar a atualização.';

        Log::error('Falha ao aplicar atualizacao/', ['message' => $this->updateApplyError]);
        AtualizacaoLog::line('Tela', $this->updateApplyError);
        $this->addError('data.login_senha', $this->updateApplyError);
    }

    public function recusarAtualizacao(): void
    {
        $this->clearUpdatePromptSession();
        $this->showUpdatePrompt = false;
        $this->finishLoginAfterUpdateChoice();
    }

    private function clearUpdatePromptSession(): void
    {
        session()->forget(['erp_awaiting_update_choice', 'erp_pending_update_version']);
    }

    private function snapshotForAtualizacaoGate(): ?LicencaSnapshot
    {
        try {
            return app(LicencaRemotaService::class)->validateAtLogin();
        } catch (\Throwable $e) {
            Log::warning('Falha ao consultar licença para o gate de atualização.', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function shouldSkipAtualizacaoPrompt(?LicencaSnapshot $snapshot): bool
    {
        if ($snapshot === null) {
            return false;
        }

        if (! $snapshot->permiteAtualizacaoOficial()) {
            return true;
        }

        $licencas = app(LicencaRemotaService::class);

        return $licencas->isEnabled() && ! $snapshot->isAllowed();
    }

    private function finishLoginAfterUpdateChoice(?LicencaSnapshot $snapshot = null): void
    {
        $target = filament()->getUrl();

        try {
            $licencas = app(LicencaRemotaService::class);
            $snapshot ??= $licencas->validateAtLogin();
            if ($licencas->isEnabled() && ! $snapshot->isAllowed()) {
                $target = \App\Filament\Pages\LicencaBloqueadaPage::getUrl();
            } else {
                $target = session()->pull('url.intended', filament()->getUrl());
            }

            HotfixLauncher::aposLogin($licencas->currentCnpj(), $snapshot);
        } catch (\Throwable $e) {
            Log::warning('Falha ao validar licença no login.', [
                'message' => $e->getMessage(),
            ]);

            $licencas = app(LicencaRemotaService::class);
            $licencas->hydrateMensalidadeFromCache();

            if ($licencas->isEnabled() && ($licencas->mensalidadeVencida() || $licencas->loginGateIsAllowed() === false)) {
                $target = \App\Filament\Pages\LicencaBloqueadaPage::getUrl();
            } else {
                $target = session()->pull('url.intended', filament()->getUrl());
            }
        }

        GestorLoginDiag::log('redirect', [
            'user_id' => Auth::id(),
            'empresa_id' => session('erp_empresa_id'),
            'target_url' => $target,
        ]);

        $target = RequestOrigin::toBrowserUrl((string) $target);
        $this->js('window.UnitecLoginBoot && window.UnitecLoginBoot.succeed('.Js::from($target).')');
    }

    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
            $this->getCancelFormAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Confirma (Enter)')
            ->submit('authenticate');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancelar')
            ->color('gray')
            ->outlined()
            ->extraAttributes(['class' => 'unitec-login__btn-cancel'])
            ->action(fn (): mixed => $this->cancel());
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }

    public function getFormActionsAlignment(): string | Alignment
    {
        return Alignment::Start;
    }

    public function cancel(): void
    {
        $this->form->fill($this->getDefaultFormState());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        $user = User::query()->find($data['user_id'] ?? null);

        return [
            'name' => $user?->name,
            'password' => $data['login_senha'] ?? $data['password'] ?? '',
        ];
    }

    protected function throwFailureValidationException(): never
    {
        // Motivo específico (inactive/credentials/…) já logado pelo caller quando aplicável.
        throw ValidationException::withMessages([
            'data.login_senha' => 'Usuário ou senha inválidos.',
        ]);
    }

    /**
     * @return array{user_id: int, empresa_id: int}|null
     */
    protected function readRememberedLogin(): ?array
    {
        $raw = Cookie::get(self::REMEMBER_COOKIE);

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $empresaId = (int) ($data['empresa_id'] ?? 0);

        if ($userId <= 0) {
            return null;
        }

        return [
            'user_id' => $userId,
            'empresa_id' => $empresaId,
        ];
    }

    protected function writeRememberedLogin(int $userId, int $empresaId): void
    {
        Cookie::queue(
            self::REMEMBER_COOKIE,
            json_encode([
                'user_id' => $userId,
                'empresa_id' => $empresaId,
            ], JSON_THROW_ON_ERROR),
            60 * 24 * 90,
        );
    }

    protected function forgetRememberedLogin(): void
    {
        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));
    }

    public function updatedInicialCep(): void
    {
        $this->inicialCepAviso = null;

        if (! Empresa::configuracaoInicialPendente()) {
            return;
        }

        $digits = preg_replace('/\D/', '', $this->inicialCep) ?? '';
        if (strlen($digits) !== 8) {
            return;
        }

        $this->buscarCepInicial();
    }

    public function buscarCepInicial(): void
    {
        if (! Empresa::configuracaoInicialPendente()) {
            return;
        }

        try {
            $result = app(CepLookupService::class)->lookup($this->inicialCep);
        } catch (\Throwable $exception) {
            $this->inicialCepAviso = $exception->getMessage();

            return;
        }

        $this->inicialCep = $result['cep'];
        $this->inicialEndereco = $result['endereco'];
        $this->inicialBairro = $result['bairro'];
        $this->inicialCidade = $result['cidade_nome'];
        $this->inicialUf = $result['uf'];
        $this->inicialCidadeCodigo = $result['cidade_codigo'];
        $this->inicialCepAviso = null;
    }

    public function updatedInicialCnpj(): void
    {
        $this->inicialCnpjAviso = null;

        if (! Empresa::configuracaoInicialPendente()) {
            return;
        }

        $digits = preg_replace('/\D/', '', $this->inicialCnpj) ?? '';
        if (strlen($digits) !== 14) {
            return;
        }

        $this->buscarCnpjInicial();
    }

    public function buscarCnpjInicial(): void
    {
        if (! Empresa::configuracaoInicialPendente()) {
            return;
        }

        $digits = preg_replace('/\D/', '', $this->inicialCnpj) ?? '';

        try {
            $fields = app(CnpjLookupService::class)->fetch($digits);
        } catch (\Throwable $exception) {
            $this->inicialCnpjAviso = $exception->getMessage();

            return;
        }

        $this->aplicarCamposCnpj($fields);

        $razao = trim($this->inicialRazao);
        if (trim($this->inicialFantasia) === '' && $razao !== '') {
            $this->inicialFantasia = $razao;
        }

        $cepDigits = preg_replace('/\D/', '', $this->inicialCep) ?? '';
        if (trim($this->inicialEndereco) === '' && strlen($cepDigits) === 8) {
            $this->buscarCepInicial();
        }

        $this->inicialCnpjAviso = null;
    }

    /**
     * Mesmos campos do cadastro da empresa (ManagesEmpresaLookup).
     *
     * @param  array<string, string|null>  $fields
     */
    protected function aplicarCamposCnpj(array $fields): void
    {
        $assign = function (string $property, ?string $value): void {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }

            $this->{$property} = $value;
        };

        $assign('inicialCnpj', $fields['cpf_cnpj'] ?? $this->inicialCnpj);
        $assign('inicialRazao', $fields['nome_razao'] ?? null);
        $assign('inicialFantasia', $fields['apelido_fantasia'] ?? null);
        $ie = trim((string) ($fields['rg_ie'] ?? ''));
        if ($ie !== '') {
            $this->inicialIe = mb_substr($ie, 0, 20, 'UTF-8');
        }
        $assign('inicialCep', $fields['cep'] ?? null);
        $assign('inicialEndereco', $fields['endereco'] ?? null);
        $assign('inicialNumero', $fields['numero'] ?? null);
        $assign('inicialComplemento', $fields['complemento'] ?? null);
        $assign('inicialBairro', $fields['bairro'] ?? null);
        $assign('inicialCidade', $fields['cidade_nome'] ?? null);
        $assign('inicialEmail', $fields['email'] ?? null);

        $telefone = preg_replace('/\D/', '', (string) ($fields['fone1'] ?? '')) ?? '';
        if ($telefone !== '') {
            $this->inicialTelefone = substr($telefone, 0, 20);
        }

        $uf = mb_strtoupper(trim((string) ($fields['uf'] ?? '')), 'UTF-8');
        if ($uf !== '' && array_key_exists($uf, Empresa::ufs())) {
            $this->inicialUf = $uf;
        }

        $codigo = preg_replace('/\D/', '', (string) ($fields['cidade_codigo'] ?? '')) ?? '';
        if (CepLookupService::isValidIbgeCode($codigo)) {
            $this->inicialCidadeCodigo = $codigo;
        }
    }

    public function salvarConfiguracaoInicial(): void
    {
        if (! Empresa::configuracaoInicialPendente()) {
            $this->redirect($this->urlLoginErp());

            return;
        }

        $this->inicialCnpj = preg_replace('/\D/', '', $this->inicialCnpj) ?? '';
        $this->inicialCep = preg_replace('/\D/', '', $this->inicialCep) ?? '';
        $this->inicialTelefone = preg_replace('/\D/', '', $this->inicialTelefone) ?? '';

        $this->validate(
            [
                'inicialRazao' => ['required', 'string', 'max:255'],
                'inicialFantasia' => ['required', 'string', 'max:255'],
                'inicialCnpj' => ['required', 'string', 'max:20', new DocumentoBrasileiroValido(cnpjOnly: true)],
                'inicialIe' => ['nullable', 'string', 'max:20'],
                'inicialCep' => ['required', 'string', 'size:8'],
                'inicialEndereco' => ['required', 'string', 'max:255'],
                'inicialNumero' => ['required', 'string', 'max:20'],
                'inicialComplemento' => ['nullable', 'string', 'max:255'],
                'inicialBairro' => ['required', 'string', 'max:255'],
                'inicialCidade' => ['required', 'string', 'max:255'],
                'inicialUf' => ['required', 'string', 'size:2', Rule::in(array_keys(Empresa::ufs()))],
                'inicialTelefone' => ['required', 'string', 'max:20'],
                'inicialEmail' => ['required', 'string', 'max:255', 'email'],
            ],
            [
                'inicialRazao.required' => 'Informe a razão social / nome.',
                'inicialFantasia.required' => 'Informe o nome fantasia / apelido.',
                'inicialCnpj.required' => 'Informe o CNPJ da empresa.',
                'inicialCep.required' => 'Informe um CEP completo com 8 dígitos.',
                'inicialCep.size' => 'Informe um CEP completo com 8 dígitos.',
                'inicialEndereco.required' => 'Informe o endereço.',
                'inicialNumero.required' => 'Informe o número.',
                'inicialBairro.required' => 'Informe o bairro.',
                'inicialCidade.required' => 'Informe a cidade.',
                'inicialUf.required' => 'Informe a UF.',
                'inicialTelefone.required' => 'Informe o telefone.',
                'inicialEmail.required' => 'Informe o e-mail.',
                'inicialEmail.email' => 'Informe um e-mail válido.',
            ],
            [
                'inicialRazao' => 'Razão social',
                'inicialFantasia' => 'Nome fantasia',
                'inicialCnpj' => 'CNPJ',
                'inicialIe' => 'Inscrição estadual',
                'inicialCep' => 'CEP',
                'inicialEndereco' => 'Endereço',
                'inicialNumero' => 'Número',
                'inicialComplemento' => 'Complemento',
                'inicialBairro' => 'Bairro',
                'inicialCidade' => 'Cidade',
                'inicialUf' => 'UF',
                'inicialTelefone' => 'Telefone',
                'inicialEmail' => 'E-mail',
            ],
        );

        $payload = ErpUppercase::normalizeFormData([
            'razao_social' => trim($this->inicialRazao),
            'fantasia' => trim($this->inicialFantasia),
            'ie' => trim($this->inicialIe),
            'endereco' => trim($this->inicialEndereco),
            'numero' => trim($this->inicialNumero),
            'complemento' => trim($this->inicialComplemento),
            'bairro' => trim($this->inicialBairro),
            'cidade' => trim($this->inicialCidade),
            'uf' => trim($this->inicialUf),
            'email' => trim($this->inicialEmail),
        ]);

        $payload['cnpj'] = $this->inicialCnpj;
        $payload['cep'] = $this->inicialCep;
        $payload['telefone'] = $this->inicialTelefone;
        $payload['nome'] = $payload['fantasia'] !== '' ? $payload['fantasia'] : $payload['razao_social'];
        $payload['ie'] = $payload['ie'] !== '' ? $payload['ie'] : null;
        $payload['complemento'] = $payload['complemento'] !== '' ? $payload['complemento'] : null;
        $payload['cidade_codigo'] = CepLookupService::isValidIbgeCode($this->inicialCidadeCodigo)
            ? $this->inicialCidadeCodigo
            : null;
        $payload['configuracao_inicial_concluida'] = true;

        $updated = Empresa::query()
            ->whereKey(1)
            ->where('configuracao_inicial_concluida', false)
            ->update($payload);

        if ($updated !== 1) {
            $this->redirect($this->urlLoginErp());

            return;
        }

        $this->configuracaoInicial = false;
        $this->redirect($this->urlLoginErp());
    }

    protected function urlLoginErp(): string
    {
        return RequestOrigin::toBrowserUrl(Filament::getPanel('admin')->getLoginUrl());
    }
}
