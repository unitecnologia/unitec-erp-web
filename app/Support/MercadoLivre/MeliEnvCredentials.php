<?php

namespace App\Support\MercadoLivre;

/**
 * Sincroniza credenciais/config ML entre a aba Empresa e o .env.
 */
final class MeliEnvCredentials
{
    /**
     * @return array{
     *     client_id: string,
     *     client_secret: string,
     *     redirect_uri: string,
     *     hub_url: string,
     *     is_hub: bool,
     *     app_url: string
     * }
     */
    public static function fromEnv(): array
    {
        return [
            'client_id' => trim((string) config('meli.client_id')),
            'client_secret' => trim((string) config('meli.client_secret')),
            'redirect_uri' => trim((string) config('meli.redirect_uri')),
            'hub_url' => rtrim((string) (config('meli.hub_url') ?: 'https://unitecnologiasc.com.br'), '/'),
            'is_hub' => filter_var(config('meli.is_hub'), FILTER_VALIDATE_BOOL),
            'app_url' => rtrim((string) config('app.url'), '/'),
        ];
    }

    /**
     * Grava chaves ML (+ APP_URL / hub) no .env e atualiza config em runtime.
     *
     * @param  array<string, mixed>  $credentials
     */
    public static function writeToEnv(array $credentials): bool
    {
        $path = base_path('.env');

        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $isHub = filter_var($credentials['is_hub'] ?? config('meli.is_hub'), FILTER_VALIDATE_BOOL);
        $appUrl = trim((string) ($credentials['app_url'] ?? config('app.url')));
        $hubUrl = rtrim(trim((string) ($credentials['hub_url'] ?? config('meli.hub_url'))), '/');
        $redirectUri = trim((string) ($credentials['redirect_uri'] ?? ''));

        if ($redirectUri === '' && $appUrl !== '') {
            $redirectUri = rtrim($appUrl, '/').'/admin/meli/oauth/callback';
        }

        $hubRedirect = $hubUrl !== ''
            ? $hubUrl.'/meli/hub/oauth/callback'
            : 'https://unitecnologiasc.com.br/meli/hub/oauth/callback';

        $map = [
            'APP_URL' => $appUrl,
            'MELI_IS_HUB' => $isHub ? 'true' : 'false',
            'MELI_HUB_URL' => $hubUrl !== '' ? $hubUrl : 'https://unitecnologiasc.com.br',
            'MELI_HUB_REDIRECT_URI' => $hubRedirect,
            'MELI_CLIENT_ID' => trim((string) ($credentials['client_id'] ?? '')),
            'MELI_CLIENT_SECRET' => trim((string) ($credentials['client_secret'] ?? '')),
            'MELI_REDIRECT_URI' => $redirectUri,
        ];

        $content = (string) file_get_contents($path);

        foreach ($map as $key => $value) {
            $line = $key.'='.self::escape((string) $value);

            if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content) === 1) {
                $content = (string) preg_replace(
                    '/^'.preg_quote($key, '/').'=.*/m',
                    $line,
                    $content,
                );
            } else {
                $content = rtrim($content)."\n".$line."\n";
            }
        }

        if (file_put_contents($path, $content) === false) {
            return false;
        }

        config([
            'app.url' => $map['APP_URL'],
            'meli.is_hub' => $isHub,
            'meli.hub_url' => $map['MELI_HUB_URL'],
            'meli.hub_redirect_uri' => $map['MELI_HUB_REDIRECT_URI'],
            'meli.client_id' => $map['MELI_CLIENT_ID'],
            'meli.client_secret' => $map['MELI_CLIENT_SECRET'],
            'meli.redirect_uri' => $map['MELI_REDIRECT_URI'],
        ]);

        return true;
    }

    private static function escape(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'\\\\]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
