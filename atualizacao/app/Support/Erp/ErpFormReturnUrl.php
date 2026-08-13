<?php

namespace App\Support\Erp;

class ErpFormReturnUrl
{
    public const QUERY_PARAM = 'erp_return';

    public const SESSION_RETURN_URL = 'erp_form_return_url';

    public const MONITOR_PATH = '/admin/forca-vendas-monitor';

    public const SESSION_NEW_CLIENTE_ID = 'erp_orcamento_new_cliente_id';

    public const SESSION_NEW_PRODUTO_CODIGO = 'erp_orcamento_new_produto_codigo';

    public static function normalize(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($path) || $path === '') {
            $path = str_starts_with($url, '/') ? strtok($url, '?') : '/' . ltrim(strtok($url, '?'), '/');
        }

        $normalized = rtrim($path, '/');

        if ($normalized === '/admin/orcamentos') {
            $normalized = '/admin/orcamentos/create';
        }

        if (is_string($query) && $query !== '') {
            return $normalized . '?' . $query;
        }

        return $normalized;
    }

    public static function remember(?string $returnUrl): void
    {
        if ($returnUrl === null || $returnUrl === '') {
            return;
        }

        $normalized = self::normalize($returnUrl);

        if (self::isAllowed($normalized)) {
            session([self::SESSION_RETURN_URL => $normalized]);
        }
    }

    public static function pull(): ?string
    {
        return session()->pull(self::SESSION_RETURN_URL);
    }

    public static function peek(): ?string
    {
        $value = session(self::SESSION_RETURN_URL);

        return is_string($value) && self::isAllowed($value) ? $value : null;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_RETURN_URL);
    }

    public static function fromRequest(?string $return = null): ?string
    {
        $value = $return
            ?? request()->query(self::QUERY_PARAM)
            ?? request()->query('return');

        if (! is_string($value) || trim($value) === '') {
            return self::peek();
        }

        $decoded = urldecode(trim($value));

        if (! self::isAllowed($decoded)) {
            return self::peek();
        }

        $normalized = self::normalize($decoded);
        self::remember($normalized);

        return $normalized;
    }

    public static function isAllowed(string $url): bool
    {
        return self::isOrcamentoFormUrl($url) || self::isMonitorUrl($url);
    }

    public static function isOrcamentoFormUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $path = self::pathOnly($url);

        return $path === '/admin/orcamentos/create'
            || (bool) preg_match('#^/admin/orcamentos/[^/]+/edit$#', $path);
    }

    public static function isMonitorUrl(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        return self::pathOnly($url) === self::MONITOR_PATH;
    }

    public static function isOrcamentoUrl(?string $url): bool
    {
        return self::isOrcamentoFormUrl($url);
    }

    private static function pathOnly(string $url): string
    {
        $path = self::normalize($url);

        if (str_contains($path, '?')) {
            $path = strtok($path, '?') ?: $path;
        }

        return $path;
    }

    public static function toRedirectUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $normalized = self::normalize($url);
        $path = str_contains($normalized, '?')
            ? (strtok($normalized, '?') ?: $normalized)
            : $normalized;

        return url($path);
    }

    public static function appendToUrl(string $baseUrl, ?string $returnUrl): string
    {
        if ($returnUrl === null || $returnUrl === '') {
            return $baseUrl;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . self::QUERY_PARAM . '=' . urlencode(self::normalize($returnUrl));
    }
}
