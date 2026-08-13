<?php

namespace App\Filament\Pages;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use App\Support\Rh\RhDashboardService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class RhDashboardPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = '';

    protected static ?string $slug = 'rh-dashboard';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, int> */
    public array $kpis = [];

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('rh.dashboard.access')
            || ErpAccess::currentCan('rh.funcionarios.access');
    }

    public function mount(): void
    {
        ErpScreen::set('RH — Painel');
        $this->kpis = app(RhDashboardService::class)->snapshot();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.pages.rh-dashboard'),
            ]);
    }

    public function refreshKpis(): void
    {
        $this->kpis = app(RhDashboardService::class)->snapshot();
    }

    public function closeScreen(): void
    {
        $this->redirect(filament()->getHomeUrl());
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-rh-dashboard-page', 'erp-home-page'];
    }
}
