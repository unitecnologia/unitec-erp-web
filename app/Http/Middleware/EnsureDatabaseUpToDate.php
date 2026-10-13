<?php

namespace App\Http\Middleware;

use App\Support\Erp\Database\PendingMigrationsGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Painel Filament: bloqueia uso normal se o schema estiver atrás do código.
 * Não executa migrate — só verifica (leve + cache). Login/atualização/reparo ficam livres.
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

        $result = $this->guard->check();

        for ($i = 0; $i < 3 && (($result['transient'] ?? false) === true); $i++) {
            usleep(150_000);
            $result = $this->guard->check(forceRefresh: true);
        }

        if ($result['ok']) {
            return $next($request);
        }

        // Lock ainda ocupado ou schema incompatível: nunca liberar o painel sem OK confirmado.
        // Livewire/AJAX: redirect (não 503) para a tela de bloqueio/recuperação.
        return redirect()->to(url('/admin/schema-incompativel'));
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
            || str_starts_with($path, 'admin/schema-incompativel')) {
            return true;
        }

        // Livewire da tela de schema (e login com atualização) precisa passar.
        if ($this->isLivewirePath($path)) {
            $referer = (string) $request->headers->get('referer', '');

            return str_contains($referer, '/schema-incompativel')
                || str_contains($referer, '/admin/login')
                || str_contains($referer, '/gestor/login')
                || str_contains($referer, '/inventario/login');
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
