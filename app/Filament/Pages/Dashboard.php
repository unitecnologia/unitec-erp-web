<?php

namespace App\Filament\Pages;

use App\Support\Erp\Dashboard\ErpDashboardData;
use App\Support\Erp\Dashboard\ErpDashboardScope;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Computed;

class Dashboard extends BaseDashboard
{
    public string $dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;

    public function mount(): void
    {
        ErpScreen::set('Principal');
        $this->dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;
    }

    #[Computed]
    public function accessibleEmpresaCount(): int
    {
        return count(ErpContext::accessibleEmpresaIds());
    }

    #[Computed]
    public function showDashboardVisaoToggle(): bool
    {
        return $this->accessibleEmpresaCount >= 2;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardData(): array
    {
        return ErpDashboardData::all(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );
    }

    public function setDashboardVisao(string $visao): void
    {
        if (! in_array($visao, [ErpDashboardScope::VISAO_EMPRESA, ErpDashboardScope::VISAO_GRUPO], true)) {
            return;
        }

        if ($visao === ErpDashboardScope::VISAO_GRUPO && $this->accessibleEmpresaCount < 2) {
            return;
        }

        $this->dashboardVisao = $visao;
        unset($this->dashboardData);
        $this->dispatch('erp-dash-refresh');
    }

    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function getWidgets(): array
    {
        return [];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.home.screen'),
            ]);
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-home-page'];
    }
}