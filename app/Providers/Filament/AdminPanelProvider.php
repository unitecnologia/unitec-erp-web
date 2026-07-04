<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PdvPage;
use App\Support\Erp\ErpPageAssets;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->darkMode(false)
            ->navigation(false)
            ->topbar(false)
            ->spa(false)
            ->maxContentWidth(Width::Full)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                PdvPage::class,
            ])
            ->widgets([])
            ->assets([
                Css::make('erp-tokens', public_path('css/erp-tokens.css'))
                    ->relativePublicPath('css/erp-tokens.css'),
            ])
            ->renderHook(PanelsRenderHook::HEAD_START, fn (): \Illuminate\Contracts\View\View => view('filament.components.erp.sw-unregister'))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): \Illuminate\Contracts\View\View {
                if (! filament()->auth()->check()) {
                    return view('filament.components.erp.empty');
                }

                return view('filament.components.erp.page-boot');
            })
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): \Illuminate\Contracts\View\View {
                if (request()->is('admin/login')) {
                    return view('filament.components.erp.login-styles');
                }

                return view('filament.components.erp.empty');
            })
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): \Illuminate\Contracts\View\View {
                if (! ErpPageAssets::shouldLoadAuthenticatedAssets()) {
                    return view('filament.components.erp.empty');
                }

                return view('filament.components.erp.authenticated-core-styles');
            })
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): \Illuminate\Contracts\View\View {
                if (! ErpPageAssets::shouldLoadAuthenticatedAssets()) {
                    return view('filament.components.erp.empty');
                }

                return view('filament.components.erp.authenticated-module-styles');
            })
            ->renderHook(PanelsRenderHook::STYLES_AFTER, function (): \Illuminate\Contracts\View\View {
                if (! filament()->auth()->check()) {
                    return view('filament.components.erp.empty');
                }

                return view('filament.components.erp.head-assets');
            })
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, fn (): \Illuminate\Contracts\View\View => view('filament.components.erp.no-browser-hints'))
            ->renderHook(PanelsRenderHook::SCRIPTS_AFTER, function (): \Illuminate\Contracts\View\View {
                if (! filament()->auth()->check()) {
                    return view('filament.components.erp.empty');
                }

                return view('filament.components.erp.shell-scripts');
            })
            ->renderHook(PanelsRenderHook::LAYOUT_START, fn (): \Illuminate\Contracts\View\View => view('filament.components.erp.shell-header'))
            ->renderHook(PanelsRenderHook::FOOTER, fn (): \Illuminate\Contracts\View\View => view('filament.components.erp.status-bar'))
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
