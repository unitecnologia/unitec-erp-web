<?php

namespace App\Filament\Gestor\Pages;

use App\Filament\Gestor\Concerns\InteractsWithGestorShell;
use App\Support\Gestor\GestorExecutivoService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class VendasGestorPage extends Page
{
    use InteractsWithGestorShell;

    protected static ?string $slug = 'vendas';

    protected static ?string $title = 'Vendas';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.gestor.vendas';

    /** @var array<string, mixed> */
    public array $snapshot = [];

    public static function canAccess(): bool
    {
        return static::canAccessGestor();
    }

    public function mount(): void
    {
        $this->mountGestorShell();
        $this->snapshot = app(GestorExecutivoService::class)->snapshot();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }
}
