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
        $enabled = config('unitec.licenca_api.enabled', true);

        if (is_string($enabled)) {
            $enabled = filter_var($enabled, FILTER_VALIDATE_BOOL);
        }

        if (! $enabled) {
            return false;
        }

        $baseUrl = trim((string) (
            config('unitec.licenca_api.base_url')
            ?: env('UNITEC_LICENCA_API_URL', '')
        ));

        return $baseUrl !== '';
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

        return $this->checkCnpj($cnpj, $forceRefresh);
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

        $memoKey = $cnpj.'|'.($forceRefresh ? '1' : '0');

        if (! $forceRefresh && isset(self::$requestMemo[$cnpj])) {
            return self::$requestMemo[$cnpj];
        }

        if ($forceRefresh) {
            $this->forgetCache($cnpj);
            unset(self::$requestMemo[$cnpj]);
        }

        if (! $forceRefresh) {
            $sessionHit = $this->fromSessionFastPath($cnpj);

            if ($sessionHit !== null) {
                self::$requestMemo[$cnpj] = $sessionHit;

                return $sessionHit;
            }

            $cached = Cache::get($this->cacheKey($cnpj));

            if (is_array($cached)) {
                $snapshot = LicencaSnapshot::fromArray($cached, true);
                self::$requestMemo[$cnpj] = $snapshot;

                return $snapshot;
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
    }

    public function currentCnpj(): ?string
    {
        $cnpj = $this->normalizeCnpj((string) (ErpContext::currentEmpresa()?->cnpj ?? ''));

        return strlen($cnpj) === 14 ? $cnpj : null;
    }

    public function pagamentoUrl(): string
    {
        return trim((string) config('unitec.pagamento_url', ''));
    }

    private function fetchFromApi(string $cnpj): LicencaSnapshot
    {
        $baseUrl = rtrim(trim((string) (
            config('unitec.licenca_api.base_url')
            ?: env('UNITEC_LICENCA_API_URL', '')
        )), '/');
        $timeout = max(3, min(10, (int) config('unitec.licenca_api.timeout', 8)));
        $url = $baseUrl.'/api/licenca/'.$cnpj;

        $response = Http::timeout($timeout)
            ->connectTimeout(min(5, $timeout))
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
        // Sessão evita HTTP em toda navegação do Filament enquanto estiver válido.
        $ttl = $snapshot->status === LicencaSnapshot::STATUS_ATIVO
            ? 600
            : 45;

        session([
            $this->sessionKey($cnpj) => [
                'until' => time() + $ttl,
                'snapshot' => $snapshot->toArray(),
            ],
        ]);
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
