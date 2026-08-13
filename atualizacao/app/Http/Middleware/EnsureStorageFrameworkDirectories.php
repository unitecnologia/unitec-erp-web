<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garante pastas de framework no storage antes da sessão/cache gravarem.
 * Evita "Failed to open stream" no poll da atualização quando a pasta sumiu.
 */
class EnsureStorageFrameworkDirectories
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        foreach ([
            'framework/sessions',
            'framework/cache',
            'framework/cache/data',
            'framework/views',
            'framework/testing',
            'logs',
            'app/private',
        ] as $relative) {
            $path = storage_path($relative);

            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }

        return $next($request);
    }
}
