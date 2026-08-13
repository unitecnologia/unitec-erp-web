<?php

namespace App\Support\ContadorCloud;

use Illuminate\Http\Client\Response;

final class ContadorCloudHttpHelper
{
    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (preg_match('#^(https?)://([^/]+)(/.*)?$#i', $url, $matches)) {
            $scheme = strtolower($matches[1]);
            if ($scheme === 'http') {
                $scheme = 'https';
            }

            $url = $scheme
                .'://'
                .strtolower($matches[2])
                .strtolower($matches[3] ?? '');
        }

        return rtrim($url, '/');
    }

    public static function resolvePortalBaseUrl(string $url = ''): string
    {
        $url = self::normalizeUrl($url);

        if ($url !== '') {
            $base = preg_replace('#/api/portal(?:/.*)?$#i', '', $url) ?? $url;

            if ($base !== '') {
                return rtrim($base, '/');
            }
        }

        return rtrim((string) config('contador-cloud.portal_base_url', 'https://unitecnologiasc.com.br'), '/');
    }

    public static function pairingRequestUrl(string $baseUrl = ''): string
    {
        $base = self::resolvePortalBaseUrl($baseUrl);
        $path = (string) config('contador-cloud.pairing_request_path', '/api/portal/vinculos/solicitar');

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    public static function pairingStatusUrl(string $vinculoId, string $baseUrl = ''): string
    {
        $base = self::resolvePortalBaseUrl($baseUrl);
        $path = (string) config('contador-cloud.pairing_status_path', '/api/portal/vinculos/{id}/status');
        $path = str_replace('{id}', rawurlencode($vinculoId), $path);

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    public static function resolveSyncUrl(string $url): string
    {
        $base = self::normalizeUrl($url);
        $path = strtolower((string) config('contador-cloud.sync_path', '/api/portal/documentos'));

        if ($base === '') {
            return '';
        }

        if (preg_match('#/api/portal/documentos$#i', $base)) {
            return preg_replace('#/api/portal/documentos$#i', '/api/portal/documentos', $base) ?? $base;
        }

        if (preg_match('#/documentos$#i', $base)) {
            return $base;
        }

        $parsedPath = (string) parse_url($base, PHP_URL_PATH);

        if ($parsedPath === '' || $parsedPath === '/') {
            return $base.$path;
        }

        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    public static function resolveHealthUrl(string $url): string
    {
        $syncUrl = self::resolveSyncUrl($url);

        if (preg_match('#/documentos$#i', $syncUrl)) {
            return preg_replace('#/documentos$#i', '/health', $syncUrl) ?? $syncUrl;
        }

        $base = self::normalizeUrl($url);
        $path = (string) config('contador-cloud.health_path', '/api/portal/health');

        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base.(str_starts_with($path, '/') ? $path : '/'.$path);
    }

    public static function isJsonApiResponse(Response $response): bool
    {
        $contentType = $response->header('Content-Type');

        if (is_array($contentType)) {
            $contentType = $contentType[0] ?? '';
        }

        if (is_string($contentType) && str_contains(strtolower($contentType), 'application/json')) {
            return true;
        }

        $body = ltrim($response->body());

        if ($body === '') {
            return false;
        }

        if (str_starts_with($body, '<!DOCTYPE') || str_starts_with($body, '<html')) {
            return false;
        }

        return $body[0] === '{' || $body[0] === '[';
    }

    public static function invalidApiMessage(Response $response, string $endpoint): string
    {
        $body = ltrim($response->body());

        if (str_starts_with($body, '<!DOCTYPE') || str_starts_with($body, '<html')) {
            return 'A URL configurada respondeu com página HTML (interface web), não com a API JSON. '
                .'Use o endpoint da API (ex.: https://seu-dominio.com.br/api/portal/documentos). '
                .'Endpoint testado: '.$endpoint;
        }

        return 'A API não retornou JSON válido no endpoint: '.$endpoint;
    }
}
