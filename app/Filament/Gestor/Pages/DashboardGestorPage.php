<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Support\Gestor\GestorExecutivoService;
use App\Support\Gestor\GestorLoginDiag;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class DashboardGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = '/';

    protected static ?string $title = 'Início';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.dashboard';

    /** @var array<string, mixed> */
    public array $snapshot = [];

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->refreshSnapshot();

        GestorLoginDiag::log('dashboard_ok', [
            'user_id' => Auth::id(),
            'empresa_id' => $this->empresaIdAtiva(),
        ]);
    }

    public function refreshSnapshot(): void
    {
        $this->snapshot = app(GestorExecutivoService::class)->snapshot();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
