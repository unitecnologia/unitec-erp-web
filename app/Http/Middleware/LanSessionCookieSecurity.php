<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SESSION_SECURE_COOKIE=true (túnel HTTPS) faz sessão e XSRF-TOKEN saírem com "Secure".
 * Em HTTP na rede local (http://192.168.x.x:porta, http://SERVIDOR:porta) o navegador descarta
 * esses cookies e todo POST/Livewire vira 419. Só nesse caso o cookie sai sem "Secure";
 * HTTPS e domínios públicos continuam exigindo Secure.
 */
final class LanSessionCookieSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $original = config('session.secure');

        if (! $original || $request->isSecure() || ! $this->isLanHost($request->getHost())) {
            return $next($request);
        }

        // Restaura no fim: em modo worker o config sobrevive entre requisições.
        config(['session.secure' => false]);

        try {
            return $next($request);
        } finally {
            config(['session.secure' => $original]);
        }
    }

    private function isLanHost(string $host): bool
    {
        $host = strtolower(trim($host, '[] '));

        if ($host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        // Nome de máquina sem domínio (ex.: SERVIDOR) só resolve na rede local.
        return ! str_contains($host, '.');
    }
}
