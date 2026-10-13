<?php

namespace App\Support\Erp\License;

use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class LicencaRemotaService
{
    /** @var array<string, LicencaSnapshot> */
    private static array $requestMemo = [];

    /** @var array<string, mixed>|null Último JSON bruto de GET /api/licenca/{cnpj} nesta requisição. */
    private static ?array $lastApiPayload = null;

    /** @var array{http_status: int, url: string, cnpj: string, body: array<string, mixed>|null, error?: string}|null */
    private static ?array $lastApiFetch = null;

    /** @var array{allowed: bool, field: string, detail: string}|null */
    private static ?array $lastAccessDecision = null;

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

        // Licença em cache/grace ainda vale e bloqueia se estiver vencida ou bloqueada.
        // Sem cache, consulta o portal uma vez. A mensalidade já em cache também bloqueia;
        // só busca o vencimento no portal quando ainda não há cache.
        $snapshot = $this->checkCnpj($cnpj, forceRefresh: false);
        $this->rememberLoginGate($snapshot);
        $this->hydrateMensalidadeFromCache($cnpj);

        if ($this->mensalidadeFromCache($cnpj) === null) {
            $this->syncMensalidadeNoGate($cnpj);
        }

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

            if ($this->loginGateMensalidadeDueDate() === null) {
                $this->syncMensalidadeNoGate($this->currentCnpj());
            }

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

            if ($this->loginGateMensalidadeDueDate() === null) {
                $this->syncMensalidadeNoGate($cnpj);
            }

            return $this->applyMensalidadeExpiry($local);
        }

        // Sem cache local: não bloqueia o menu com HTTP. Valida de verdade no login.
        $snapshot = new LicencaSnapshot(
            status: LicencaSnapshot::STATUS_INDISPONIVEL,
            validoAte: $this->localFallbackDate(),
            mensagem: 'Licença será revalidada no próximo login.',
        );
        $this->rememberLoginGate($snapshot);
        $this->hydrateMensalidadeFromCache($cnpj);

        if ($this->loginGateMensalidadeDueDate() === null) {
            $this->syncMensalidadeNoGate($cnpj);
        }

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

        $snapshot = $this->loginGateSnapshot();

        if ($snapshot !== null) {
            return $this->resolveAccess($snapshot)['allowed'];
        }

        return (bool) ($gate['allowed'] ?? false);
    }

    /**
     * True quando a janela local (vencimento + grace_days da config) já acabou.
     * O vencimento sozinho, dentro dos dias de tolerância, não bloqueia.
     * Não usa valido_ate do portal quando há mensalidade (evita tolerância antiga em cache).
     */
    public function mensalidadeVencida(): bool
    {
        $due = trim((string) ($this->loginGateMensalidadeDueDate() ?? ''));

        // Com vencimento conhecido: só grace_days local. Sem vencimento: fallback valido_ate.
        return LicencaLiberacao::bloqueadoPorData(
            $due !== '' ? $due : null,
            $due !== '' ? null : $this->loginGateSnapshot()?->validoAte,
        );
    }

    /**
     * Se a janela local (vencimento + grace_days) já acabou, força status bloqueado.
     * Vale também quando o portal ainda devolve status=ativo (tolerância antiga no portal).
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
            bloquearAtualizacao: $snapshot->bloquearAtualizacao,
            quantidadeComputadores: $snapshot->quantidadeComputadores,
            quantidadeTelefones: $snapshot->quantidadeTelefones,
            fromCache: $snapshot->fromCache,
            modoAtualizacao: $snapshot->modoAtualizacao,
        );
    }

    /**
     * Decisão de liberação + campo usado (para diagnóstico na tela de bloqueio).
     *
     * @return array{allowed: bool, field: string, detail: string}
     */
    public function resolveAccess(LicencaSnapshot $snapshot): array
    {
        // Janela local sempre prevalece: não reaproveita valido_ate/tolerância antiga do portal.
        if ($this->mensalidadeVencida()) {
            $due = (string) ($this->loginGateMensalidadeDueDate() ?? '');
            $liberadoAte = LicencaLiberacao::liberadoAte(
                $due !== '' ? $due : null,
                $due !== '' ? null : $snapshot->validoAte,
            );

            return self::$lastAccessDecision = [
                'allowed' => false,
                'field' => 'mensalidade_due_date+grace_days',
                'detail' => 'liberado_ate='.($liberadoAte?->toDateString() ?? 'null')
                    .'; mensalidade_due_date='.($due !== '' ? $due : 'null')
                    .'; grace_days='.LicencaLiberacao::graceDays(),
            ];
        }

        if ($snapshot->status === LicencaSnapshot::STATUS_ATIVO) {
            return self::$lastAccessDecision = [
                'allowed' => true,
                'field' => 'status',
                'detail' => 'ativo',
            ];
        }

        if (in_array($snapshot->status, [
            LicencaSnapshot::STATUS_DESABILITADO,
            LicencaSnapshot::STATUS_INDISPONIVEL,
        ], true)) {
            return self::$lastAccessDecision = [
                'allowed' => true,
                'field' => 'status',
                'detail' => $snapshot->status,
            ];
        }

        if ($snapshot->status === LicencaSnapshot::STATUS_BLOQUEADO) {
            return self::$lastAccessDecision = [
                'allowed' => false,
                'field' => 'status',
                'detail' => 'bloqueado',
            ];
        }

        return self::$lastAccessDecision = [
            'allowed' => false,
            'field' => 'status',
            'detail' => $snapshot->status,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastApiPayload(): ?array
    {
        return self::$lastApiPayload;
    }

    /**
     * @return array{http_status: int, url: string, cnpj: string, body: array<string, mixed>|null, error?: string}|null
     */
    public function lastApiFetch(): ?array
    {
        if (self::$lastApiFetch !== null) {
            return self::$lastApiFetch;
        }

        $cnpj = $this->currentCnpj();
        if ($cnpj === null) {
            return null;
        }

        $cached = Cache::get($this->lastFetchCacheKey($cnpj));

        return is_array($cached) ? $cached : null;
    }

    /**
     * @return array{allowed: bool, field: string, detail: string}|null
     */
    public function lastAccessDecision(): ?array
    {
        return self::$lastAccessDecision;
    }

    /**
     * Diagnóstico persistido (sobrevive a redirect/remount da tela de bloqueio).
     *
     * @return array{at: string, http_status: int|null, status: mixed, valido_ate: mixed, vencimento: mixed, decision_field: string, decision_detail: string, body: array<string, mixed>|null}|null
     */
    public function pullPersistedDiagnostico(): ?array
    {
        $data = session()->pull('erp.licenca.last_diagnostico');

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array{allowed: bool, field: string, detail: string}  $decision
     */
    public function persistDiagnostico(array $decision): void
    {
        $fetch = $this->lastApiFetch();
        $body = is_array($fetch['body'] ?? null) ? $fetch['body'] : self::$lastApiPayload;

        $diag = [
            'at' => now()->toDateTimeString(),
            'http_status' => isset($fetch['http_status']) ? (int) $fetch['http_status'] : null,
            'status' => is_array($body) ? ($body['status'] ?? null) : null,
            'valido_ate' => is_array($body) ? ($body['valido_ate'] ?? null) : null,
            'vencimento' => is_array($body) ? ($body['vencimento'] ?? null) : null,
            'decision_field' => (string) ($decision['field'] ?? ''),
            'decision_detail' => (string) ($decision['detail'] ?? ''),
            'body' => $body,
        ];

        session(['erp.licenca.last_diagnostico' => $diag]);

        Log::warning('erp.licenca.verificacao', $diag);
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

    /**
     * Data para o KPI do dashboard (sem HTTP): mensalidade em cache, senão valido_ate da última consulta.
     */
    public function lastKnownValidoAte(): ?string
    {
        $this->hydrateMensalidadeFromCache($this->currentCnpj());

        $due = trim((string) ($this->loginGateMensalidadeDueDate() ?? ''));

        if ($due !== '') {
            return $due;
        }

        $cnpj = $this->currentCnpj();

        if ($cnpj === null) {
            return $this->localFallbackDate();
        }

        $cached = Cache::get($this->cacheKey($cnpj));

        if (is_array($cached) && filled($cached['valido_ate'] ?? null)) {
            return (string) $cached['valido_ate'];
        }

        $grace = Cache::get($this->graceKey($cnpj));

        if (is_array($grace) && filled($grace['valido_ate'] ?? null)) {
            return (string) $grace['valido_ate'];
        }

        return $this->localFallbackDate();
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

        // resolveAccess já aplica grace_days local (mesmo com status=ativo do portal).
        $allowed = $this->resolveAccess($snapshot)['allowed'];

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
                'mensalidade_pending' => $previous['mensalidade_pending'] ?? null,
            ],
        ]);
    }

    public function rememberMensalidadeGate(
        ?string $dueDate,
        ?string $description = null,
        ?string $amount = null,
        ?string $cnpj = null,
        ?bool $pending = null,
    ): void {
        $gate = session($this->loginGateKey());

        if (! is_array($gate)) {
            $gate = [];
        }

        $dueDate = trim((string) $dueDate);
        $gate['mensalidade_due_date'] = $dueDate !== '' ? $dueDate : null;
        $gate['mensalidade_description'] = filled($description) ? trim((string) $description) : ($gate['mensalidade_description'] ?? null);
        $gate['mensalidade_amount'] = filled($amount) ? trim((string) $amount) : ($gate['mensalidade_amount'] ?? null);
        if ($pending !== null) {
            $gate['mensalidade_pending'] = $pending;
        } elseif ($dueDate === '') {
            $gate['mensalidade_pending'] = null;
        }

        session([$this->loginGateKey() => $gate]);

        $cnpj = $this->normalizeCnpj((string) ($cnpj ?? $this->currentCnpj() ?? ''));

        if (strlen($cnpj) === 14) {
            if ($dueDate !== '') {
                Cache::put($this->mensalidadeCacheKey($cnpj), [
                    'due_date' => $dueDate,
                    'description' => $gate['mensalidade_description'],
                    'amount' => $gate['mensalidade_amount'],
                    'pending' => $gate['mensalidade_pending'] ?? null,
                ], now()->addHours(12));
            } else {
                Cache::forget($this->mensalidadeCacheKey($cnpj));
            }
        }

        // Reavalia bloqueio com a nova data (ex.: mensalidade vencida).
        $current = $this->loginGateSnapshot();
        if ($current !== null) {
            $this->rememberLoginGate($current);
        }
    }

    public function loginGateMensalidadeIsPending(): ?bool
    {
        $gate = session($this->loginGateKey());

        if (! is_array($gate) || ! array_key_exists('mensalidade_pending', $gate)) {
            $cached = $this->mensalidadeFromCache($this->currentCnpj());

            if (is_array($cached) && array_key_exists('pending', $cached)) {
                return $cached['pending'] === null ? null : (bool) $cached['pending'];
            }

            return null;
        }

        return $gate['mensalidade_pending'] === null ? null : (bool) $gate['mensalidade_pending'];
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
            $cnpj,
            array_key_exists('pending', $cached) ? (bool) $cached['pending'] : null,
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
                $cnpj,
                (bool) ($info['pending'] ?? true),
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
            'erp.licenca.aviso_startup_shown',
        ]);
    }

    /**
     * Aviso de vencimento para o início da sessão (Dashboard).
     *
     * @return array{title: string, body: ?string, level: string}|null
     */
    public function avisoStartup(): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $this->hydrateMensalidadeFromCache($this->currentCnpj());

        $due = trim((string) ($this->loginGateMensalidadeDueDate() ?? ''));

        // Com mensalidade: aviso usa só vencimento + grace_days (ignora valido_ate antigo).
        return LicencaLiberacao::avisoStartup(
            $due !== '' ? $due : null,
            $due !== '' ? null : $this->loginGateSnapshot()?->validoAte,
        );
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
            $baseUrl = $this->resolveBaseUrl();
            $this->rememberApiFetch(
                $cnpj,
                $baseUrl.'/api/licenca/'.$cnpj,
                0,
                self::$lastApiPayload,
                $e->getMessage(),
            );

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

    private function fetchFromApi(string $cnpj): LicencaSnapshot
    {
        $baseUrl = $this->resolveBaseUrl();
        $timeout = $this->resolveTimeout();
        $url = $baseUrl.'/api/licenca/'.$cnpj;

        $response = LicencaHttpClient::make()
            ->timeout($timeout)
            ->connectTimeout(min(3, $timeout))
            ->acceptJson()
            ->get($url);

        $httpStatus = $response->status();
        /** @var array<string, mixed>|null $payload */
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : null;

        $this->rememberApiFetch($cnpj, $url, $httpStatus, $payload);

        if ($httpStatus === 404) {
            $erro = is_array($payload) ? (string) ($payload['erro'] ?? 'Cliente não encontrado') : 'Cliente não encontrado';

            return new LicencaSnapshot(
                status: LicencaSnapshot::STATUS_NAO_ENCONTRADO,
                mensagem: $erro,
            );
        }

        if (! $response->successful()) {
            throw new \RuntimeException('HTTP '.$httpStatus.' ao consultar licença.');
        }

        if (! is_array($payload)) {
            throw new \RuntimeException('Resposta de licença inválida.');
        }

        self::$lastApiPayload = $payload;

        $status = strtolower(trim((string) ($payload['status'] ?? '')));
        // status=ativo do portal não ignora a janela local (vencimento + grace_days).
        // Só força bloqueio por flag quando o status não veio como ativo.
        $bloqueado = filter_var($payload['bloqueado'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($bloqueado && $status !== LicencaSnapshot::STATUS_ATIVO) {
            $status = LicencaSnapshot::STATUS_BLOQUEADO;
        }

        if (! in_array($status, [LicencaSnapshot::STATUS_ATIVO, LicencaSnapshot::STATUS_BLOQUEADO], true)) {
            throw new \RuntimeException('Status de licença desconhecido: '.$status);
        }

        $validoAte = $this->normalizeValidoAte($payload['valido_ate'] ?? null);
        $vencimento = $this->normalizeValidoAte($payload['vencimento'] ?? null);

        // Alinha KPI local ao vencimento do portal sem reavaliar o gate (evita snapshot antigo).
        if ($vencimento !== null && (
            $status === LicencaSnapshot::STATUS_ATIVO
            || trim((string) ($this->loginGateMensalidadeDueDate() ?? '')) === ''
        )) {
            $this->storeMensalidadeDueOnly($cnpj, $vencimento);
        }

        Log::warning('erp.licenca.api_resposta', [
            'cnpj' => $cnpj,
            'http_status' => $httpStatus,
            'status' => $payload['status'] ?? null,
            'valido_ate' => $payload['valido_ate'] ?? null,
            'vencimento' => $payload['vencimento'] ?? null,
            'url' => $url,
        ]);

        return new LicencaSnapshot(
            status: $status,
            validoAte: $validoAte,
            nome: filled($payload['nome'] ?? null) ? trim((string) $payload['nome']) : null,
            bloquearAtualizacao: filter_var($payload['bloquear_atualizacao'] ?? false, FILTER_VALIDATE_BOOLEAN),
            quantidadeComputadores: $this->normalizeQuota($payload['quantidade_computadores'] ?? null),
            quantidadeTelefones: $this->normalizeQuota($payload['quantidade_telefones'] ?? null),
            modoAtualizacao: LicencaSnapshot::normalizeModoAtualizacao($payload['modo_atualizacao'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function rememberApiFetch(string $cnpj, string $url, int $httpStatus, ?array $body, ?string $error = null): void
    {
        $fetch = [
            'http_status' => $httpStatus,
            'url' => $url,
            'cnpj' => $cnpj,
            'body' => $body,
            'at' => now()->toDateTimeString(),
        ];

        if ($error !== null) {
            $fetch['error'] = $error;
        }

        self::$lastApiFetch = $fetch;
        self::$lastApiPayload = $body;
        Cache::put($this->lastFetchCacheKey($cnpj), $fetch, now()->addHours(6));
    }

    private function lastFetchCacheKey(string $cnpj): string
    {
        return 'erp.licenca.last_fetch.'.$cnpj;
    }

    /**
     * Atualiza só a data de mensalidade (sessão + cache), sem regravar o snapshot do gate.
     */
    private function storeMensalidadeDueOnly(string $cnpj, string $dueDate): void
    {
        $gate = session($this->loginGateKey());
        if (! is_array($gate)) {
            $gate = [];
        }

        $gate['mensalidade_due_date'] = $dueDate;
        session([$this->loginGateKey() => $gate]);

        Cache::put($this->mensalidadeCacheKey($cnpj), [
            'due_date' => $dueDate,
            'description' => $gate['mensalidade_description'] ?? null,
            'amount' => $gate['mensalidade_amount'] ?? null,
            'pending' => $gate['mensalidade_pending'] ?? null,
        ], now()->addHours(12));
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

    private function normalizeQuota(mixed $value): ?int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'null') {
            return null;
        }

        return max(0, (int) $value);
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
                bloquearAtualizacao: $snapshot->bloquearAtualizacao,
                quantidadeComputadores: $snapshot->quantidadeComputadores,
                quantidadeTelefones: $snapshot->quantidadeTelefones,
                fromCache: true,
                modoAtualizacao: $snapshot->modoAtualizacao,
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
                    bloquearAtualizacao: $snapshot->bloquearAtualizacao,
                    quantidadeComputadores: $snapshot->quantidadeComputadores,
                    quantidadeTelefones: $snapshot->quantidadeTelefones,
                    fromCache: true,
                    modoAtualizacao: $snapshot->modoAtualizacao,
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
                    bloquearAtualizacao: $snapshot->bloquearAtualizacao,
                    quantidadeComputadores: $snapshot->quantidadeComputadores,
                    quantidadeTelefones: $snapshot->quantidadeTelefones,
                    fromCache: true,
                    modoAtualizacao: $snapshot->modoAtualizacao,
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
        // Timeout fixo (config) — não há mais parâmetro por empresa na tela.
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
