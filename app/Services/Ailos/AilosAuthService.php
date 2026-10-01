<?php

namespace App\Services\Ailos;

use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Autenticação Ailos para API de Cobrança:
 * 1) access_token (client_credentials)
 * 2) obter ID (callback + UUID + state)
 * 3) login cooperado → JWT no callback (x-ailos-authentication)
 * 4) refresh do JWT (opcional)
 *
 * Tokens ficam em cache — nunca no banco.
 */
final class AilosAuthService implements AilosCobrancaAuth
{
    private const HOST_HOMOLOGACAO = 'https://apiendpointhml.ailos.coop.br';

    private const HOST_PRODUCAO = 'https://apiendpoint.ailos.coop.br';

    private const ACCESS_TOKEN_SKEW_SECONDS = 120;

    /** JWT vale ~30 min (cartilha); renovar com folga. */
    private const JWT_TTL_SECONDS = 28 * 60;

    private const JWT_POLL_ATTEMPTS = 16;

    private const JWT_POLL_SLEEP_MS = 400;

    /**
     * Par access_token + JWT prontos para headers da API de Cobrança.
     *
     * @return array{access_token: string, jwt: string}
     *
     * @throws RuntimeException
     */
    public function credentialsForCobranca(Empresa $empresa): array
    {
        $accessToken = $this->getAccessToken($empresa);
        $jwt = $this->getJwt($empresa, $accessToken);

        return [
            'access_token' => $accessToken,
            'jwt' => $jwt,
        ];
    }

    /**
     * @throws RuntimeException
     */
    public function getAccessToken(Empresa $empresa): string
    {
        $ambiente = $this->ambiente($empresa);
        $cacheKey = $this->accessTokenCacheKey((int) $empresa->id, $ambiente);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $consumerKey = trim((string) ($empresa->param_boleto_client_id ?? ''));
        $consumerSecret = trim((string) ($empresa->param_boleto_client_secret ?? ''));

        if ($consumerKey === '' || $consumerSecret === '') {
            throw new RuntimeException(
                'Consumer Key/Secret da Ailos não configurados (Empresa > Parâmetros > API Boleto).'
            );
        }

        $response = AilosHttp::client()
            ->asForm()
            ->withHeaders([
                'Authorization' => 'Basic '.base64_encode($consumerKey.':'.$consumerSecret),
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->post($this->hostForEmpresa($empresa).'/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            $hint = '';
            if ($response->status() === 401 && str_contains($response->body(), 'invalid_client')) {
                $hostUsado = $this->hostForEmpresa($empresa);
                $hint = ' Verifique Consumer Key/Secret e o host (homologação Ailos costuma ser '
                    .'https://apiendpointhml.ailos.coop.br — host atual: '.$hostUsado.').'
                    .' Se preencheu "URL base da API", deixe em branco ou use o host HML.';
            }

            throw new RuntimeException(
                'Falha ao obter access_token Ailos (HTTP '.$response->status().'): '.$response->body().$hint
            );
        }

        $accessToken = trim((string) $response->json('access_token', ''));
        $expiresIn = (int) $response->json('expires_in', 3600);

        if ($accessToken === '') {
            throw new RuntimeException('Resposta Ailos sem access_token.');
        }

        $ttl = max(60, $expiresIn - self::ACCESS_TOKEN_SKEW_SECONDS);
        Cache::put($cacheKey, $accessToken, now()->addSeconds($ttl));

        return $accessToken;
    }

    /**
     * Obtém (cache) ou renova o JWT x-ailos-authentication.
     *
     * @throws RuntimeException
     */
    public function getJwt(Empresa $empresa, ?string $accessToken = null): string
    {
        $ambiente = $this->ambiente($empresa);
        $empresaId = (int) $empresa->id;
        $cacheKey = $this->jwtCacheKey($empresaId, $ambiente);

        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            $normalized = $this->normalizeJwt($cached);
            if ($this->isValidAilosJwt($normalized)) {
                return $normalized;
            }
            Cache::forget($cacheKey);
        }

        $accessToken ??= $this->getAccessToken($empresa);

        return $this->loginForJwt($empresa, $accessToken);
    }

    /**
     * Renova o JWT via endpoint de refresh (quando ainda válido o anterior).
     *
     * @throws RuntimeException
     */
    public function renewJwt(Empresa $empresa): string
    {
        $accessToken = $this->getAccessToken($empresa);
        $ambiente = $this->ambiente($empresa);
        $cacheKey = $this->jwtCacheKey((int) $empresa->id, $ambiente);
        $previous = Cache::get($cacheKey);

        if (! is_string($previous) || $previous === '' || ! $this->isValidAilosJwt($this->normalizeJwt($previous))) {
            Cache::forget($cacheKey);

            return $this->loginForJwt($empresa, $accessToken);
        }

        $refreshed = $this->tryRefreshJwt($empresa, $accessToken, $previous);

        return $refreshed ?? $this->loginForJwt($empresa, $accessToken);
    }

    /**
     * Recebe o JWT do callback Ailos (ou de um poll no servidor central).
     */
    public function storeJwtFromCallback(string $state, string $jwt, ?int $empresaIdHint = null): void
    {
        $jwt = $this->normalizeJwt($jwt);
        if ($jwt === '' || ! $this->isValidAilosJwt($jwt)) {
            throw new RuntimeException('Callback Ailos sem JWT válido.');
        }

        $pending = Cache::get($this->pendingStateCacheKey($state));
        $empresaId = is_array($pending)
            ? (int) ($pending['empresa_id'] ?? 0)
            : (int) ($empresaIdHint ?? $this->empresaIdFromState($state));

        if ($empresaId <= 0) {
            throw new RuntimeException('Callback Ailos: state desconhecido ou sem empresa.');
        }

        $ambiente = is_array($pending)
            ? (string) ($pending['ambiente'] ?? 'homologacao')
            : $this->ambienteFromState($state);

        if ($ambiente !== 'producao') {
            $ambiente = 'homologacao';
        }

        Cache::put($this->jwtCacheKey($empresaId, $ambiente), $jwt, now()->addSeconds(self::JWT_TTL_SECONDS));
        Cache::put($this->doneStateCacheKey($state), $jwt, now()->addMinutes(5));
        Cache::forget($this->pendingStateCacheKey($state));
    }

    public function peekJwtByState(string $state): ?string
    {
        $jwt = Cache::get($this->doneStateCacheKey($state));
        if (! is_string($jwt) || $jwt === '') {
            return null;
        }

        $normalized = $this->normalizeJwt($jwt);
        if (! $this->isValidAilosJwt($normalized)) {
            Cache::forget($this->doneStateCacheKey($state));

            return null;
        }

        return $normalized;
    }

    public function hostForEmpresa(Empresa $empresa): string
    {
        $custom = rtrim(trim((string) ($empresa->param_boleto_api_url ?? '')), '/');
        if ($custom !== '') {
            return $this->normalizeHost($custom);
        }

        return $this->defaultHost($this->ambiente($empresa));
    }

    /**
     * Host Ailos em minúsculas (WAF rejeita URL com scheme/host em maiúsculas).
     */
    private function normalizeHost(string $host): string
    {
        $host = rtrim(trim($host), '/');
        if ($host === '') {
            return $host;
        }

        $parts = parse_url($host);
        if (! is_array($parts) || empty($parts['host'])) {
            return strtolower($host);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $hostname = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = (string) ($parts['path'] ?? '');

        return $scheme.'://'.$hostname.$port.rtrim($path, '/');
    }

    public function ambiente(Empresa $empresa): string
    {
        $ambiente = trim((string) ($empresa->param_boleto_ambiente ?? 'homologacao'));

        return $ambiente === 'producao' ? 'producao' : 'homologacao';
    }

    /**
     * @throws RuntimeException
     */
    private function loginForJwt(Empresa $empresa, string $accessToken): string
    {
        $this->assertLoginParams($empresa);

        $ambiente = $this->ambiente($empresa);
        $empresaId = (int) $empresa->id;
        // Descarta JWT HTML/inválido que possa ter ficado em cache.
        Cache::forget($this->jwtCacheKey($empresaId, $ambiente));

        // Sem pontos: alguns WAF rejeitam state com "." no body/query.
        $state = $empresaId.'_'.$ambiente.'_'.str_replace('-', '', (string) Str::uuid());
        $callbackUrl = $this->callbackUrl($empresa);

        Cache::put($this->pendingStateCacheKey($state), [
            'empresa_id' => $empresaId,
            'ambiente' => $ambiente,
            'created_at' => now()->toIso8601String(),
        ], now()->addMinutes(10));

        $id = $this->obterLoginId($empresa, $accessToken, $callbackUrl, $state);
        $loginJwt = $this->postCooperadoLogin($empresa, $accessToken, $id);
        if ($loginJwt !== null && $loginJwt !== '' && $this->isValidAilosJwt($loginJwt)) {
            $this->storeJwtFromCallback($state, $loginJwt, $empresaId);

            return $this->normalizeJwt($loginJwt);
        }

        $jwt = $this->waitForJwt($state, $callbackUrl);
        Cache::put($this->jwtCacheKey($empresaId, $ambiente), $jwt, now()->addSeconds(self::JWT_TTL_SECONDS));

        return $jwt;
    }

    private function tryRefreshJwt(Empresa $empresa, string $accessToken, string $previousJwt): ?string
    {
        $ambiente = $this->ambiente($empresa);

        $response = AilosHttp::client()
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'application/json',
            ])
            ->timeout(30)
            ->get($this->hostForEmpresa($empresa).'/ailos/identity/api/v1/autenticacao/token/refresh', [
                'code' => $this->normalizeJwt($previousJwt),
            ]);

        if (! $response->successful()) {
            Cache::forget($this->jwtCacheKey((int) $empresa->id, $ambiente));

            return null;
        }

        $jwt = $this->extractJwtFromMixed($response->json() ?? [], $response->body());
        if ($jwt === null || $jwt === '') {
            return null;
        }

        $jwt = $this->normalizeJwt($jwt);
        Cache::put(
            $this->jwtCacheKey((int) $empresa->id, $ambiente),
            $jwt,
            now()->addSeconds(self::JWT_TTL_SECONDS)
        );

        return $jwt;
    }

    /**
     * @throws RuntimeException
     */
    private function obterLoginId(Empresa $empresa, string $accessToken, string $callbackUrl, string $state): string
    {
        $uuid = trim((string) ($empresa->param_boleto_dev_app_key ?? ''));
        if ($uuid === '') {
            throw new RuntimeException(
                'UUID do Desenvolvedor Ailos não configurado (Empresa > Parâmetros > API Boleto).'
            );
        }

        $payload = [
            'urlCallback' => $callbackUrl,
            'ailosApiKeyDeveloper' => $uuid,
            'state' => $state,
        ];

        // Body cru evita escape de barras no JSON (WAF Ailos / Postman).
        $response = AilosHttp::client()
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'text/plain',
                'Content-Type' => 'application/json',
                'User-Agent' => 'PostmanRuntime/7.49.1',
            ])
            ->timeout(30)
            ->withBody(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}', 'application/json')
            ->post($this->hostForEmpresa($empresa).'/ailos/identity/api/v1/autenticacao/login/obter/id');

        $body = (string) $response->body();

        if (! $response->successful()) {
            throw new RuntimeException(
                'Falha ao obter ID de login Ailos (HTTP '.$response->status().'): '
                .$this->htmlSnippetForError($body)
            );
        }

        if ($this->looksLikeHtml($body) || str_contains($body, 'Request Rejected')) {
            throw new RuntimeException(
                'Ailos rejeitou obter/id (resposta HTML/WAF). Confira URL de callback, UUID e host HML. '
                .$this->htmlSnippetForError($body)
            );
        }

        $id = trim((string) ($response->json('id') ?? $body));
        $id = trim($id, "\" \n\r\t");

        if ($id === '' || strlen($id) < 20) {
            throw new RuntimeException('Resposta Ailos sem ID de autenticação válido.');
        }

        return $id;
    }

    /**
     * @return string|null JWT se a Ailos devolver no response do login
     *
     * @throws RuntimeException
     */
    private function postCooperadoLogin(Empresa $empresa, string $accessToken, string $id): ?string
    {
        $encodedId = rawurlencode($id);

        $response = AilosHttp::client()
            ->asMultipart()
            ->withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'Accept' => 'application/json, text/html',
                'User-Agent' => 'PostmanRuntime/7.49.1',
            ])
            ->timeout(45)
            ->post($this->hostForEmpresa($empresa).'/ailos/identity/api/v1/login/index?id='.$encodedId, [
                [
                    'name' => 'Login.CodigoCooperativa',
                    'contents' => trim((string) ($empresa->param_boleto_agencia ?? '')),
                ],
                [
                    'name' => 'Login.CodigoConta',
                    'contents' => trim((string) ($empresa->param_boleto_conta ?? '')),
                ],
                [
                    'name' => 'Login.Senha',
                    'contents' => (string) ($empresa->param_boleto_senha_api ?? ''),
                ],
            ]);

        $body = $response->body();
        $contentType = strtolower((string) $response->header('Content-Type'));

        // Página HTML de login ≠ JWT. O token vai para o callback (cartilha).
        // Se a Ailos devolver erro de negócio no HTML (ex.: LG006), falhar na hora.
        if (str_contains($contentType, 'text/html') || $this->looksLikeHtml($body)) {
            $loginError = $this->extractAilosLoginHtmlError($body);
            if ($loginError !== null) {
                throw new RuntimeException($this->formatAilosLoginBusinessError($loginError));
            }

            if (! $response->successful() && $response->status() !== 302) {
                throw new RuntimeException(
                    'Falha no login cooperado Ailos (HTTP '.$response->status().'): '
                    .$this->htmlSnippetForError($body)
                );
            }

            return null;
        }

        if (! $response->successful() && $response->status() !== 302) {
            throw new RuntimeException(
                'Falha no login cooperado Ailos (HTTP '.$response->status().'): '.$body
            );
        }

        $headerJwt = $response->header('x-ailos-authentication')
            ?? $response->header('X-Ailos-Authentication');
        if (is_string($headerJwt) && $this->isValidAilosJwt($this->normalizeJwt($headerJwt))) {
            return $this->normalizeJwt($headerJwt);
        }

        $extracted = $this->extractJwtFromMixed($response->json() ?? [], $body);

        return $extracted !== null && $this->isValidAilosJwt($extracted)
            ? $extracted
            : null;
    }

    /**
     * @throws RuntimeException
     */
    private function waitForJwt(string $state, string $callbackUrl): string
    {
        for ($i = 0; $i < self::JWT_POLL_ATTEMPTS; $i++) {
            $local = $this->peekJwtByState($state);
            if ($local !== null) {
                return $local;
            }

            $remote = $this->fetchJwtFromCallbackHost($callbackUrl, $state);
            if ($remote !== null) {
                $this->storeJwtFromCallback($state, $remote);

                return $remote;
            }

            usleep(self::JWT_POLL_SLEEP_MS * 1000);
        }

        throw new RuntimeException(
            'Timeout aguardando JWT Ailos no callback ('.$callbackUrl.'). '
            .'O login envia o token para essa URL; o ERP precisa conseguir lê-lo em seguida. '
            .'Confirme se o servidor do callback está atualizado e acessível.'
        );
    }

    private function fetchJwtFromCallbackHost(string $callbackUrl, string $state): ?string
    {
        $base = preg_replace('#/auth/callback/?$#', '', rtrim($callbackUrl, '/'));
        if (! is_string($base) || $base === '') {
            return null;
        }

        // Se o callback é neste próprio app, o cache local já basta.
        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl !== '' && str_starts_with($callbackUrl, $appUrl)) {
            return null;
        }

        try {
            $response = AilosHttp::client()
                ->timeout(2)
                ->acceptJson()
                ->get($base.'/auth/jwt', [
                    'state' => $state,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $jwt = $this->extractJwtFromMixed($response->json() ?? [], $response->body());

            return $jwt !== null && $jwt !== '' ? $this->normalizeJwt($jwt) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function callbackUrl(Empresa $empresa): string
    {
        $configured = trim((string) ($empresa->param_boleto_callback_url ?? ''));

        return $configured !== ''
            ? $configured
            : EmpresaParametros::boletoAilosAuthCallbackUrl();
    }

    /**
     * @throws RuntimeException
     */
    private function assertLoginParams(Empresa $empresa): void
    {
        $missing = [];
        if (trim((string) ($empresa->param_boleto_agencia ?? '')) === '') {
            $missing[] = 'Código da Cooperativa';
        }
        if (trim((string) ($empresa->param_boleto_conta ?? '')) === '') {
            $missing[] = 'Código da Conta';
        }
        if (trim((string) ($empresa->param_boleto_senha_api ?? '')) === '') {
            $missing[] = 'Senha API Ailos';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Parâmetros Ailos incompletos: '.implode(', ', $missing).' (Empresa > Parâmetros > API Boleto).'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public function extractJwtFromMixed(array $json, string $rawBody = ''): ?string
    {
        // Ailos confirma callback: { "state": "...", "code": "<jwt>" }
        $candidates = [
            $json['code'] ?? null,
            data_get($json, 'data.code'),
            $json['token'] ?? null,
            $json['access_token'] ?? null,
            $json['jwt'] ?? null,
            $json['x-ailos-authentication'] ?? null,
            $json['x_ailos_authentication'] ?? null,
            data_get($json, 'data.token'),
            data_get($json, 'data.jwt'),
            data_get($json, 'authentication.token'),
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalized = $this->normalizeJwt($candidate);
            if ($this->isValidAilosJwt($normalized)) {
                return $normalized;
            }
        }

        $trimmed = trim($rawBody);
        if ($trimmed === '' || str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return null;
        }

        // Nunca tratar HTML / página de login como token.
        if ($this->looksLikeHtml($trimmed)) {
            return null;
        }

        $normalized = $this->normalizeJwt($trimmed);

        return $this->isValidAilosJwt($normalized) ? $normalized : null;
    }

    public function extractStateFromMixed(array $json, ?string $fallback = null): ?string
    {
        $candidates = [
            $json['state'] ?? null,
            data_get($json, 'data.state'),
            $fallback,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public function normalizeJwt(string $jwt): string
    {
        $jwt = trim($jwt);
        if (str_starts_with(strtolower($jwt), 'bearer ')) {
            $jwt = trim(substr($jwt, 7));
        }

        return $jwt;
    }

    /**
     * JWT Ailos / x-ailos-authentication: não pode ser HTML nem lixo de página.
     */
    public function isValidAilosJwt(string $jwt): bool
    {
        $jwt = $this->normalizeJwt($jwt);
        if ($jwt === '') {
            return false;
        }

        if ($this->looksLikeHtml($jwt)) {
            return false;
        }

        // Header HTTP não aceita espaços/quebras.
        if (preg_match('/[\s\x00-\x1F\x7F]/', $jwt) === 1) {
            return false;
        }

        $len = strlen($jwt);
        if ($len < 32 || $len > 8192) {
            return false;
        }

        // JWT clássico (3 partes) ou token opaco sem caracteres de markup.
        if (substr_count($jwt, '.') >= 2 && str_starts_with($jwt, 'eyJ')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9\-_\.=+\/]+$/', $jwt);
    }

    private function looksLikeHtml(string $value): bool
    {
        $lower = strtolower($value);

        return str_contains($value, '<')
            || str_contains($lower, '<!doctype')
            || str_contains($lower, '<html')
            || str_contains($lower, '<body')
            || str_contains($lower, 'autentica')
            || str_contains($lower, 'cooperativa central de crédito');
    }

    private function htmlSnippetForError(string $html): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');
        if ($plain === '') {
            return '(resposta HTML sem texto)';
        }

        return mb_substr($plain, 0, 180);
    }

    private function empresaIdFromState(string $state): int
    {
        if (str_contains($state, '_')) {
            $parts = explode('_', $state);

            return (int) ($parts[0] ?? 0);
        }

        $parts = explode('.', $state);

        return (int) ($parts[0] ?? 0);
    }

    private function ambienteFromState(string $state): string
    {
        if (str_contains($state, '_')) {
            $parts = explode('_', $state);
            $candidato = (string) ($parts[1] ?? 'homologacao');
        } else {
            $parts = explode('.', $state);
            $candidato = (string) ($parts[1] ?? 'homologacao');
        }

        return $candidato === 'producao' ? 'producao' : 'homologacao';
    }

    private function extractAilosLoginHtmlError(string $html): ?string
    {
        if (preg_match('/validation-summary-errors[^>]*>.*?<li[^>]*>(.*?)<\/li>/is', $html, $m)) {
            $msg = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $msg !== '' ? $msg : null;
        }

        if (preg_match('/\b(LG\d{3})\b[^.<]{0,120}/u', strip_tags($html), $m2)) {
            return trim(preg_replace('/\s+/', ' ', $m2[0]) ?? '');
        }

        return null;
    }

    private function formatAilosLoginBusinessError(string $message): string
    {
        $base = 'Login Ailos recusado: '.$message;
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'callback')
            || str_contains($lower, 'url de callback')) {
            return $base
                .' A URL de Callback precisa ser HTTPS público, estável e aceitar POST application/json '
                .'(hoje: '.EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL.'). '
                .'Confira se o host responde 200 (não 530) e se a Ailos tem essa URL liberada no app.';
        }

        if (str_contains(mb_strtoupper($message), 'LG006')
            || str_contains($lower, 'não possui acesso')
            || str_contains($lower, 'nao possui acesso')) {
            $ambiente = 'homologação';

            return $base
                .' Peça à Ailos ('.$ambiente.') liberar a conta cooperado para a plataforma API '
                .'(cooperativa + conta informadas em Empresa > Parâmetros > API Boleto).';
        }

        return $base;
    }

    private function defaultHost(string $ambiente): string
    {
        return $ambiente === 'producao' ? self::HOST_PRODUCAO : self::HOST_HOMOLOGACAO;
    }

    private function accessTokenCacheKey(int $empresaId, string $ambiente): string
    {
        return 'ailos.access_token.'.$empresaId.'.'.$ambiente;
    }

    private function jwtCacheKey(int $empresaId, string $ambiente): string
    {
        return 'ailos.jwt.'.$empresaId.'.'.$ambiente;
    }

    private function pendingStateCacheKey(string $state): string
    {
        return 'ailos.auth.pending.'.$state;
    }

    private function doneStateCacheKey(string $state): string
    {
        return 'ailos.auth.done.'.$state;
    }
}
