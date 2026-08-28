<?php

namespace App\Http\Middleware;

use App\Support\Erp\ErpContext;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use App\Support\Erp\Pdv\TerminalResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

final class EnsureLicensedBrowserDevice
{
    public function __construct(
        private readonly DeviceLicenseService $devices,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Durante rollout/update, nunca impede o ERP se a migration ainda não foi aplicada.
        if (! Schema::hasColumn('terminais', 'device_uuid')) {
            return $next($request);
        }

        $empresaId = ErpContext::currentEmpresaId();
        $deviceUuid = trim((string) $request->cookie(EnsureBrowserDeviceCookie::COOKIE, ''));

        if ($empresaId === null || $deviceUuid === '') {
            return $next($request);
        }

        $origin = $request->is('gestor/*') ? 'gestor_web' : 'erp_web';

        try {
            if ($this->isMobile($request)) {
                $this->devices->register(
                    empresaId: $empresaId,
                    deviceUuid: $deviceUuid,
                    category: DeviceLicenseService::CATEGORY_TELEFONE,
                    origin: $origin,
                    deviceName: $this->browserName($request),
                    platform: 'web-mobile',
                );
            } else {
                $this->devices->attachBrowserDevice(
                    empresaId: $empresaId,
                    deviceUuid: $deviceUuid,
                    origin: $origin,
                    deviceName: TerminalResolver::make()->resolveMachineName(),
                    platform: 'web-desktop',
                );
            }
        } catch (DeviceLicenseLimitExceeded $e) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->guest(filament()->getLoginUrl())
                ->with('device_limit_error', $e->getMessage());
        }

        return $next($request);
    }

    private function isMobile(Request $request): bool
    {
        return preg_match('/android|iphone|ipad|ipod|mobile/i', (string) $request->userAgent()) === 1;
    }

    private function browserName(Request $request): string
    {
        $agent = trim((string) $request->userAgent());

        return $agent !== '' ? mb_substr($agent, 0, 120) : 'Navegador';
    }
}
