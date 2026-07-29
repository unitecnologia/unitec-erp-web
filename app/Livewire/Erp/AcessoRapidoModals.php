<?php

namespace App\Livewire\Erp;

use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Js;
use Livewire\Attributes\On;
use Livewire\Component;

class AcessoRapidoModals extends Component
{
    public bool $alterarSenhaOpen = false;

    public bool $trocarUsuarioOpen = false;

    public string $senhaAtual = '';

    public string $senhaNova = '';

    public string $senhaConfirmacao = '';

    public ?int $trocarUserId = null;

    public string $trocarSenha = '';

    /** @var array<int, string> */
    public array $usuariosOptions = [];

    #[On('erp-open-alterar-senha')]
    public function openAlterarSenha(): void
    {
        $this->resetAlterarSenhaForm();
        $this->senhaAtual = $this->legacyPlainPassword() ?? '';
        $this->trocarUsuarioOpen = false;
        $this->alterarSenhaOpen = true;
    }

    #[On('erp-open-trocar-usuario')]
    public function openTrocarUsuario(): void
    {
        $this->resetTrocarUsuarioForm();
        $this->loadUsuariosOptions();
        $this->alterarSenhaOpen = false;
        $this->trocarUsuarioOpen = true;
    }

    public function closeAlterarSenha(): void
    {
        $this->alterarSenhaOpen = false;
        $this->resetAlterarSenhaForm();
    }

    public function closeTrocarUsuario(): void
    {
        $this->trocarUsuarioOpen = false;
        $this->resetTrocarUsuarioForm();
    }

    public function salvarNovaSenha(): void
    {
        $this->validate([
            'senhaAtual' => ['required', 'string'],
            'senhaNova' => ['required', 'string', 'min:2', 'max:60'],
            'senhaConfirmacao' => ['required', 'same:senhaNova'],
        ], [
            'senhaAtual.required' => 'Informe a senha atual.',
            'senhaNova.required' => 'Informe a nova senha.',
            'senhaNova.min' => 'A nova senha deve ter ao menos 2 caracteres.',
            'senhaConfirmacao.required' => 'Confirme a nova senha.',
            'senhaConfirmacao.same' => 'A confirmação não confere com a nova senha.',
        ]);

        /** @var User|null $user */
        $user = Auth::user();

        if (! $user instanceof User) {
            Notification::make()->title('Sessão inválida.')->danger()->send();

            return;
        }

        if (! $this->passwordMatches($user, $this->senhaAtual)) {
            $this->addError('senhaAtual', 'Senha atual incorreta.');

            return;
        }

        $plain = $this->senhaNova;
        $user->forceFill([
            'password' => Hash::make($plain),
            'senha' => $plain,
        ])->save();

        $this->closeAlterarSenha();

        Notification::make()
            ->title('Senha alterada com sucesso.')
            ->success()
            ->send();
    }

    public function updatedTrocarUserId(mixed $value): void
    {
        $this->trocarUserId = filled($value) ? (int) $value : null;
    }

    public function confirmarTrocaUsuario(): void
    {
        $this->validate([
            'trocarUserId' => ['required', 'integer'],
            'trocarSenha' => ['required', 'string'],
        ], [
            'trocarUserId.required' => 'Selecione o usuário.',
            'trocarSenha.required' => 'Informe a senha.',
        ]);

        $empresaId = ErpContext::currentEmpresaId();
        $target = User::query()->find((int) $this->trocarUserId);

        if (! $target instanceof User || ! $target->ativo) {
            $this->addError('trocarUserId', 'Usuário inválido ou inativo.');

            return;
        }

        if ($empresaId !== null && ! $target->canAccessEmpresa($empresaId) && ! $target->is_admin) {
            $this->addError('trocarUserId', 'Este usuário não tem acesso à empresa atual.');

            return;
        }

        if (! $this->passwordMatches($target, $this->trocarSenha)) {
            $this->addError('trocarSenha', 'Senha incorreta.');

            return;
        }

        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        Auth::guard('web')->login($target, false);
        session()->regenerate();

        if ($empresaId !== null && ($target->is_admin || $target->canAccessEmpresa($empresaId))) {
            session(['erp_empresa_id' => $empresaId]);
        } elseif ($target->empresa_id) {
            session(['erp_empresa_id' => (int) $target->empresa_id]);
        }

        ErpAccess::storeInSession($target, $target->effectivePermissionKeys());

        $this->closeTrocarUsuario();

        Notification::make()
            ->title('Usuário alterado: '.$target->name)
            ->success()
            ->send();

        $this->js('window.location.replace('.Js::from(filament()->getUrl()).')');
    }

    public function irParaLogin(): void
    {
        Auth::guard('web')->logout();
        ErpAccess::forgetSession();
        session()->forget('erp_empresa_id');
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(filament()->getLoginUrl(), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.erp.acesso-rapido-modals');
    }

    private function loadUsuariosOptions(): void
    {
        $empresaId = ErpContext::currentEmpresaId() ?? 0;
        $currentId = (int) (Auth::id() ?? 0);

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

        $this->usuariosOptions = $query
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $u): array => [
                $u->id => $u->name.($u->id === $currentId ? ' (atual)' : ''),
            ])
            ->all();
    }

    private function passwordMatches(User $user, string $plain): bool
    {
        if (filled($user->password) && Hash::check($plain, (string) $user->password)) {
            return true;
        }

        $legacy = (string) ($user->getRawOriginal('senha') ?? $user->senha ?? '');

        return $legacy !== '' && hash_equals($legacy, $plain);
    }

    /**
     * Plaintext from legacy column `senha` only — never reverse the bcrypt `password` hash.
     */
    private function legacyPlainPassword(): ?string
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $legacy = trim((string) ($user->getRawOriginal('senha') ?? $user->senha ?? ''));

        if ($legacy === '') {
            return null;
        }

        // Refuse if the column accidentally holds a hash instead of legacy plaintext.
        if (
            str_starts_with($legacy, '$2y$')
            || str_starts_with($legacy, '$2a$')
            || str_starts_with($legacy, '$2b$')
            || str_starts_with($legacy, '$argon')
        ) {
            return null;
        }

        return $legacy;
    }

    private function resetAlterarSenhaForm(): void
    {
        $this->resetValidation();
        $this->senhaAtual = '';
        $this->senhaNova = '';
        $this->senhaConfirmacao = '';
    }

    private function resetTrocarUsuarioForm(): void
    {
        $this->resetValidation();
        $this->trocarUserId = null;
        $this->trocarSenha = '';
    }
}
