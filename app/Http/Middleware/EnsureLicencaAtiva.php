<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicencaBloqueadaPage;
use App\Support\Erp\License\LicencaRemotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usa o resultado da licença gravado no login.
 * Não consulta a API ao abrir telas — só no login ou no botão "Verificar".
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

        $allowed = $this->licencas->loginGateIsAllowed();

        // Já validou no login nesta sessão.
        if ($allowed === true) {
            return $next($request);
        }

        if ($allowed === false) {
            return redirect()->to(LicencaBloqueadaPage::getUrl());
        }

        // Sessão antiga / sem gate: valida uma vez e grava (equivale ao login).
        $snapshot = $this->licencas->validateAtLogin();

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
            || $path === 'admin/licenca-sistema'
            || str_starts_with($path, 'admin/licenca-sistema')
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire');
    }
}
