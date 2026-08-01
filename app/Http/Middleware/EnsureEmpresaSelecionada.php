<?php

namespace App\Http\Middleware;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante que a empresa da sessão (erp_empresa_id) pertence ao usuário logado.
 *
 * Roda depois de EnsureEmpresaCadastrada e EnsureOnboardingCompleto: nesse ponto
 * já existe empresa e o onboarding terminou. Se a sessão não tem empresa válida
 * (perdida, inexistente, ou de outro tenant), força novo login.
 */
class EnsureEmpresaSelecionada
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        // Sem empresa cadastrada ainda: primeiro acesso é tratado por outro middleware.
        if (! Cache::remember('erp.empresa.exists', 120, static fn (): bool => Empresa::query()->exists())) {
            return $next($request);
        }

        $empresaId = ErpContext::currentEmpresaId();

        if ($empresaId !== null && ErpContext::userCanAccessEmpresa($empresaId, $user)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        session()->forget('erp_empresa_id');
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->guest(filament()->getLoginUrl());
    }

    private function isAllowed(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return $path === 'admin/empresas/create'
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire');
    }
}
