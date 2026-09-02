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

    public bool $dashboardHeavyReady = false;

    public function mount(): void
    {
        ErpScreen::set('Principal');
        $this->dashboardVisao = ErpDashboardScope::VISAO_EMPRESA;
        $this->dashboardHeavyReady = false;
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
     * First paint: KPIs + metadados de visão.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardShell(): array
    {
        return ErpDashboardData::shell(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );
    }

    /**
     * Blocos pesados (carregados após wire:init).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardHeavy(): array
    {
        return ErpDashboardData::heavy(
            empresaId: ErpContext::currentEmpresaId(),
            visao: $this->dashboardVisao,
        );
    }

    /**
     * Compat: shell + heavy quando pronto; só shell no first paint.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function dashboardData(): array
    {
        $shell = $this->dashboardShell;

        if (! $this->dashboardHeavyReady) {
            return $shell;
        }

        return [
            ...$shell,
            ...$this->dashboardHeavy,
        ];
    }

    public function loadDashboardHeavy(): void
    {
        $this->dashboardHeavyReady = true;
        unset($this->dashboardHeavy, $this->dashboardData);
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
        $this->dashboardHeavyReady = false;
        unset($this->dashboardShell, $this->dashboardHeavy, $this->dashboardData);
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
