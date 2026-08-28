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
            Cookie::queue(cookie(
                self::COOKIE,
                (string) Str::uuid(),
                60 * 24 * 365 * 3,
                null,
                null,
                $request->isSecure(),
                true,
                false,
                'lax',
            ));
        }

        return $response;
    }
}
