<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cloudflare Tunnel / reverse proxy: respeitar X-Forwarded-* (HTTPS real).
        $middleware->trustProxies(at: '*');

        $middleware->web(prepend: [
            \App\Http\Middleware\EnsureStorageFrameworkDirectories::class,
        ]);

        // Polling do update precisa responder mesmo com artisan down.
        $middleware->preventRequestsDuringMaintenance([
            'admin/erp-update/status',
            'admin/erp-update/reset',
            'up',
            'api/health',
        ]);

        // Status/reset do update rodam sem sessão (StartSession removido na rota).
        $middleware->validateCsrfTokens(except: [
            'admin/erp-update/status',
            'admin/erp-update/reset',
        ]);

        $middleware->alias([
            'erp.permission' => \App\Http\Middleware\EnsureErpPermission::class,
            'forcavendas.pairing' => \App\Http\Middleware\EnsureForcaVendasPairing::class,
            'forcavendas.device' => \App\Http\Middleware\EnsureForcaVendasDeviceApproved::class,
            'vendasinternas.device' => \App\Http\Middleware\EnsureVendasInternasDeviceApproved::class,
            'pdv.carga.token' => \App\Http\Middleware\EnsurePdvCargaToken::class,
            'pdv.terminal.ativo' => \App\Http\Middleware\EnsurePdvTerminalAtivo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
