<?php

namespace App\Support\MercadoLivre;

use App\Models\Empresa;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MeliOAuthService
{
    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function beginAuthorization(Empresa $empresa, int $userId): array
    {
        $credentials = $this->resolveCredentials($empresa);

        if ($credentials['client_id'] === '' || $credentials['redirect_uri'] === '') {
            return [
                'ok' => false,
                'message' => 'Preencha Client ID e URI de redirect na aba Mercado Livre antes de conectar.',
            ];
        }

        if ($credentials['client_secret'] === '') {
            return [
                'ok' => false,
                'message' => 'Preencha o Client Secret na aba Mercado Livre antes de conectar.',
            ];
        }

        $pkce = $this->generatePkcePair();
        $state = Str::random(40);

        Cache::put($this->stateCacheKey($state), [
            'empresa_id' => (int) $empresa->getKey(),
            'user_id' => $userId,
            'code_verifier' => $pkce['verifier'],
        ], now()->addMinutes((int) config('meli.oauth_state_ttl_minutes', 15)));

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $credentials['redirect_uri'],
            'state' => $state,
            'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256',
        ]);

        return [
            'ok' => true,
            'message' => 'Redirecionando para o Mercado Livre.',
            'data' => [
                'authorize_url' => rtrim((string) config('meli.auth_url'), '?').'?'.$query,
                'state' => $state,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function completeAuthorization(string $code, string $state, int $userId): array
    {
        $cached = Cache::pull($this->stateCacheKey($state));

        if (! is_array($cached)) {
            return [
                'ok' => false,
                'message' => 'Sessão de autorização expirada. Tente conectar novamente.',
            ];
        }

        if ((int) ($cached['user_id'] ?? 0) !== $userId) {
            return [
                'ok' => false,
                'message' => 'Usuário da sessão não confere com o login atual.',
                'data' => ['empresa_id' => (int) ($cached['empresa_id'] ?? 0)],
            ];
        }

        $empresaId = (int) ($cached['empresa_id'] ?? 0);
        $empresa = Empresa::query()->find($empresaId);

        if (! $empresa) {
            return [
                'ok' => false,
                'message' => 'Empresa não encontrada para concluir a autorização.',
            ];
        }

        $tokenResult = $this->exchangeAuthorizationCode(
            $empresa,
            $code,
            (string) ($cached['code_verifier'] ?? ''),
        );

        if (! $tokenResult['ok'] || ! is_array($tokenResult['data'])) {
            return array_merge($tokenResult, [
                'data' => ['empresa_id' => $empresaId],
            ]);
        }

        $accessToken = trim((string) ($tokenResult['data']['access_token'] ?? ''));

        if ($accessToken === '') {
            return [
                'ok' => false,
                'message' => 'Mercado Livre não retornou access token.',
                'data' => ['empresa_id' => $empresaId],
            ];
        }

        $userResult = app(MeliApiClient::class)->getMe($accessToken);

        if (! $userResult['ok'] || ! is_array($userResult['data'])) {
            return [
                'ok' => false,
                'message' => $userResult['message'] ?: 'Não foi possível ler os dados da conta Mercado Livre.',
                'data' => ['empresa_id' => $empresaId],
            ];
        }

        $expiresIn = (int) ($tokenResult['data']['expires_in'] ?? 0);

        return [
            'ok' => true,
            'message' => 'Conta Mercado Livre conectada com sucesso.',
            'data' => [
                'empresa_id' => $empresaId,
                'access_token' => $accessToken,
                'refresh_token' => trim((string) ($tokenResult['data']['refresh_token'] ?? '')),
                'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
                'user_id' => (string) ($userResult['data']['id'] ?? ''),
                'nickname' => trim((string) ($userResult['data']['nickname'] ?? '')),
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function refreshAccessToken(Empresa $empresa): array
    {
        $refreshToken = trim((string) $empresa->param_meli_refresh_token);

        if ($refreshToken === '') {
            return [
                'ok' => false,
                'message' => 'Empresa sem refresh token do Mercado Livre.',
            ];
        }

        $credentials = $this->resolveCredentials($empresa);

        if ($credentials['client_id'] === '' || $credentials['client_secret'] === '') {
            return [
                'ok' => false,
                'message' => 'Client ID / Secret do Mercado Livre não configurados na empresa.',
            ];
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post((string) config('meli.token_url'), [
                'grant_type' => 'refresh_token',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'refresh_token' => $refreshToken,
            ]);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->extractErrorMessage($response->json(), 'Falha ao renovar token do Mercado Livre.'),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [
                'ok' => false,
                'message' => 'Resposta inválida ao renovar token do Mercado Livre.',
            ];
        }

        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        return [
            'ok' => true,
            'message' => 'Token renovado.',
            'data' => [
                'access_token' => trim((string) ($payload['access_token'] ?? '')),
                'refresh_token' => trim((string) ($payload['refresh_token'] ?? $refreshToken)),
                'expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            ],
        ];
    }

    /**
     * Credenciais do app ML — somente cadastro da empresa (banco).
     *
     * @return array{client_id: string, client_secret: string, redirect_uri: string}
     */
    public function resolveCredentials(?Empresa $empresa = null): array
    {
        $config = MeliEmpresaConfig::forEmpresa($empresa);

        return [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
        ];
    }

    /**
     * @return array{verifier: string, challenge: string}
     */
    public function generatePkcePair(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return [
            'verifier' => $verifier,
            'challenge' => $challenge,
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    private function exchangeAuthorizationCode(Empresa $empresa, string $code, string $codeVerifier): array
    {
        if ($codeVerifier === '') {
            return [
                'ok' => false,
                'message' => 'PKCE inválido para troca do código de autorização.',
            ];
        }

        $credentials = $this->resolveCredentials($empresa);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post((string) config('meli.token_url'), [
                'grant_type' => 'authorization_code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $credentials['redirect_uri'],
                'code_verifier' => $codeVerifier,
            ]);

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => $this->extractErrorMessage($response->json(), 'Mercado Livre recusou a autorização.'),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return [
                'ok' => false,
                'message' => 'Resposta inválida do Mercado Livre ao obter token.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Token obtido.',
            'data' => $payload,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function extractErrorMessage(?array $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        $message = trim((string) ($payload['message'] ?? $payload['error_description'] ?? $payload['error'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    private function stateCacheKey(string $state): string
    {
        return 'meli_oauth_state:'.hash('sha256', $state);
    }
}
