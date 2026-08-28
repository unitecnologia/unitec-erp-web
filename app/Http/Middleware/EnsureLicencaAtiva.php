<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicencaBloqueadaPage;
use App\Models\Empresa;
use App\Support\Erp\License\LicencaRemotaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Primeiro acesso: a licença depende do CNPJ, que só existe após
        // cadastrar a primeira empresa. Não bloquear esse cadastro.
        if (! Cache::remember('erp.empresa.exists', 120, static fn (): bool => Empresa::query()->exists())) {
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

        // Sessão antiga / sem gate: NÃO força HTTP no portal (deixava menu lento).
        // Usa cache/grace local; se ainda assim bloquear, manda para a tela.
        $snapshot = $this->licencas->ensureLoginGateWithoutRemote();

        if ($snapshot->isAllowed()) {
            return $next($request);
        }

        return redirect()->to(LicencaBloqueadaPage::getUrl());
    }

    private function isAllowed(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')
            || $request->routeIs('filament.gestor.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        return $path === 'admin/licenca-bloqueada'
            || $path === 'admin/licenca-sistema'
            || str_starts_with($path, 'admin/licenca-sistema')
            || str_starts_with($path, 'livewire/')
            || str_starts_with($path, 'admin/livewire')
            || str_starts_with($path, 'gestor/livewire');
    }
}
