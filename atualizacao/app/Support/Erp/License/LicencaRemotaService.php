<?php

namespace App\Support\Erp\License;

use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class LicencaRemotaService
{
    /** @var array<string, LicencaSnapshot> */
    private static array $requestMemo = [];

    public function isEnabled(): bool
    {
        if (! $this->resolveEnabled()) {
            return false;
        }

        return $this->resolveBaseUrl() !== '';
    }

    public function checkCurrentEmpresa(bool $forceRefresh = false): LicencaSnapshot
    {
        if (! $this->isEnabled()) {
            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_DESABILITADO,
                validoAte: $this->localFallbackDate(),
                mensagem: 'Validação remota desabilitada.',
            );
        }

        $cnpj = $this->currentCnpj();

        if ($cnpj === null) {
            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_SEM_CNPJ,
                mensagem: 'Cadastre o CNPJ da empresa para validar a licença.',
            );
        }

        $snapshot = $this->checkCnpj($cnpj, $forceRefresh);
        $this->hydrateMensalidadeFromCache($cnpj);

        return $snapshot;
    }

    /**
     * Única verificação “pesada” da sessão: no login (barra Abrindo o sistema).
     * Depois disso o middleware só lê o gate da sessão — sem HTTP.
     *
     * Portal offline: mantém grace/INDISPONIVEL (libera), exceto se mensalidade local estiver vencida.
     */
    public function validateAtLogin(): LicencaSnapshot
    {
        if (! $this->isEnabled()) {
            $snapshot = new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_DESABILITADO,
                validoAte: $this->localFallbackDate(),
                mensagem: 'Validação remota desabilitada.',
            );
            $this->rememberLoginGate($snapshot);

            return $snapshot;
        }

        $cnpj = $this->currentCnpj();

        if ($cnpj === null) {
            $snapshot = new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_SEM_CNPJ,
                mensagem: 'Cadastre o CNPJ da empresa para validar a licença.',
            );
            $this->rememberLoginGate($snapshot);

            return $snapshot;
        }

        // Portal (forceRefresh) + mensalidade: único momento HTTP além do botão Verificar.
        $snapshot = $this->checkCnpj($cnpj, forceRefresh: true);
        $this->rememberLoginGate($snapshot);
        $this->hydrateMensalidadeFromCache($cnpj);
        $this->syncMensalidadeNoGate($cnpj);
        $this->rememberLoginGate($snapshot);

        return $this->applyMensalidadeExpiry($snapshot);
    }

    /**
     * Garante gate na sessão sem chamar o portal (menu/SPA).
     * Preferência: gate existente → cache/grace → indisponível (libera o menu).
     */
    public function ensureLoginGateWithoutRemote(): LicencaSnapshot
    {
        $existing = $this->loginGateSnapshot();

        if ($existing !== null) {
            $this->hydrateMensalidadeFromCache($this->currentCnpj());

            return $this->applyMensalidadeExpiry($existing);
        }

        if (! $this->isEnabled()) {
            $snapshot = new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_DESABILITADO,
                validoAte: $this->localFallbackDate(),
                mensagem: 'Validação remota desabilitada.',
            );
            $this->rememberLoginGate($snapshot);

            return $snapshot;
        }

        $cnpj = $this->currentCnpj();

        if ($cnpj === null) {
            $snapshot = new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_SEM_CNPJ,
                mensagem: 'Cadastre o CNPJ da empresa para validar a licença.',
            );
            $this->rememberLoginGate($snapshot);

            return $snapshot;
        }

        $local = $this->resolveLocalSnapshot($cnpj);

        if ($local !== null) {
            $this->rememberLoginGate($local);
            $this->hydrateMensalidadeFromCache($cnpj);

            return $this->applyMensalidadeExpiry($local);
        }

        // Sem cache local: não bloqueia o menu com HTTP. Valida de verdade no login.
        $snapshot = new LicencaSnapshot(
            status: LicencaSnapshot::STATUS_INDISPONIVEL,
            validoAte: $this->localFallbackDate(),
            mensagem: 'Licença será revalidada no próximo login.',
        );
        $this->rememberLoginGate($snapshot);

        return $this->applyMensalidadeExpiry($snapshot);
    }

    /**
     * Snapshot só de memo/sessão/cache/grace — nunca HTTP.
     */
    private function resolveLocalSnapshot(string $cnpj): ?LicencaSnapshot
    {
        $cnpj = $this->normalizeCnpj($cnpj);

        if (strlen($cnpj) !== 14) {
            return null;
        }

        if (isset(self::$requestMemo[$cnpj])) {
            return self::$requestMemo[$cnpj];
        }

        $gateSnapshot = $this->loginGateSnapshot();

        if ($gateSnapshot !== null) {
            self::$requestMemo[$cnpj] = $gateSnapshot;

            return $gateSnapshot;
        }

        $sessionHit = $this->fromSessionFastPath($cnpj);

        if ($sessionHit !== null) {
            self::$requestMemo[$cnpj] = $sessionHit;

            return $sessionHit;
        }

        $cached = Cache::get($this->cacheKey($cnpj));

        if (is_array($cached)) {
            $snapshot = LicencaSnapshot::fromArray($cached, true);
            self::$requestMemo[$cnpj] = $snapshot;
            $this->storeSessionFastPath($cnpj, $snapshot);

            return $snapshot;
        }

        $graceHit = $this->fromGraceFastPath($cnpj);

        if ($graceHit !== null) {
            self::$requestMemo[$cnpj] = $graceHit;
            $this->storeSessionFastPath($cnpj, $graceHit);

            return $graceHit;
        }

        return null;
    }

    /**
     * Resultado da licença gravado no login (vale até logout).
     * Sem chamada à API.
     */
    public function loginGateSnapshot(): ?LicencaSnapshot
    {
        $gate = session($this->loginGateKey());

        if (! is_array($gate)) {
            return null;
        }

        $data = $gate['snapshot'] ?? null;

        return is_array($data) ? LicencaSnapshot::fromArray($data, true) : null;
    }

    public function loginGateIsAllowed(): ?bool
    {
        $gate = session($this->loginGateKey());

        if (! is_array($gate)) {
            return null;
        }

        // Mensalidade vencida bloqueia mesmo se o portal ainda disser "ativo".
        if ($this->mensalidadeVencida()) {
            return false;
        }

        return (bool) ($gate['allowed'] ?? false);
    }

    /**
     * True quando há data de mensalidade e ela já passou (início do dia).
     */
    public function mensalidadeVencida(): bool
    {
        $raw = trim((string) ($this->loginGateMensalidadeDueDate() ?? ''));

        if ($raw === '') {
            return false;
        }

        try {
            $due = \Carbon\Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            return false;
        }

        return $due->lt(now()->startOfDay());
    }

    /**
     * Se a mensalidade estiver vencida, força status bloqueado (KPI "Vencida" passa a bloquear o ERP).
     */
    public function applyMensalidadeExpiry(LicencaSnapshot $snapshot): LicencaSnapshot
    {
        if (! $this->isEnabled()) {
            return $snapshot;
        }

        if (! $this->mensalidadeVencida()) {
            return $snapshot;
        }

        if ($snapshot->status === LicencaSnapshot::STATUS_BLOQUEADO) {
            return $snapshot;
        }

        $due = $this->loginGateMensalidadeDueDate();

        return new LicencaSnapshot(
            status: LicencaSnapshot::STATUS_BLOQUEADO,
            validoAte: $due ?: $snapshot->validoAte,
            nome: $snapshot->nome,
            mensagem: 'Mensalidade vencida. Regularize o pagamento para continuar usando o sistema.',
            fromCache: $snapshot->fromCache,
        );
    }

    /**
     * Vencimento da próxima mensalidade (pagamento), gravado no login / cache.
     */
    public function loginGateMensalidadeDueDate(): ?string
    {
        $gate = session($this->loginGateKey());

        if (is_array($gate)) {
            $due = trim((string) ($gate['mensalidade_due_date'] ?? ''));

            if ($due !== '') {
                return $due;
            }
        }

        $cached = $this->mensalidadeFromCache($this->currentCnpj());

        return filled($cached['due_date'] ?? null) ? (string) $cached['due_date'] : null;
    }

    public function loginGateMensalidadeLabel(): ?string
    {
        $gate = session($this->loginGateKey());

        if (is_array($gate)) {
            $label = trim((string) ($gate['mensalidade_description'] ?? ''));

            if ($label !== '') {
                return $label;
            }
        }

        $cached = $this->mensalidadeFromCache($this->currentCnpj());

        return filled($cached['description'] ?? null) ? (string) $cached['description'] : null;
    }

    public function rememberLoginGate(LicencaSnapshot $snapshot): void
    {
        $gate = session($this->loginGateKey());
        $previous = is_array($gate) ? $gate : [];

        // Mantém o status do portal intacto; o vencimento da mensalidade só afeta `allowed`.
        $allowed = $snapshot->isAllowed() && ! $this->mensalidadeVencida();

        session([
            $this->loginGateKey() => [
                'allowed' => $allowed,
                'status' => $snapshot->status,
                'checked_at' => time(),
                'snapshot' => $snapshot->toArray(),
                // Mantém mensalidade já carregada se existir (até syncMensalidadeNoGate).
                'mensalidade_due_date' => $previous['mensalidade_due_date'] ?? null,
                'mensalidade_description' => $previous['mensalidade_description'] ?? null,
                'mensalidade_amount' => $previous['mensalidade_amount'] ?? null,
            ],
        ]);
    }

    public function rememberMensalidadeGate(?string $dueDate, ?string $description = null, ?string $amount = null): void
    {
        $gate = session($this->loginGateKey());

        if (! is_array($gate)) {
            $gate = [];
        }

        $dueDate = trim((string) $dueDate);
        $gate['mensalidade_due_date'] = $dueDate !== '' ? $dueDate : null;
        $gate['mensalidade_description'] = filled($description) ? trim((string) $description) : ($gate['mensalidade_description'] ?? null);
        $gate['mensalidade_amount'] = filled($amount) ? trim((string) $amount) : ($gate['mensalidade_amount'] ?? null);

        session([$this->loginGateKey() => $gate]);

        $cnpj = $this->currentCnpj();

        if ($cnpj !== null && $dueDate !== '') {
            Cache::put($this->mensalidadeCacheKey($cnpj), [
                'due_date' => $dueDate,
                'description' => $gate['mensalidade_description'],
                'amount' => $gate['mensalidade_amount'],
            ], now()->addHours(12));
        }

        // Reavalia bloqueio com a nova data (ex.: mensalidade vencida).
        $current = $this->loginGateSnapshot();
        if ($current !== null) {
            $this->rememberLoginGate($current);
        }
    }

    /**
     * Agenda sync da mensalidade DEPOIS de enviar a resposta HTTP.
     * Não atrasa login nem cliques.
     */
    public function scheduleMensalidadeSync(?string $cnpj = null): void
    {
        $cnpj = $this->normalizeCnpj((string) ($cnpj ?? $this->currentCnpj() ?? ''));

        if (strlen($cnpj) !== 14) {
            return;
        }

        if (session('erp.licenca.mensalidade_sync_scheduled')) {
            return;
        }

        session(['erp.licenca.mensalidade_sync_scheduled' => true]);

        dispatch(function () use ($cnpj): void {
            app(self::class)->syncMensalidadeNoGate($cnpj);
        })->afterResponse();
    }

    public function hydrateMensalidadeFromCache(?string $cnpj = null): void
    {
        $cnpj = $this->normalizeCnpj((string) ($cnpj ?? $this->currentCnpj() ?? ''));
        $cached = $this->mensalidadeFromCache($cnpj);

        if ($cached === null) {
            return;
        }

        $this->rememberMensalidadeGate(
            $cached['due_date'] ?? null,
            $cached['description'] ?? null,
            $cached['amount'] ?? null,
        );
    }

    public function syncMensalidadeNoGate(?string $cnpj = null): void
    {
        $cnpj = $this->normalizeCnpj((string) ($cnpj ?? $this->currentCnpj() ?? ''));

        if (strlen($cnpj) !== 14) {
            return;
        }

        try {
            $info = app(LicencaPortalPagamentoService::class)->proximaMensalidade($cnpj);

            if (! ($info['ok'] ?? false)) {
                return;
            }

            $this->rememberMensalidadeGate(
                $info['due_date'] ?? null,
                $info['description'] ?? null,
                $info['amount'] ?? null,
            );
        } catch (Throwable $e) {
            Log::warning('Não foi possível sincronizar vencimento da mensalidade.', [
                'cnpj' => $cnpj,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{due_date?: string, description?: string, amount?: string}|null
     */
    private function mensalidadeFromCache(?string $cnpj): ?array
    {
        $cnpj = $this->normalizeCnpj((string) ($cnpj ?? ''));

        if (strlen($cnpj) !== 14) {
            return null;
        }

        $cached = Cache::get($this->mensalidadeCacheKey($cnpj));

        return is_array($cached) ? $cached : null;
    }

    private function mensalidadeCacheKey(string $cnpj): string
    {
        return 'erp.licenca.mensalidade.'.$cnpj;
    }

    public function forgetLoginGate(): void
    {
        session()->forget([
            $this->loginGateKey(),
            'erp.licenca.mensalidade_sync_scheduled',
            'erp.licenca.mensalidade_sync_tried',
        ]);
    }

    public function checkCnpj(string $cnpj, bool $forceRefresh = false): LicencaSnapshot
    {
        $cnpj = $this->normalizeCnpj($cnpj);

        if (strlen($cnpj) !== 14) {
            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_SEM_CNPJ,
                mensagem: 'CNPJ inválido para validação de licença.',
            );
        }

        if (! $forceRefresh && isset(self::$requestMemo[$cnpj])) {
            return self::$requestMemo[$cnpj];
        }

        if ($forceRefresh) {
            $this->forgetCache($cnpj);
            unset(self::$requestMemo[$cnpj]);
        }

        if (! $forceRefresh) {
            // Preferência: gate do login (sem HTTP, sem TTL curto).
            $gateSnapshot = $this->loginGateSnapshot();

            if ($gateSnapshot !== null) {
                self::$requestMemo[$cnpj] = $gateSnapshot;

                return $gateSnapshot;
            }

            $sessionHit = $this->fromSessionFastPath($cnpj);

            if ($sessionHit !== null) {
                self::$requestMemo[$cnpj] = $sessionHit;

                return $sessionHit;
            }

            $cached = Cache::get($this->cacheKey($cnpj));

            if (is_array($cached)) {
                $snapshot = LicencaSnapshot::fromArray($cached, true);
                self::$requestMemo[$cnpj] = $snapshot;
                $this->storeSessionFastPath($cnpj, $snapshot);

                return $snapshot;
            }

            // Grace estática (sem refresh em background — só no login/botão).
            $graceHit = $this->fromGraceFastPath($cnpj);

            if ($graceHit !== null) {
                self::$requestMemo[$cnpj] = $graceHit;
                $this->storeSessionFastPath($cnpj, $graceHit);

                return $graceHit;
            }
        }

        try {
            $snapshot = $this->fetchFromApi($cnpj);
            $ttl = $this->cacheTtlSeconds($snapshot);
            Cache::put($this->cacheKey($cnpj), $snapshot->toArray(), now()->addSeconds($ttl));
            $this->rememberGrace($cnpj, $snapshot);
            $this->storeSessionFastPath($cnpj, $snapshot);
            self::$requestMemo[$cnpj] = $snapshot;

            return $snapshot;
        } catch (Throwable $e) {
            Log::warning('Licença remota indisponível.', [
                'cnpj' => $cnpj,
                'message' => $e->getMessage(),
            ]);

            $snapshot = $this->graceOrUnavailable($cnpj, $e->getMessage());
            self::$requestMemo[$cnpj] = $snapshot;

            return $snapshot;
        }
    }

    public function forgetCurrentEmpresaCache(): void
    {
        $cnpj = $this->currentCnpj();

        if ($cnpj !== null) {
            $this->forgetCache($cnpj);
            unset(self::$requestMemo[$cnpj]);
            session()->forget($this->sessionKey($cnpj));
        }

        $this->forgetLoginGate();
    }

    public function currentCnpj(): ?string
    {
        $cnpj = $this->normalizeCnpj((string) (ErpContext::currentEmpresa()?->cnpj ?? ''));

        return strlen($cnpj) === 14 ? $cnpj : null;
    }

    public function pagamentoUrl(): string
    {
        return $this->resolveBaseUrl() ?: 'https://unitecnologiasc.digital';
    }

    private function fetchFromApi(string $cnpj): LicencaSnapshot
    {
        $baseUrl = $this->resolveBaseUrl();
        $timeout = $this->resolveTimeout();
        $url = $baseUrl.'/api/licenca/'.$cnpj;

        $response = Http::timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->acceptJson()
            ->get($url);

        if ($response->status() === 404) {
            $payload = $response->json();
            $erro = is_array($payload) ? (string) ($payload['erro'] ?? 'Cliente não encontrado') : 'Cliente não encontrado';

            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_NAO_ENCONTRADO,
                mensagem: $erro,
            );
        }

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$response->status().' ao consultar licença.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Resposta de licença inválida.');
        }

        $status = strtolower(trim((string) ($payload['status'] ?? '')));

        if (! in_array($status, [LicencaSnapshot::STATUS_ATIVO, LicencaSnapshot::STATUS_BLOQUEADO], true)) {
            throw new \RuntimeException('Status de licença desconhecido: '.$status);
        }

        return new LicencaSnapshot(
            status: $status,
            validoAte: $this->normalizeValidoAte($payload['valido_ate'] ?? null),
            nome: filled($payload['nome'] ?? null) ? trim((string) $payload['nome']) : null,
        );
    }

    private function normalizeValidoAte(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '' || strtolower($raw) === 'null') {
            return null;
        }

        return $raw;
    }

    private function cacheTtlSeconds(LicencaSnapshot $snapshot): int
    {
        $base = max(60, (int) config('unitec.licenca_api.cache_seconds', 300));

        return match ($snapshot->status) {
            LicencaSnapshot::STATUS_ATIVO => max($base, 600),
            LicencaSnapshot::STATUS_BLOQUEADO,
            LicencaSnapshot::STATUS_NAO_ENCONTRADO => min(60, $base),
            default => $base,
        };
    }

    private function loginGateKey(): string
    {
        return 'erp.licenca.login_gate';
    }

    private function fromSessionFastPath(string $cnpj): ?LicencaSnapshot
    {
        $payload = session($this->sessionKey($cnpj));

        if (! is_array($payload)) {
            return null;
        }

        $until = (int) ($payload['until'] ?? 0);

        if ($until < time()) {
            session()->forget($this->sessionKey($cnpj));

            return null;
        }

        $data = $payload['snapshot'] ?? null;

        return is_array($data) ? LicencaSnapshot::fromArray($data, true) : null;
    }

    private function storeSessionFastPath(string $cnpj, LicencaSnapshot $snapshot): void
    {
        // Cache auxiliar (telas de licença). O gate do login é o que libera o painel.
        $ttl = $snapshot->status === LicencaSnapshot::STATUS_ATIVO
            ? 86400
            : 300;

        session([
            $this->sessionKey($cnpj) => [
                'until' => time() + $ttl,
                'snapshot' => $snapshot->toArray(),
            ],
        ]);
    }

    private function fromGraceFastPath(string $cnpj): ?LicencaSnapshot
    {
        $grace = Cache::get($this->graceKey($cnpj));

        if (! is_array($grace)) {
            return null;
        }

        $snapshot = LicencaSnapshot::fromArray($grace, true);

        if ($snapshot->status === LicencaSnapshot::STATUS_ATIVO) {
            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_ATIVO,
                validoAte: $snapshot->validoAte,
                nome: $snapshot->nome,
                mensagem: 'Usando última validação (sem esperar API).',
                fromCache: true,
            );
        }

        if (in_array($snapshot->status, [
            LicencaSnapshot::STATUS_BLOQUEADO,
            LicencaSnapshot::STATUS_NAO_ENCONTRADO,
        ], true)) {
            return $snapshot;
        }

        return null;
    }

    private function graceOrUnavailable(string $cnpj, string $reason): LicencaSnapshot
    {
        $grace = Cache::get($this->graceKey($cnpj));

        if (is_array($grace)) {
            $snapshot = LicencaSnapshot::fromArray($grace, true);

            if ($snapshot->status === LicencaSnapshot::STATUS_ATIVO) {
                return new LicencaSnapshot(
                    status: LicencaSnapshot::STATUS_ATIVO,
                    validoAte: $snapshot->validoAte,
                    nome: $snapshot->nome,
                    mensagem: 'Usando última validação (API indisponível).',
                    fromCache: true,
                );
            }

            if (in_array($snapshot->status, [
                LicencaSnapshot::STATUS_BLOQUEADO,
                LicencaSnapshot::STATUS_NAO_ENCONTRADO,
            ], true)) {
                return new LicencaSnapshot(
                    status: $snapshot->status,
                    validoAte: $snapshot->validoAte,
                    nome: $snapshot->nome,
                    mensagem: $snapshot->mensagem ?? 'Licença bloqueada (última consulta).',
                    fromCache: true,
                );
            }
        }

        $lastDate = is_array($grace) ? ($grace['valido_ate'] ?? null) : null;
        $lastNome = is_array($grace) && filled($grace['nome'] ?? null) ? (string) $grace['nome'] : null;

        return new LicencaSnapshot(
            status: LicencaSnapshot::STATUS_INDISPONIVEL,
            validoAte: filled($lastDate) ? (string) $lastDate : $this->localFallbackDate(),
            nome: $lastNome,
            mensagem: 'Não foi possível validar a licença agora: '.$reason,
        );
    }

    private function rememberGrace(string $cnpj, LicencaSnapshot $snapshot): void
    {
        $hours = max(1, (int) config('unitec.licenca_api.grace_hours', 24));

        Cache::put($this->graceKey($cnpj), $snapshot->toArray(), now()->addHours($hours));
    }

    private function forgetCache(string $cnpj): void
    {
        Cache::forget($this->cacheKey($cnpj));
        session()->forget($this->sessionKey($cnpj));
    }

    private function cacheKey(string $cnpj): string
    {
        return 'erp.licenca.remota.'.$cnpj;
    }

    private function graceKey(string $cnpj): string
    {
        return 'erp.licenca.remota.grace.'.$cnpj;
    }

    private function sessionKey(string $cnpj): string
    {
        return 'erp.licenca.session.'.$cnpj;
    }

    private function resolveEnabled(): bool
    {
        $empresa = ErpContext::currentEmpresa();

        if ($empresa !== null && array_key_exists('param_licenca_api_habilitar', $empresa->getAttributes())) {
            return (bool) $empresa->param_licenca_api_habilitar;
        }

        $enabled = config('unitec.licenca_api.enabled', true);

        if (is_string($enabled)) {
            $enabled = filter_var($enabled, FILTER_VALIDATE_BOOL);
        }

        return (bool) $enabled;
    }

    private function resolveBaseUrl(): string
    {
        // Portal Unitec é nativo (config) — não grava mais URL na tabela empresas (row size).
        return rtrim(trim((string) config('unitec.licenca_api.base_url', 'https://unitecnologiasc.digital')), '/');
    }

    private function resolveTimeout(): int
    {
        $empresa = ErpContext::currentEmpresa();
        $fromEmpresa = (int) ($empresa?->param_licenca_api_timeout ?? 0);

        if ($fromEmpresa >= 2) {
            return max(2, min(30, $fromEmpresa));
        }

        return max(2, min(30, (int) config('unitec.licenca_api.timeout', 8)));
    }

    private function normalizeCnpj(string $cnpj): string
    {
        return preg_replace('/\D/', '', $cnpj) ?? '';
    }

    private function localFallbackDate(): ?string
    {
        $raw = trim((string) config('unitec.licenca', ''));

        if ($raw === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $raw)->toDateString();
        } catch (Throwable) {
            try {
                return \Carbon\Carbon::parse($raw)->toDateString();
            } catch (Throwable) {
                return null;
            }
        }
    }
}
