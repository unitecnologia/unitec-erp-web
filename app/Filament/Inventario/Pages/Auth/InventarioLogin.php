<?php

namespace App\Filament\Inventario\Pages\Auth;

use App\Filament\Pages\Auth\Login as ErpLogin;
use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\RequestOrigin;
use App\Support\Inventario\InventarioAcesso;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

class InventarioLogin extends ErpLogin
{
    protected string $view = 'filament.inventario.pages.auth.login';

    protected Width|string|null $maxWidth = Width::Large;

    public ?string $deviceLimitError = null;

    public function mount(): void
    {
        if (Empresa::configuracaoInicialPendente()) {
            $this->redirect(RequestOrigin::toBrowserUrl(Filament::getPanel('admin')->getLoginUrl()));

            return;
        }

        $flash = session()->pull('device_limit_error');
        $this->deviceLimitError = filled($flash) ? (string) $flash : null;

        parent::mount();
    }

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
            ->extraAttributes(['class' => 'inv-login__btn-secondary'])
            ->action(fn (): mixed => $this->cancel());
    }

    /**
     * @return array<int|string, string>
     */
    protected function userOptionsForEmpresa(int $empresaId): array
    {
        $options = parent::userOptionsForEmpresa($empresaId);

        if ($options === []) {
            return [];
        }

        $liberados = User::query()
            ->whereIn('id', array_map('intval', array_keys($options)))
            ->get()
            ->filter(fn (User $user): bool => $this->usuarioAutorizado($user))
            ->map(fn (User $user): int => (int) $user->id)
            ->all();

        $permitidos = array_fill_keys($liberados, true);
        $filtrados = [];

        foreach ($options as $id => $nome) {
            if (isset($permitidos[(int) $id])) {
                $filtrados[$id] = $nome;
            }
        }

        return $filtrados;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultFormState(): array
    {
        $state = parent::getDefaultFormState();
        $userId = (int) ($state['user_id'] ?? 0);

        if ($userId > 0) {
            $user = User::query()->find($userId);

            if (! $user instanceof User || ! $this->usuarioAutorizado($user)) {
                $state['user_id'] = null;
            }
        }

        return $state;
    }

    public function authenticate(): ?LoginResponse
    {
        $userId = (int) ($this->data['user_id'] ?? 0);

        if ($userId > 0) {
            $user = User::query()->find($userId);

            if (! $user instanceof User || ! $this->usuarioAutorizado($user)) {
                throw ValidationException::withMessages([
                    'data.user_id' => 'Este usuário não está autorizado no Inventário.',
                ]);
            }
        }

        return parent::authenticate();
    }

    private function usuarioAutorizado(User $user): bool
    {
        if (! $user->ativo || ! $user->podeAcessarApp(User::APP_INVENTARIO)) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        $chaves = $user->effectivePermissionKeys();

        return in_array(InventarioAcesso::CONSULTA, $chaves, true)
            || in_array(InventarioAcesso::CONTAGEM, $chaves, true)
            || in_array(InventarioAcesso::FINALIZAR, $chaves, true);
    }
}
