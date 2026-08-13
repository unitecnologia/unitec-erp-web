<?php

namespace App\Http\Middleware;

use App\Filament\Resources\EmpresaResource;
use App\Models\Empresa;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sem empresa cadastrada: força o cadastro da primeira empresa (primeiro acesso).
 */
class EnsureEmpresaCadastrada
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        if ($this->empresaExists()) {
            return $next($request);
        }

        if ($this->isAllowedWithoutEmpresa($request)) {
            return $next($request);
        }

        return redirect()->to(EmpresaResource::getUrl('create'));
    }

    private function empresaExists(): bool
    {
        return (bool) Cache::remember('erp.empresa.exists', 120, static fn (): bool => Empresa::query()->exists());
    }

    private function isAllowedWithoutEmpresa(Request $request): bool
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
