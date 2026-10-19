<?php

namespace App\Http\Middleware;

use App\Support\Erp\Database\PendingMigrationsGuard;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Painel Filament: avisa se o schema estiver atrás do código, mas NÃO bloqueia o uso.
 * Migrate fica no atualizador oficial / Reparar / Aplicar Migrations.
 */
class EnsureDatabaseUpToDate
{
    public function __construct(
        private readonly PendingMigrationsGuard $guard,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        try {
            $result = $this->guard->check();

            for ($i = 0; $i < 2 && (($result['transient'] ?? false) === true); $i++) {
                usleep(100_000);
                $result = $this->guard->check(forceRefresh: true);
            }

            if (! ($result['ok'] ?? false) && (($result['transient'] ?? false) !== true)) {
                $pending = (int) ($result['pending'] ?? 0);
                // Um aviso por request basta; não redireciona nem impede o painel.
                Log::warning('Schema atrasado liberado para uso do painel.', [
                    'pending' => $pending,
                    'path' => $request->path(),
                ]);
                if ($pending > 0) {
                    session()->now('erp_schema_warning', $result['message'] ?? null);
                }
            }
        } catch (\Throwable) {
            // Falha na verificação nunca pode impedir login/uso.
        }

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')
            || $request->routeIs('filament.gestor.auth.logout')
            || $request->routeIs('filament.inventario.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        if ($path === 'admin/schema-incompativel'
            || str_starts_with($path, 'admin/schema-incompativel')
            || str_contains($path, '/login')) {
            return true;
        }

        if ($this->isLivewirePath($path)) {
            return true;
        }

        return false;
    }

    private function isLivewirePath(string $path): bool
    {
        return str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire')
            || str_starts_with($path, 'gestor/livewire')
            || str_starts_with($path, 'inventario/livewire');
    }
}
