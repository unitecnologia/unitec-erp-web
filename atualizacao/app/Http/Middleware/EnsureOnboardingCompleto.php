<?php

namespace App\Http\Middleware;

use App\Support\Erp\ErpOnboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enquanto não houver empresa, o ERP já redireciona para o cadastro.
 * Passos legado usuario/colaborador ainda são respeitados se existirem no storage
 * (instalações antigas); instalações novas concluem onboarding ao salvar a empresa.
 */
class EnsureOnboardingCompleto
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $step = ErpOnboarding::step();

        if ($step === null || $step === ErpOnboarding::STEP_EMPRESA) {
            return $next($request);
        }

        if ($this->isAllowedDuringOnboarding($request, $step)) {
            return $next($request);
        }

        $target = ErpOnboarding::urlForCurrentStep();

        if ($target === null) {
            return $next($request);
        }

        return redirect()->to($target);
    }

    private function isAllowedDuringOnboarding(Request $request, string $step): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        if (
            str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire')
        ) {
            return true;
        }

        return match ($step) {
            ErpOnboarding::STEP_USUARIO => str_starts_with($path, 'admin/usuarios'),
            ErpOnboarding::STEP_COLABORADOR => $this->isAllowedDuringOperadorStep($path),
            default => false,
        };
    }

    private function isAllowedDuringOperadorStep(string $path): bool
    {
        foreach ([
            'admin/vendedores',
            'admin/rh-funcionarios',
            'admin/rh-cargos',
            'admin/rh-departamentos',
            'admin/rh-dashboard',
        ] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
