<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Support\Gestor\GestorExecutivoService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

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
