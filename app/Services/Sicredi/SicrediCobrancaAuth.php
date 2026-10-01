<?php

namespace App\Services\Sicredi;

use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Autenticação OAuth2 da API de Cobrança Sicredi (manual 3.9.1).
 *
 * - x-api-key: token do portal do desenvolvedor
 * - username: codigoBeneficiario + cooperativa
 * - password: código de acesso (Internet Banking)
 * - scope: cobranca | context header: COBRANCA
 */
final class SicrediCobrancaAuth
{
    private const HOST_HOMOLOGACAO = 'https://api-parceiro.sicredi.com.br/sb';

    private const HOST_PRODUCAO = 'https://api-parceiro.sicredi.com.br';

    private const ACCESS_TOKEN_SKEW_SECONDS = 30;

    /**
     * @return array{access_token: string, refresh_token: string|null, x_api_key: string}
     *
     * @throws RuntimeException
     */
    public function credentialsForCobranca(Empresa $empresa): array
    {
        $xApiKey = $this->xApiKey($empresa);
        $tokens = $this->getTokens($empresa, $xApiKey);

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'x_api_key' => $xApiKey,
        ];
    }

    /**
     * Base da API (com ou sem /sb). Override via param_boleto_api_url.
     */
    public function hostForEmpresa(Empresa $empresa): string
    {
        $custom = rtrim(trim((string) ($empresa->param_boleto_api_url ?? '')), '/');
        if ($custom !== '') {
            return $custom;
        }

        return $this->ambiente($empresa) === 'producao'
            ? self::HOST_PRODUCAO
            : self::HOST_HOMOLOGACAO;
    }

    public function cooperativa(Empresa $empresa): string
    {
        $v = preg_replace('/\D/', '', (string) ($empresa->param_boleto_agencia ?? '')) ?? '';
        $v = str_pad(substr($v, -4), 4, '0', STR_PAD_LEFT);

        if ($v === '0000') {
            throw new RuntimeException(
                'Cooperativa Sicredi não configurada (Empresa > Parâmetros > API Boleto).'
            );
        }

        return $v;
    }

    public function posto(Empresa $empresa): string
    {
        $v = preg_replace('/\D/', '', (string) ($empresa->param_boleto_agencia_dv ?? '')) ?? '';
        $v = str_pad(substr($v, -2), 2, '0', STR_PAD_LEFT);

        if ($v === '00' && trim((string) ($empresa->param_boleto_agencia_dv ?? '')) === '') {
            throw new RuntimeException(
                'Posto Sicredi não configurado (Empresa > Parâmetros > API Boleto).'
            );
        }

        return $v;
    }

    public function codigoBeneficiario(Empresa $empresa): string
    {
        $v = preg_replace('/\D/', '', (string) ($empresa->param_boleto_beneficiario_codigo ?? '')) ?? '';
        if ($v === '') {
            throw new RuntimeException(
                'Código do Beneficiário Sicredi não configurado (Empresa > Parâmetros > API Boleto).'
            );
        }

        return substr($v, -5);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null}
     *
     * @throws RuntimeException
     */
    private function getTokens(Empresa $empresa, string $xApiKey): array
    {
        $ambiente = $this->ambiente($empresa);
        $cacheKey = $this->tokenCacheKey((int) $empresa->id, $ambiente);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && is_string($cached['access_token'] ?? null) && $cached['access_token'] !== '') {
            return [
                'access_token' => $cached['access_token'],
                'refresh_token' => is_string($cached['refresh_token'] ?? null) ? $cached['refresh_token'] : null,
            ];
        }

        $refresh = is_array($cached) && is_string($cached['refresh_token'] ?? null)
            ? $cached['refresh_token']
            : null;

        if (is_string($refresh) && $refresh !== '') {
            try {
                return $this->requestToken($empresa, $xApiKey, [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refresh,
                    'scope' => 'cobranca',
                ], $cacheKey);
            } catch (RuntimeException) {
                // Cai no fluxo password.
            }
        }

        $beneficiario = $this->codigoBeneficiario($empresa);
        $cooperativa = $this->cooperativa($empresa);
        $password = trim((string) ($empresa->param_boleto_senha_api ?? ''));

        if ($password === '') {
            throw new RuntimeException(
                'Código de acesso Sicredi não configurado (Empresa > Parâmetros > API Boleto).'
            );
        }

        return $this->requestToken($empresa, $xApiKey, [
            'grant_type' => 'password',
            'username' => $beneficiario.$cooperativa,
            'password' => $password,
            'scope' => 'cobranca',
        ], $cacheKey);
    }

    /**
     * @param  array<string, string>  $form
     * @return array{access_token: string, refresh_token: string|null}
     *
     * @throws RuntimeException
     */
    private function requestToken(Empresa $empresa, string $xApiKey, array $form, string $cacheKey): array
    {
        $response = SicrediHttp::client()
            ->asForm()
            ->withHeaders([
                'x-api-key' => $xApiKey,
                'context' => 'COBRANCA',
                'Accept' => 'application/json',
            ])
            ->timeout(45)
            ->post($this->hostForEmpresa($empresa).'/auth/openapi/token', $form);

        if (! $response->successful()) {
            $hint = '';
            $body = $response->body();
            if ($response->status() === 401 && str_contains($body, 'x-api-key')) {
                $hint = ' O header x-api-key precisa ser o Access Token da app no portal Sicredi'
                    .' (Minhas Apps → Ver detalhes), não o Client ID.'
                    .' Cole esse token em Empresa > API Boleto > Sicredi > Access Token (x-api-key).'
                    .' Se o token ainda não aparece na app, abra chamado no suporte do portal (Sandbox).';
            }

            throw new RuntimeException(
                'Falha ao obter token Sicredi (HTTP '.$response->status().'): '.$body.$hint
            );
        }

        $accessToken = trim((string) $response->json('access_token', ''));
        if ($accessToken === '') {
            throw new RuntimeException('Resposta Sicredi sem access_token.');
        }

        $refreshToken = trim((string) $response->json('refresh_token', ''));
        $expiresIn = (int) $response->json('expires_in', 300);
        $ttl = max(60, $expiresIn - self::ACCESS_TOKEN_SKEW_SECONDS);

        $payload = [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken !== '' ? $refreshToken : null,
        ];

        Cache::put($cacheKey, $payload, now()->addSeconds($ttl));

        return $payload;
    }

    /**
     * @throws RuntimeException
     */
    private function xApiKey(Empresa $empresa): string
    {
        // Manual Sicredi: x-api-key = Access Token da app (portal), distinto do Client ID.
        $key = trim((string) ($empresa->param_boleto_dev_app_key ?? ''));
        if ($key === '') {
            // Fallback legado: alguns cadastros antigos colocaram o token em client_id.
            $key = trim((string) ($empresa->param_boleto_client_id ?? ''));
        }

        if ($key === '') {
            throw new RuntimeException(
                'Access Token (x-api-key) Sicredi não configurado (Empresa > API Boleto > Sicredi).'
            );
        }

        return $key;
    }

    private function ambiente(Empresa $empresa): string
    {
        $ambiente = mb_strtolower(trim((string) ($empresa->param_boleto_ambiente ?? 'homologacao')), 'UTF-8');

        return $ambiente === 'producao' ? 'producao' : 'homologacao';
    }

    private function tokenCacheKey(int $empresaId, string $ambiente): string
    {
        return 'sicredi:cobranca:token:'.$empresaId.':'.$ambiente;
    }
}
