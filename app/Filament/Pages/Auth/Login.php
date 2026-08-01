<?php

namespace App\Filament\Pages\Auth;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\ErpAccess;
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
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected Width | string | null $maxWidth = Width::SevenExtraLarge;

    private const REMEMBER_COOKIE = 'erp_login_remember';

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
        if (filament()->auth()->check()) {
            filament()->auth()->logout();

            session()->forget('erp_empresa_id');
            session()->invalidate();
            session()->regenerateToken();
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
        } else {
            session(['erp_empresa_id' => $empresaId]);

            // Conferência de licença só no login (barra "Abrindo o sistema").
            // Depois disso o middleware não chama a API ao abrir telas.
            try {
                $licencas = app(\App\Support\Erp\License\LicencaRemotaService::class);
                $snapshot = $licencas->validateAtLogin();
                if ($licencas->isEnabled() && ! $snapshot->isAllowed()) {
                    $target = \App\Filament\Pages\LicencaBloqueadaPage::getUrl();
                } else {
                    $target = session()->pull('url.intended', filament()->getUrl());
                }
            } catch (\Throwable) {
                $target = session()->pull('url.intended', filament()->getUrl());
            }
        }

        $this->js('window.UnitecLoginBoot && window.UnitecLoginBoot.succeed('.Js::from($target).')');

        return null;
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
