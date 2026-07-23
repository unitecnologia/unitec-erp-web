<?php

namespace App\Providers\Filament;

use App\Filament\Gestor\Pages\AprovacoesGestorPage;
use App\Filament\Gestor\Pages\Auth\GestorLogin;
use App\Filament\Gestor\Pages\DashboardGestorPage;
use App\Filament\Gestor\Pages\EstoqueGestorPage;
use App\Filament\Gestor\Pages\FinanceiroGestorPage;
use App\Filament\Gestor\Pages\MaisGestorPage;
use App\Filament\Gestor\Pages\ProdutosGestorPage;
use App\Filament\Gestor\Pages\VendasGestorPage;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class GestorPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('gestor')
            ->path('gestor')
            ->brandName('Unitec Executivo')
            ->login(GestorLogin::class)
            ->homeUrl('/gestor')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(false)
            ->navigation(false)
            ->topbar(false)
            ->spa(false)
            ->maxContentWidth(Width::Full)
            ->pages([
                DashboardGestorPage::class,
                FinanceiroGestorPage::class,
                VendasGestorPage::class,
                EstoqueGestorPage::class,
                MaisGestorPage::class,
                ProdutosGestorPage::class,
                AprovacoesGestorPage::class,
            ])
            ->widgets([])
            ->assets([
                Css::make('erp-gestor', public_path('css/erp-gestor.css'))
                    ->relativePublicPath('css/erp-gestor.css'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.gestor.head'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.gestor.partials.pwa-scripts'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.gestor.partials.masks-boot'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
