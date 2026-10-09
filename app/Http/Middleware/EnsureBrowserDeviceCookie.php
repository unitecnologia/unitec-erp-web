<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBrowserDeviceCookie
{
    public const COOKIE = 'erp_device_id';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! filled($request->cookie(self::COOKIE))) {
            Cookie::queue(self::make($request, (string) Str::uuid()));
        }

        return $response;
    }

    /**
     * Outro navegador do mesmo computador passa a usar a identidade já vinculada ao terminal.
     * Vale também para o restante desta requisição.
     */
    public static function adopt(Request $request, string $deviceUuid): void
    {
        $request->cookies->set(self::COOKIE, $deviceUuid);
        Cookie::queue(self::make($request, $deviceUuid));
    }

    private static function make(Request $request, string $deviceUuid): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            self::COOKIE,
            $deviceUuid,
            60 * 24 * 365 * 3,
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );
    }
}
