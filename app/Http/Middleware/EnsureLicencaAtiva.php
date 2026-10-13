<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicencaBloqueadaPage;
use App\Models\Empresa;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Gestor\GestorLoginDiag;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usa o resultado da licença gravado no login.
 * Não consulta a API ao abrir telas — só no login ou no botão "Verificar".
 *
 * Livewire NÃO é isento: o painel navega por Livewire e um bypass total
 * permitiria usar o ERP só fechando o aviso. Só libera Livewire das telas
 * de licença (Renovar / Verificar).
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

        if ($this->isRouteExempt($request)) {
            return $next($request);
        }

        $allowed = $this->licencas->loginGateIsAllowed();

        // Já validou no login nesta sessão (reavalia datas a cada request).
        if ($allowed === true) {
            return $next($request);
        }

        if ($allowed === false) {
            GestorLoginDiag::log('bloqueio_licenca', [
                'user_id' => $request->user()?->getAuthIdentifier(),
                'target_url' => LicencaBloqueadaPage::getUrl(),
            ]);

            return redirect()->to(LicencaBloqueadaPage::getUrl());
        }

        // Sessão antiga / sem gate: NÃO força HTTP no portal (deixava menu lento).
        // Usa cache/grace local; se ainda assim bloquear, manda para a tela.
        $snapshot = $this->licencas->ensureLoginGateWithoutRemote();

        if ($this->licencas->resolveAccess($snapshot)['allowed']) {
            return $next($request);
        }

        GestorLoginDiag::log('bloqueio_licenca', [
            'user_id' => $request->user()?->getAuthIdentifier(),
            'target_url' => LicencaBloqueadaPage::getUrl(),
            'from_cache_gate' => true,
        ]);

        return redirect()->to(LicencaBloqueadaPage::getUrl());
    }

    private function isRouteExempt(Request $request): bool
    {
        if ($request->routeIs('filament.admin.auth.logout')
            || $request->routeIs('filament.gestor.auth.logout')) {
            return true;
        }

        $path = trim($request->path(), '/');

        if ($path === 'admin/licenca-bloqueada'
            || $path === 'admin/licenca-sistema'
            || str_starts_with($path, 'admin/licenca-sistema')
            || $path === 'admin/schema-incompativel'
            || str_starts_with($path, 'admin/schema-incompativel')) {
            return true;
        }

        // Livewire das telas de licença (Pix / Verificar) precisa passar com gate negado.
        if ($this->isLivewirePath($path)) {
            $referer = (string) $request->headers->get('referer', '');

            return str_contains($referer, '/licenca-bloqueada')
                || str_contains($referer, '/licenca-sistema')
                || str_contains($referer, '/schema-incompativel');
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
