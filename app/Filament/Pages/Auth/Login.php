<?php

namespace App\Filament\Pages\Auth;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use App\Support\Erp\Atualizacao\AtualizacaoPasta;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
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
            $this->redirect(session()->pull('url.intended', filament()->getUrl()));

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
        $state = $this->form->getState();
        $empresaId = (int) ($state['empresa_id'] ?? 0);
        $userId = (int) ($state['user_id'] ?? 0);
        $rememberUser = (bool) ($state['remember_user'] ?? false);
        $primeiroAcesso = ! $this->temEmpresaCadastrada();

        $user = User::query()->find($userId);

        if (! $user || ! $user->ativo) {
            $this->throwFailureValidationException();
        }

        if ($primeiroAcesso) {
            if (! $user->is_admin) {
                throw ValidationException::withMessages([
                    'data.login_senha' => 'Primeiro acesso: use o usuário administrador para cadastrar a empresa.',
                ]);
            }
        } elseif ($empresaId <= 0 || ! $user->canAccessEmpresa($empresaId)) {
            throw ValidationException::withMessages([
                'data.empresa_id' => 'Selecione uma empresa liberada para este usuário.',
            ]);
        }

        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $authUser = Auth::user();

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
            $target = \App\Filament\Resources\EmpresaResource::getUrl('create');
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

        if (! ErpUpdateProcessLauncher::launch(base_path())) {
            $this->applyingUpdate = false;
            $this->updateApplyError = 'Não foi possível iniciar o processo de atualização.';
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

        if ($snapshot->bloquearAtualizacao) {
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
}
