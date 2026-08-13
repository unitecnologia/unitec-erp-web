<?php

namespace App\Support\MercadoLivre;

use App\Models\Empresa;

/**
 * Configuração Mercado Livre lida somente do cadastro da empresa (banco).
 */
final class MeliEmpresaConfig
{
    public static function hubEmpresa(?Empresa $context = null): ?Empresa
    {
        $marked = Empresa::query()
            ->where('param_meli_is_hub', true)
            ->orderBy('id')
            ->first();

        if ($marked instanceof Empresa) {
            return $marked;
        }

        if ($context instanceof Empresa && filled($context->getKey())) {
            return $context;
        }

        $sessionId = session('erp_empresa_id');

        if ($sessionId) {
            $sessionEmpresa = Empresa::query()->find($sessionId);

            if ($sessionEmpresa instanceof Empresa) {
                return $sessionEmpresa;
            }
        }

        return Empresa::query()->orderBy('id')->first();
    }

    /**
     * @return array{
     *     client_id: string,
     *     client_secret: string,
     *     redirect_uri: string,
     *     is_hub: bool,
     *     app_url: string,
     *     hub_url: string
     * }
     */
    public static function forEmpresa(?Empresa $empresa): array
    {
        return [
            'client_id' => trim((string) ($empresa?->param_meli_client_id ?? '')),
            'client_secret' => trim((string) ($empresa?->param_meli_client_secret ?? '')),
            'redirect_uri' => self::redirectUri($empresa),
            'is_hub' => (bool) ($empresa?->param_meli_is_hub ?? false),
            'app_url' => self::appUrl($empresa),
            'hub_url' => self::hubUrl($empresa),
        ];
    }

    public static function appUrl(?Empresa $empresa): string
    {
        $url = rtrim(trim((string) ($empresa?->param_meli_app_url ?? '')), '/');

        if ($url !== '') {
            return $url;
        }

        return rtrim(trim((string) config('app.url')), '/');
    }

    public static function hubUrl(?Empresa $empresa = null): string
    {
        $empresa ??= self::hubEmpresa();

        $url = rtrim(trim((string) ($empresa?->param_meli_hub_url ?? '')), '/');

        if ($url !== '') {
            return $url;
        }

        return 'https://unitecnologiasc.com.br';
    }

    public static function redirectUri(?Empresa $empresa): string
    {
        $uri = trim((string) ($empresa?->param_meli_redirect_uri ?? ''));

        if ($uri !== '') {
            return $uri;
        }

        $appUrl = self::appUrl($empresa);

        return $appUrl !== '' ? $appUrl.'/admin/meli/oauth/callback' : '';
    }

    public static function hubRedirectUri(?Empresa $hub = null): string
    {
        return rtrim(self::hubUrl($hub), '/').'/meli/hub/oauth/callback';
    }

    public static function isSelfHub(?Empresa $context = null): bool
    {
        $hub = self::hubEmpresa($context);

        if ($hub && (bool) $hub->param_meli_is_hub) {
            return true;
        }

        $hubHost = strtolower((string) (parse_url(self::hubUrl($hub), PHP_URL_HOST) ?: ''));
        $appHost = strtolower((string) (parse_url(self::appUrl($hub ?? $context), PHP_URL_HOST) ?: ''));
        $requestHost = strtolower((string) request()->getHost());
        $redirectHost = strtolower((string) (parse_url(self::redirectUri($hub ?? $context), PHP_URL_HOST) ?: ''));

        if ($hubHost === '') {
            return true;
        }

        return in_array($hubHost, array_filter([$appHost, $requestHost, $redirectHost]), true)
            && ! in_array($requestHost, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * Defaults de exibição quando campos ML ainda estão vazios no formulário.
     *
     * @return array<string, mixed>
     */
    public static function formDefaults(?Empresa $empresa = null): array
    {
        $config = self::forEmpresa($empresa);

        return [
            'param_meli_modo' => filled($empresa?->param_meli_modo) ? (string) $empresa->param_meli_modo : 'hub',
            'param_meli_is_hub' => (bool) ($empresa?->param_meli_is_hub ?? false),
            'param_meli_app_url' => trim((string) ($empresa?->param_meli_app_url ?? '')) !== ''
                ? (string) $empresa->param_meli_app_url
                : ($config['app_url'] !== '' ? $config['app_url'] : 'https://unitecnologiasc.com.br'),
            'param_meli_hub_url' => trim((string) ($empresa?->param_meli_hub_url ?? '')) !== ''
                ? (string) $empresa->param_meli_hub_url
                : self::hubUrl($empresa),
        ];
    }
}
