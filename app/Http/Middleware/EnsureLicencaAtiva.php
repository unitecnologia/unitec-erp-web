<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicencaBloqueadaPage;
use App\Support\Erp\License\LicencaRemotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia o painel quando o gerenciador remoto marca a licença como bloqueada
 * (ou CNPJ inexistente / sem CNPJ cadastrado).
 */
class EnsureLicencaAtiva
{
    public function __construct(
        private readonly LicencaRemotaService $licencas,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $this->licencas->isEnabled()) {
            return $next($request);
        }

        if ($this->isAllowed($request)) {
            return $next($request);
        }

        $snapshot = $this->licencas->checkCurrentEmpresa();

        if ($snapshot->isAllowed()) {
            return $next($request);
        }

        return redirect()->to(LicencaBloqueadaPage::getUrl());
    }

    private function isAllowed(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return $path === 'admin/licenca-bloqueada'
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire');
    }
}
