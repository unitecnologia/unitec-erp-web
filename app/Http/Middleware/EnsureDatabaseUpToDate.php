<?php

namespace App\Http\Middleware;

use App\Support\Erp\Database\PendingMigrationsGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ao abrir o ERP: se o schema estiver atrás do código (restore de dump antigo),
 * aplica só `migrate --force` (nunca fresh).
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
        // API do PDV / health: evita custo e race em alta frequência.
        if ($request->is('api/*') || $request->is('up')) {
            return $next($request);
        }

        $this->guard->ensureUpToDate();

        return $next($request);
    }
}
