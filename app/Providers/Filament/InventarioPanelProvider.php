<?php

namespace App\Providers\Filament;

use App\Filament\Inventario\Pages\Auth\InventarioLogin;
use App\Filament\Inventario\Pages\InventarioPage;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class InventarioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('inventario')
            ->path('inventario')
            ->brandName('Unitec Inventário')
            ->favicon(asset('pwa-inventario/icons/favicon.png'))
            ->login(InventarioLogin::class)
            ->homeUrl('/inventario')
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->darkMode(false)
            ->navigation(false)
            ->topbar(false)
            ->spa(false)
            ->maxContentWidth(Width::Full)
            ->pages([
                InventarioPage::class,
            ])
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): \Illuminate\Contracts\View\View => view('filament.inventario.head'),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): \Illuminate\Contracts\View\View => view('filament.components.erp.no-browser-hints'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                \App\Http\Middleware\EnsureBrowserDeviceCookie::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                AuthenticateSession::class,
                \App\Http\Middleware\EnsureEmpresaSelecionada::class,
                \App\Http\Middleware\EnsureLicencaAtiva::class,
                \App\Http\Middleware\EnsureLicensedBrowserDevice::class,
            ]);
    }
}
