<?php

namespace App\Filament\Pages\Auth;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Actions\Action;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected Width | string | null $maxWidth = Width::SevenExtraLarge;

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
        return [
            'empresa_id' => 1,
            'user_id' => User::query()->where('name', 'USUARIO')->value('id')
                ?? User::query()->orderBy('name')->value('id'),
            'password' => null,
        ];
    }

    public function getEmpresaLogoUrl(): ?string
    {
        $empresaId = $this->data['empresa_id'] ?? 1;

        return Empresa::query()->find($empresaId)?->logoUrl();
    }

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                $this->getEmpresaFormComponent(),
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }

    protected function getEmpresaFormComponent(): Component
    {
        return Select::make('empresa_id')
            ->label('EMPRESA')
            ->options(fn (): array => Empresa::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->pluck('nome', 'id')
                ->all())
            ->default(1)
            ->live()
            ->selectablePlaceholder(false)
            ->required()
            ->native(false);
    }

    protected function getLoginFormComponent(): Component
    {
        return Select::make('user_id')
            ->label('USUÁRIO')
            ->options(fn (): array => User::query()
                ->where('ativo', true)
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all())
            ->selectablePlaceholder(false)
            ->required()
            ->native(false);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('SENHA')
            ->password()
            ->revealable()
            ->autocomplete('off')
            ->extraInputAttributes([
                'autocomplete' => 'off',
                'data-lpignore' => 'true',
                'data-1p-ignore' => 'true',
                'data-form-type' => 'other',
            ])
            ->required();
    }

    public function authenticate(): ?LoginResponse
    {
        $empresaId = $this->form->getState()['empresa_id'] ?? 1;

        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        session(['erp_empresa_id' => $empresaId]);

        $user = Auth::user();

        if ($user instanceof User) {
            ErpAccess::storeInSession($user, $user->effectivePermissionKeys());
        }

        $target = session()->pull('url.intended', filament()->getUrl());

        $this->js('window.location.replace('.Js::from($target).')');

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
        $user = User::query()->find($data['user_id']);

        return [
            'email' => $user?->email,
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.password' => 'Usuário ou senha inválidos.',
        ]);
    }
}
