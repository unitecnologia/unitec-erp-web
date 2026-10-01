<?php

namespace App\Http\Middleware;

use App\Support\Erp\RequestOrigin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sincroniza URL::useOrigin depois do TrustProxies (middleware global).
 *
 * Não pode rodar no AppServiceProvider::boot: nessa hora o host ainda pode ser
 * o do runtime local e asset() gera CSS/logo quebrados via Cloudflare Tunnel.
 * Filament não usa o grupo `web`, por isso este middleware é append global.
 */
final class SyncUrlOriginFromRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = RequestOrigin::resolve($request);

        if ($origin) {
            URL::useOrigin($origin);
        }

        return $next($request);
    }
}
