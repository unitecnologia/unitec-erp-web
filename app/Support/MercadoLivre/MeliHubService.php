<?php

namespace App\Support\MercadoLivre;

use App\Models\Empresa;
use App\Models\MeliHubPair;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class MeliHubService
{
    public function hubBaseUrl(?Empresa $context = null): string
    {
        return MeliEmpresaConfig::hubUrl(MeliEmpresaConfig::hubEmpresa($context));
    }

    public function isSelfHub(?Empresa $context = null): bool
    {
        return MeliEmpresaConfig::isSelfHub($context);
    }

    public function isLocalBrowser(): bool
    {
        return in_array(strtolower((string) request()->getHost()), ['127.0.0.1', 'localhost', '::1'], true);
    }

    public function hubRedirectUri(?Empresa $hub = null): string
    {
        return MeliEmpresaConfig::hubRedirectUri($hub ?? MeliEmpresaConfig::hubEmpresa());
    }

    /**
     * Credenciais do app ML no hub (cadastro da empresa hub).
     *
     * @return array{client_id: string, client_secret: string, redirect_uri: string}
     */
    public function hubAppCredentials(?Empresa $hub = null): array
    {
        $hub ??= MeliEmpresaConfig::hubEmpresa();
        $credentials = MeliEmpresaConfig::forEmpresa($hub);

        return [
            'client_id' => $credentials['client_id'],
            'client_secret' => $credentials['client_secret'],
            'redirect_uri' => $this->hubRedirectUri($hub),
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function createPair(?int $empresaId = null, string $clientLabel = ''): array
    {
        $pair = MeliHubPair::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => MeliHubPair::STATUS_PENDING,
            'empresa_id' => $empresaId,
            'client_label' => Str::limit($clientLabel, 120, ''),
            'expires_at' => now()->addMinutes((int) config('meli.oauth_state_ttl_minutes', 15)),
        ]);

        return [
            'ok' => true,
            'message' => 'Pareamento criado.',
            'data' => [
                'uuid' => $pair->uuid,
                'connect_url' => $this->hubBaseUrl().'/meli/hub/connect?pair='.$pair->uuid,
                'status_url' => $this->hubBaseUrl().'/api/meli/hub/pair/'.$pair->uuid,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function beginHubAuthorization(string $pairUuid): array
    {
        $pair = MeliHubPair::query()->where('uuid', $pairUuid)->first();

        if (! $pair) {
            return ['ok' => false, 'message' => 'Pareamento não encontrado.'];
        }

        if ($pair->isExpired()) {
            $pair->update(['status' => MeliHubPair::STATUS_EXPIRED]);

            return ['ok' => false, 'message' => 'Pareamento expirado. Gere um novo no ERP.'];
        }

        $credentials = $this->hubAppCredentials();

        if ($credentials['client_id'] === '' || $credentials['client_secret'] === '') {
            return [
                'ok' => false,
                'message' => 'Hub sem Client ID / Client Secret. Preencha na aba Mercado Livre da empresa hub e grave (F5).',
            ];
        }

        $pkce = app(MeliOAuthService::class)->generatePkcePair();
        $state = Str::random(40);

        Cache::put($this->stateCacheKey($state), [
            'pair_uuid' => $pair->uuid,
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
            'message' => 'OK',
            'data' => [
                'authorize_url' => rtrim((string) config('meli.auth_url'), '?').'?'.$query,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function completeHubAuthorization(string $code, string $state): array
    {
        $cached = Cache::pull($this->stateCacheKey($state));

        if (! is_array($cached)) {
            return ['ok' => false, 'message' => 'Sessão de autorização expirada.'];
        }

        $pair = MeliHubPair::query()->where('uuid', (string) ($cached['pair_uuid'] ?? ''))->first();

        if (! $pair) {
            return ['ok' => false, 'message' => 'Pareamento não encontrado.'];
        }

        $credentials = $this->hubAppCredentials();
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post((string) config('meli.token_url'), [
                'grant_type' => 'authorization_code',
                'client_id' => $credentials['client_id'],
                'client_secret' => $credentials['client_secret'],
                'code' => $code,
                'redirect_uri' => $credentials['redirect_uri'],
                'code_verifier' => (string) ($cached['code_verifier'] ?? ''),
            ]);

        if (! $response->successful()) {
            $message = $this->extractError($response->json(), 'Mercado Livre recusou a autorização.');
            $pair->update([
                'status' => MeliHubPair::STATUS_ERROR,
                'erro' => $message,
            ]);

            return ['ok' => false, 'message' => $message];
        }

        $payload = $response->json();
        $accessToken = trim((string) ($payload['access_token'] ?? ''));

        if ($accessToken === '') {
            $pair->update([
                'status' => MeliHubPair::STATUS_ERROR,
                'erro' => 'Token vazio.',
            ]);

            return ['ok' => false, 'message' => 'Mercado Livre não retornou access token.'];
        }

        $userResult = app(MeliApiClient::class)->getMe($accessToken);
        $expiresIn = (int) ($payload['expires_in'] ?? 0);

        $pair->update([
            'status' => MeliHubPair::STATUS_AUTHORIZED,
            'access_token' => $accessToken,
            'refresh_token' => trim((string) ($payload['refresh_token'] ?? '')),
            'meli_user_id' => (string) ($userResult['data']['id'] ?? ''),
            'nickname' => trim((string) ($userResult['data']['nickname'] ?? '')),
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'erro' => null,
        ]);

        return [
            'ok' => true,
            'message' => 'Conta autorizada. Volte ao ERP — a conexão será concluída automaticamente.',
            'data' => [
                'nickname' => $pair->nickname,
                'meli_user_id' => $pair->meli_user_id,
            ],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function pairStatus(string $uuid): array
    {
        $pair = MeliHubPair::query()->where('uuid', $uuid)->first();

        if (! $pair) {
            return ['ok' => false, 'message' => 'Pareamento não encontrado.'];
        }

        if ($pair->isExpired() && $pair->status === MeliHubPair::STATUS_PENDING) {
            $pair->update(['status' => MeliHubPair::STATUS_EXPIRED]);
        }

        $data = [
            'status' => $pair->status,
            'nickname' => $pair->nickname,
            'meli_user_id' => $pair->meli_user_id,
        ];

        if ($pair->status === MeliHubPair::STATUS_AUTHORIZED) {
            $data['access_token'] = $pair->access_token;
            $data['refresh_token'] = $pair->refresh_token;
            $data['token_expires_at'] = optional($pair->token_expires_at)?->toIso8601String();
        }

        if ($pair->status === MeliHubPair::STATUS_ERROR) {
            $data['erro'] = $pair->erro;
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => $data,
        ];
    }

    /**
     * Cliente remoto: cria pareamento no hub via HTTP.
     *
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function createRemotePair(Empresa $empresa): array
    {
        if ($this->isSelfHub($empresa)) {
            return $this->createPair((int) $empresa->getKey(), (string) ($empresa->fantasia ?: $empresa->razao_social));
        }

        $response = Http::acceptJson()
            ->timeout(20)
            ->post($this->hubBaseUrl($empresa).'/api/meli/hub/pair', [
                'empresa_id' => $empresa->getKey(),
                'client_label' => (string) ($empresa->fantasia ?: $empresa->razao_social),
            ]);

        if ($response->status() === 404) {
            return [
                'ok' => false,
                'message' => 'Hub Unitec ainda sem a atualização do Mercado Livre. Publique o ERP em '.$this->hubBaseUrl($empresa).' e rode php artisan migrate.',
            ];
        }

        if (! $response->successful()) {
            return [
                'ok' => false,
                'message' => 'Não foi possível falar com o hub Unitec ('.$this->hubBaseUrl($empresa).'). Status HTTP '.$response->status().'.',
            ];
        }

        $json = $response->json();

        if (! is_array($json) || empty($json['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($json['message'] ?? 'Hub Unitec recusou o pareamento.'),
            ];
        }

        return [
            'ok' => true,
            'message' => 'OK',
            'data' => is_array($json['data'] ?? null) ? $json['data'] : [],
        ];
    }

    /**
     * @return array{ok: bool, message: string, data?: array<string, mixed>}
     */
    public function fetchRemotePairStatus(string $uuid): array
    {
        if ($this->isSelfHub()) {
            return $this->pairStatus($uuid);
        }

        $response = Http::acceptJson()
            ->timeout(15)
            ->get($this->hubBaseUrl().'/api/meli/hub/pair/'.$uuid);

        if (! $response->successful()) {
            return ['ok' => false, 'message' => 'Falha ao consultar status no hub.'];
        }

        $json = $response->json();

        if (! is_array($json)) {
            return ['ok' => false, 'message' => 'Resposta inválida do hub.'];
        }

        return [
            'ok' => (bool) ($json['ok'] ?? false),
            'message' => (string) ($json['message'] ?? ''),
            'data' => is_array($json['data'] ?? null) ? $json['data'] : [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function extractError(?array $payload, string $fallback): string
    {
        if (! is_array($payload)) {
            return $fallback;
        }

        $message = trim((string) ($payload['message'] ?? $payload['error_description'] ?? $payload['error'] ?? ''));

        return $message !== '' ? $message : $fallback;
    }

    private function stateCacheKey(string $state): string
    {
        return 'meli_hub_oauth_state:'.hash('sha256', $state);
    }
}
