<?php

namespace App\Filament\Gestor\Pages\Auth;

use App\Filament\Pages\Auth\Login as ErpLogin;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;

class GestorLogin extends ErpLogin
{
    protected string $view = 'filament.gestor.pages.auth.login';

    protected Width|string|null $maxWidth = Width::Large;

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Entrar')
            ->submit('authenticate');
    }

    protected function getCancelFormAction(): Action
    {
        return Action::make('cancel')
            ->label('Limpar')
            ->color('gray')
            ->outlined()
            ->extraAttributes(['class' => 'gestor-login__btn-secondary'])
            ->action(fn (): mixed => $this->cancel());
    }
}
