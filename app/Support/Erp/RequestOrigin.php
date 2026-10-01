<?php

namespace App\Support\Erp;

use Illuminate\Http\Request;

/**
 * Origem (scheme://host[:port]) para URL::useOrigin / asset().
 *
 * Cascata (túnel Cloudflare + LAN + local):
 * 1. Host normal → Host da request (+ porta efetiva só em loopback/LAN)
 * 2. Loopback + X-Forwarded-Host → X-Forwarded-Host (público sem porta local)
 * 3. Loopback + X-Forwarded-Proto (sem XFH) → APP_URL / URL pública da empresa
 * 4. Loopback sem forwarded → 127.0.0.1:porta
 *
 * Host público (ex.: *.unierp.uk) nunca recebe porta de runtime local (8765/8000).
 */
final class RequestOrigin
{
    public static function resolve(Request $request): ?string
    {
        // 2) X-Forwarded-Host tem prioridade (túnel / proxy com host forçado no runtime).
        $forwarded = self::forwardedHostParts($request);

        if ($forwarded !== null) {
            $scheme = self::forwardedScheme($request) ?? $request->getScheme();
            $host = $forwarded['host'];
            $port = $forwarded['port'];

            if ($port === null) {
                $port = self::forwardedPort($request);
            }

            $port = self::sanitizePortForHost($host, $port);

            return self::build($scheme, $host, $port);
        }

        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        // Caddy sem aspas no placeholder pode zerar HTTP_HOST → asset() vira http://:8000/...
        if ($host === '') {
            $serverName = trim((string) $request->server->get('SERVER_NAME', ''));
            if ($serverName !== '' && $serverName !== '0.0.0.0') {
                $host = explode(':', $serverName)[0] ?: $serverName;
            } else {
                $host = '127.0.0.1';
            }
            $port = self::portForHost($request, $host, $port);
        }

        if ($host === '') {
            return null;
        }

        // 3) Loopback + Proto (sem XFH): túnel manda Host=127.0.0.1.
        //    APP_URL local não serve — usa URL pública da empresa (Acesso remoto).
        // 4) Loopback sem forwarded: NÃO usar URL pública (PDV/local na :8765).
        if (self::isLoopbackHost($host)) {
            if (self::forwardedScheme($request) !== null) {
                $fromPublic = self::originFromConfiguredAppUrlIfPublic();
                if ($fromPublic !== null) {
                    return $fromPublic;
                }
            }

            return self::build($scheme, $host, self::effectivePort($request, $port));
        }

        // Host público ou LAN: porta efetiva só na LAN; público nunca herda SERVER_PORT/APP_URL :8765.
        $port = self::portForHost($request, $host, $port);

        return self::build($scheme, $host, $port);
    }

    /**
     * URL para o navegador após login. Host 127.0.0.1:8765 vira path relativo
     * (/gestor) para o túnel Cloudflare não expulsar o usuário do domínio público.
     * No PDV local o path relativo continua em 127.0.0.1.
     */
    public static function toBrowserUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '/';
        }

        $parts = parse_url($url);
        if (! is_array($parts)) {
            return $url;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || ! self::isLoopbackHost($host)) {
            return $url;
        }

        $path = (string) ($parts['path'] ?? '/');
        if ($path === '') {
            $path = '/';
        }

        $suffix = $path;
        if (! empty($parts['query'])) {
            $suffix .= '?'.$parts['query'];
        }
        if (! empty($parts['fragment'])) {
            $suffix .= '#'.$parts['fragment'];
        }

        return $suffix;
    }

    private static function isLoopbackHost(string $host): bool
    {
        $h = strtolower($host);

        return in_array($h, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * IPv4 privado (RFC 1918) — acesso LAN do ERP na porta 8765.
     */
    private static function isPrivateLanHost(string $host): bool
    {
        $h = strtolower(trim($host));

        if (filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        return str_starts_with($h, '10.')
            || str_starts_with($h, '192.168.')
            || (bool) preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $h);
    }

    private static function isPublicHttpHost(string $host): bool
    {
        return ! self::isLoopbackHost($host) && ! self::isPrivateLanHost($host);
    }

    /**
     * Loopback/LAN: pode usar SERVER_PORT (8765). Público: nunca.
     */
    private static function portForHost(Request $request, string $host, ?int $port): ?int
    {
        if (self::isPublicHttpHost($host)) {
            return self::sanitizePortForHost($host, $port);
        }

        return self::effectivePort($request, $port);
    }

    /**
     * Em host público, descarta portas de runtime local (ex.: 8765 vindas de APP_URL ou X-Forwarded-Port).
     */
    private static function sanitizePortForHost(string $host, ?int $port): ?int
    {
        if ($port === null || $port <= 0) {
            return null;
        }

        if (in_array((int) $port, [80, 443], true)) {
            return $port;
        }

        if (self::isPublicHttpHost($host)) {
            return null;
        }

        return $port;
    }

    /**
     * Caddy/`{http.request.host}` pode omitir a porta; Symfony assume 80/443 e ignora SERVER_PORT.
     * Recupera a porta real (ex.: 8765/8000) em loopback e LAN.
     */
    private static function effectivePort(Request $request, ?int $port): ?int
    {
        if ($port && ! in_array((int) $port, [80, 443], true)) {
            return $port;
        }

        $serverPort = (int) $request->server->get('SERVER_PORT', 0);
        if ($serverPort > 0 && ! in_array($serverPort, [80, 443], true)) {
            return $serverPort;
        }

        return $port;
    }

    private static function originFromConfiguredAppUrlIfPublic(): ?string
    {
        foreach (self::candidatePublicOriginUrls() as $raw) {
            $origin = self::originFromUrlIfPublic($raw);
            if ($origin !== null) {
                return $origin;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function candidatePublicOriginUrls(): array
    {
        $urls = [(string) config('app.url')];

        try {
            $urls[] = ErpSystemConfig::publicUrl();
            $urls[] = self::singleEmpresaPublicUrl();
        } catch (\Throwable) {
            // Login / testes sem tabela empresas.
        }

        return $urls;
    }

    private static function originFromUrlIfPublic(string $raw): ?string
    {
        $configured = parse_url(trim($raw)) ?: [];
        $scheme = strtolower((string) ($configured['scheme'] ?? ''));
        $host = (string) ($configured['host'] ?? '');
        $port = isset($configured['port']) ? (int) $configured['port'] : null;

        if ($host === '' || self::isLoopbackHost($host)) {
            return null;
        }

        if (! in_array($scheme, ['http', 'https'], true)) {
            $scheme = 'https';
        }

        $port = self::sanitizePortForHost($host, $port);

        return self::build($scheme, $host, $port);
    }

    private static function singleEmpresaPublicUrl(): string
    {
        return (string) once(function (): string {
            if (! class_exists(\App\Models\Empresa::class)) {
                return '';
            }

            $table = (new \App\Models\Empresa)->getTable();
            if (! \Illuminate\Support\Facades\Schema::hasTable($table)
                || ! \Illuminate\Support\Facades\Schema::hasColumn($table, 'param_erp_public_url')) {
                return '';
            }

            $q = \App\Models\Empresa::query()
                ->whereNotNull('param_erp_public_url')
                ->where('param_erp_public_url', '!=', '');

            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'param_acesso_remoto_habilitar')) {
                $q->where(function ($inner): void {
                    $inner->where('param_acesso_remoto_habilitar', true)
                        ->orWhere('param_acesso_remoto_habilitar', 1)
                        ->orWhere('param_acesso_remoto_habilitar', '1');
                });
            }

            $urls = $q->pluck('param_erp_public_url')
                ->map(fn ($u) => rtrim(trim((string) $u), '/'))
                ->filter()
                ->unique()
                ->values();

            return $urls->count() === 1 ? (string) $urls->first() : '';
        });
    }

    private static function hostFromForwardedHeader(Request $request): string
    {
        $raw = trim((string) $request->headers->get('Forwarded', ''));
        if ($raw === '') {
            return '';
        }

        if (preg_match('/(?:^|[;,\\s])host="?([^";,]+)"?/i', $raw, $m) === 1) {
            return trim($m[1]);
        }

        return '';
    }

    /**
     * @return array{host: string, port: int|null}|null
     */
    private static function forwardedHostParts(Request $request): ?array
    {
        $raw = trim((string) $request->headers->get('X-Forwarded-Host', ''));
        if ($raw === '') {
            $raw = self::hostFromForwardedHeader($request);
        }
        if ($raw === '') {
            return null;
        }

        $first = trim(explode(',', $raw)[0] ?? '');
        if ($first === '') {
            return null;
        }

        // host:port ou [ipv6]:port
        if (preg_match('/^\[([^\]]+)\](?::(\d+))?$/', $first, $m) === 1) {
            return [
                'host' => $m[1],
                'port' => isset($m[2]) ? (int) $m[2] : null,
            ];
        }

        if (preg_match('/^([^:]+):(\d+)$/', $first, $m) === 1) {
            return [
                'host' => $m[1],
                'port' => (int) $m[2],
            ];
        }

        return [
            'host' => $first,
            'port' => null,
        ];
    }

    private static function forwardedScheme(Request $request): ?string
    {
        $raw = trim((string) $request->headers->get('X-Forwarded-Proto', ''));
        if ($raw === '') {
            return null;
        }

        $first = strtolower(trim(explode(',', $raw)[0] ?? ''));

        return in_array($first, ['http', 'https'], true) ? $first : null;
    }

    private static function forwardedPort(Request $request): ?int
    {
        $raw = trim((string) $request->headers->get('X-Forwarded-Port', ''));
        if ($raw === '') {
            return null;
        }

        $first = trim(explode(',', $raw)[0] ?? '');
        if ($first === '' || ! ctype_digit($first)) {
            return null;
        }

        return (int) $first;
    }

    private static function build(string $scheme, string $host, ?int $port): ?string
    {
        if ($host === '') {
            return null;
        }

        $origin = $scheme.'://'.$host;

        if ($port && ! in_array((int) $port, [80, 443], true)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }
}
