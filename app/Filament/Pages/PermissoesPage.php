<?php

namespace App\Filament\Pages;

use App\Models\ErpProfile;
use App\Models\User;
use App\Support\Erp\ErpAccess;
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

class PermissoesPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $title = '';

    protected static ?string $slug = 'permissoes';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'usuario')]
    public ?int $selectedUserId = null;

    public ?int $selectedProfileId = null;

    /** @var array<string, bool> */
    public array $checked = [];

    public string $profileNome = '';

    public string $profileDescricao = '';

    public bool $editingProfile = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('acesso.permissoes.manage');
    }

    public function mount(): void
    {
        ErpScreen::set('Permissões');

        if ($this->selectedUserId) {
            $this->loadUserPermissions();
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-permissoes-page'];
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

    public function updatedSelectedUserId(): void
    {
        $this->editingProfile = false;
        $this->selectedProfileId = null;
        $this->loadUserPermissions();
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
        if (! $this->selectedProfileId) {
            return;
        }

        $profile = ErpProfile::query()->find($this->selectedProfileId);

        if (! $profile) {
            return;
        }

        $this->checked = [];

        foreach ($profile->permissionKeys() as $key) {
            $this->checked[$key] = true;
        }

        Notification::make()
            ->title('Perfil carregado no formulário.')
            ->body('Confirme para salvar no usuário ou no perfil.')
            ->success()
            ->send();
    }

    public function startEditProfile(): void
    {
        $this->editingProfile = true;
        $this->selectedUserId = null;
        $this->checked = [];

        if ($this->selectedProfileId) {
            $profile = ErpProfile::query()->find($this->selectedProfileId);

            if ($profile) {
                $this->profileNome = $profile->nome;
                $this->profileDescricao = (string) ($profile->descricao ?? '');

                foreach ($profile->permissionKeys() as $key) {
                    $this->checked[$key] = true;
                }
            }
        } else {
            $this->profileNome = '';
            $this->profileDescricao = '';
        }
    }

    public function markGroup(string $group, bool $value): void
    {
        foreach (ErpPermissionCatalog::groupedForUi()[$group]['modules'] ?? [] as $module => $meta) {
            foreach (array_keys($meta['actions']) as $action) {
                $this->checked[ErpPermissionCatalog::key($module, $action)] = $value;
            }
        }
    }

    public function markModule(string $module, bool $value): void
    {
        $meta = ErpPermissionCatalog::modules()[$module] ?? null;

        if (! $meta) {
            return;
        }

        foreach (array_keys($meta['actions']) as $action) {
            $this->checked[ErpPermissionCatalog::key($module, $action)] = $value;
        }
    }

    public function savePermissions(): void
    {
        if ($this->editingProfile) {
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

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }
}
