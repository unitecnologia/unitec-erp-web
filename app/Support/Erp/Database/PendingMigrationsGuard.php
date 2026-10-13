<?php

namespace App\Support\Erp\Database;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Verificação leve de compatibilidade código × schema.
 *
 * Não executa migrate (isso fica no atualizador oficial / Reparar Sistema).
 * Cache positivo só com fingerprint (arquivos + linhas em migrations): restore
 * ou novos arquivos invalidam o OK sem esperar TTL.
 * Não cacheia falha. Nunca trata lock/miss como OK.
 */
final class PendingMigrationsGuard
{
    private const CACHE_OK_KEY = 'erp.schema.checked';

    private const CACHE_UI_STATUS_KEY = 'erp.schema.ui_status';

    private const LOCK_KEY = 'erp.schema.check';

    private const CACHE_OK_TTL_SECONDS = 300;

    /**
     * @return array{ok: bool, pending: int, message: ?string, from_cache: bool, transient?: bool}
     */
    public function check(bool $forceRefresh = false): array
    {
        if (! $forceRefresh) {
            $cachedOk = $this->cachedOkStillValid();
            if ($cachedOk !== null) {
                return $cachedOk;
            }
        }

        $lock = Cache::lock(self::LOCK_KEY, 15);

        if (! $lock->get()) {
            return [
                'ok' => false,
                'pending' => -1,
                'message' => 'Verificando compatibilidade do banco… Aguarde alguns segundos e tente de novo.',
                'from_cache' => false,
                'transient' => true,
            ];
        }

        try {
            if (! $forceRefresh) {
                $cachedOk = $this->cachedOkStillValid();
                if ($cachedOk !== null) {
                    return $cachedOk;
                }
            }

            return $this->inspectAndCache();
        } catch (Throwable $e) {
            $message = 'Falha ao verificar schema do banco: '.$e->getMessage();
            Log::error($message);
            Cache::forget(self::CACHE_OK_KEY);
            $this->storeSessionError($message);

            return [
                'ok' => false,
                'pending' => -1,
                'message' => $message,
                'from_cache' => false,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Confirma Pending = 0 (após migrate no apply). Lança se incompatível.
     *
     * @throws \RuntimeException
     */
    public function assertZeroPending(): void
    {
        $this->invalidate();

        $lastMessage = null;

        for ($attempt = 1; $attempt <= 12; $attempt++) {
            $result = $this->check(forceRefresh: true);

            if ($result['ok']) {
                $this->markCompatible();

                return;
            }

            $lastMessage = $result['message'] ?? $this->defaultIncompatibleMessage(max(0, $result['pending']));

            if (($result['transient'] ?? false) === true) {
                usleep(250_000);

                continue;
            }

            throw new \RuntimeException($lastMessage);
        }

        throw new \RuntimeException(
            $lastMessage ?? 'Não foi possível confirmar o schema após o migrate (lock ocupado).'
        );
    }

    public function markCompatible(): void
    {
        Cache::forget('erp.schema.migrate_error');
        Cache::forget('erp.schema.incompatible');

        try {
            $fingerprint = $this->quickFingerprint();
            Cache::put(self::CACHE_OK_KEY, $fingerprint, self::CACHE_OK_TTL_SECONDS);
            $this->storeUiStatus([
                'fingerprint' => $fingerprint,
                'ok' => true,
                'pending' => 0,
                'last_migration' => $this->lastAppliedMigrationName(),
            ]);
        } catch (Throwable) {
            Cache::forget(self::CACHE_OK_KEY);
            Cache::forget(self::CACHE_UI_STATUS_KEY);
        }

        try {
            if (app()->bound('session')) {
                session()->forget('erp_update_choice_skipped');
            }
        } catch (Throwable) {
            // ignore
        }
    }

    public function invalidate(): void
    {
        Cache::forget(self::CACHE_OK_KEY);
        Cache::forget(self::CACHE_UI_STATUS_KEY);
        Cache::forget('erp.schema.incompatible');
        Cache::forget('erp.schema.migrate_error');
    }

    /**
     * Indicador compacto para login/barra (cache compartilhado; sem migrate).
     * Nunca retorna "Atualizado" sem confirmação (fingerprint + Pending=0).
     *
     * @return array{ok: bool, pending: int, label: string, last_migration: ?string, tooltip: string, from_cache: bool, state: string}
     */
    public function statusForUi(): array
    {
        // Memo só na request atual (FrankenPHP reutiliza o worker entre requests).
        if (app()->bound('request')) {
            $memo = request()->attributes->get('erp.schema.ui_status');
            if (is_array($memo)) {
                return $memo;
            }
        }

        $remember = static function (array $status): array {
            if (app()->bound('request')) {
                request()->attributes->set('erp.schema.ui_status', $status);
            }

            return $status;
        };

        try {
            $fingerprint = $this->quickFingerprint();
        } catch (Throwable) {
            return $remember($this->formatUiStatus([
                'ok' => false,
                'pending' => -1,
                'last_migration' => null,
                'unavailable' => true,
            ], fromCache: false));
        }

        $cached = Cache::get(self::CACHE_UI_STATUS_KEY);
        if (is_array($cached) && ($cached['fingerprint'] ?? '') === $fingerprint) {
            // Só aceita "ok" do cache se Pending=0 estiver explícito.
            if (($cached['ok'] ?? false) === true && (int) ($cached['pending'] ?? -1) === 0) {
                return $remember($this->formatUiStatus($cached, fromCache: true));
            }
            if (($cached['ok'] ?? false) === false && (int) ($cached['pending'] ?? 0) > 0) {
                return $remember($this->formatUiStatus($cached, fromCache: true));
            }
            // Cache ambíguo (indisponível/antigo): revalida abaixo.
        }

        $result = $this->check();

        if (($result['transient'] ?? false) === true) {
            // Nunca reaproveitar "Atualizado" sem nova confirmação.
            return $remember($this->formatUiStatus([
                'ok' => false,
                'pending' => -1,
                'last_migration' => is_array($cached) ? ($cached['last_migration'] ?? null) : null,
                'unavailable' => true,
            ], fromCache: false));
        }

        try {
            $fingerprint = $this->quickFingerprint();
        } catch (Throwable) {
            return $remember($this->formatUiStatus([
                'ok' => false,
                'pending' => -1,
                'last_migration' => null,
                'unavailable' => true,
            ], fromCache: false));
        }

        $pending = (int) $result['pending'];
        $ok = (bool) $result['ok'] && $pending === 0;

        if (! $ok && $pending <= 0) {
            $payload = [
                'fingerprint' => $fingerprint,
                'ok' => false,
                'pending' => -1,
                'last_migration' => $this->lastAppliedMigrationName(),
                'unavailable' => true,
            ];
            $this->storeUiStatus($payload);

            return $remember($this->formatUiStatus($payload, fromCache: false));
        }

        $payload = [
            'fingerprint' => $fingerprint,
            'ok' => $ok,
            'pending' => $pending,
            'last_migration' => $this->lastAppliedMigrationName(),
        ];
        $this->storeUiStatus($payload);

        return $remember($this->formatUiStatus($payload, fromCache: false));
    }

    public function repairGuidanceMessage(int $pendingCount = 0): string
    {
        return $this->defaultIncompatibleMessage($pendingCount);
    }

    /**
     * @return array{ok: bool, pending: int, message: ?string, from_cache: bool}|null
     */
    private function cachedOkStillValid(): ?array
    {
        $cached = Cache::get(self::CACHE_OK_KEY);

        // Formato antigo (bool true): forçar revalidação completa uma vez.
        if ($cached === true) {
            Cache::forget(self::CACHE_OK_KEY);

            return null;
        }

        if (! is_string($cached) || $cached === '') {
            return null;
        }

        try {
            if ($cached !== $this->quickFingerprint()) {
                Cache::forget(self::CACHE_OK_KEY);

                return null;
            }
        } catch (Throwable) {
            Cache::forget(self::CACHE_OK_KEY);

            return null;
        }

        return [
            'ok' => true,
            'pending' => 0,
            'message' => null,
            'from_cache' => true,
        ];
    }

    /**
     * Impressão barata: qtd de arquivos de migration + linhas na tabela.
     * Restore antigo ou pacote com arquivos novos muda o valor e derruba o cache OK.
     */
    private function quickFingerprint(): string
    {
        $dir = database_path('migrations');
        $fileCount = is_dir($dir) ? count(glob($dir.DIRECTORY_SEPARATOR.'*.php') ?: []) : 0;

        if (! Schema::hasTable('migrations')) {
            return $fileCount.':missing';
        }

        $ranCount = (int) DB::table('migrations')->count();

        return $fileCount.':'.$ranCount;
    }

    /**
     * @return array{ok: bool, pending: int, message: ?string, from_cache: bool}
     */
    private function inspectAndCache(): array
    {
        if (! Schema::hasTable('migrations')) {
            $message = 'Tabela de migrations ausente — banco incompleto ou restore inválido. '
                .'Feche o ERP e execute Reparar Sistema.bat (ou reinstale preservando dados).';
            Cache::forget(self::CACHE_OK_KEY);
            $this->storeSessionError($message);
            Log::error($message);

            try {
                $this->storeUiStatus([
                    'fingerprint' => $this->quickFingerprint(),
                    'ok' => false,
                    'pending' => -1,
                    'last_migration' => null,
                    'unavailable' => true,
                ]);
            } catch (Throwable) {
                Cache::forget(self::CACHE_UI_STATUS_KEY);
            }

            return [
                'ok' => false,
                'pending' => -1,
                'message' => $message,
                'from_cache' => false,
            ];
        }

        $pending = $this->pendingMigrationFiles();
        $pendingCount = count($pending);

        if ($pendingCount === 0) {
            $this->markCompatible();

            return [
                'ok' => true,
                'pending' => 0,
                'message' => null,
                'from_cache' => false,
            ];
        }

        $message = $this->defaultIncompatibleMessage($pendingCount);
        Cache::forget(self::CACHE_OK_KEY);
        Cache::forget('erp.schema.incompatible');
        $this->storeSessionError($message);
        Log::warning($message, ['pending' => $pendingCount]);

        try {
            $this->storeUiStatus([
                'fingerprint' => $this->quickFingerprint(),
                'ok' => false,
                'pending' => $pendingCount,
                'last_migration' => $this->lastAppliedMigrationName(),
            ]);
        } catch (Throwable) {
            Cache::forget(self::CACHE_UI_STATUS_KEY);
        }

        return [
            'ok' => false,
            'pending' => $pendingCount,
            'message' => $message,
            'from_cache' => false,
        ];
    }

    /**
     * @param  array{fingerprint?: string, ok?: bool, pending?: int, last_migration?: ?string, transient?: bool, unavailable?: bool}  $payload
     * @return array{ok: bool, pending: int, label: string, last_migration: ?string, tooltip: string, from_cache: bool, state: string}
     */
    private function formatUiStatus(array $payload, bool $fromCache): array
    {
        $pending = (int) ($payload['pending'] ?? 0);
        $ok = (bool) ($payload['ok'] ?? false) && $pending === 0;
        $unavailable = (bool) ($payload['unavailable'] ?? false)
            || (bool) ($payload['transient'] ?? false)
            || (! $ok && $pending <= 0);
        $last = isset($payload['last_migration']) && is_string($payload['last_migration']) && $payload['last_migration'] !== ''
            ? $payload['last_migration']
            : null;

        if ($ok) {
            $state = 'ok';
            $label = 'BD: Atualizado';
            $tooltip = $last !== null ? 'Última migration: '.$last : 'Schema alinhado ao código.';
        } elseif ($pending > 0) {
            $state = 'pending';
            $label = 'BD: Pendente ('.$pending.')';
            $tooltip = $last !== null
                ? 'Última migration: '.$last
                : 'Há migrations pendentes em relação ao código.';
        } else {
            $state = 'unavailable';
            $label = 'BD: Indisponível';
            $tooltip = $last !== null
                ? 'Não foi possível confirmar o schema. Última migration conhecida: '.$last
                : 'Não foi possível verificar a situação do banco.';
            $unavailable = true;
            $ok = false;
        }

        return [
            'ok' => $ok,
            'pending' => max(0, $pending),
            'label' => $label,
            'last_migration' => $last,
            'tooltip' => $tooltip,
            'from_cache' => $fromCache,
            'state' => $state,
        ];
    }

    /**
     * @param  array{fingerprint: string, ok: bool, pending: int, last_migration: ?string, unavailable?: bool}  $payload
     */
    private function storeUiStatus(array $payload): void
    {
        Cache::put(self::CACHE_UI_STATUS_KEY, [
            'fingerprint' => (string) ($payload['fingerprint'] ?? ''),
            'ok' => (bool) ($payload['ok'] ?? false),
            'pending' => (int) ($payload['pending'] ?? 0),
            'last_migration' => $payload['last_migration'] ?? null,
            'unavailable' => (bool) ($payload['unavailable'] ?? false),
        ], self::CACHE_OK_TTL_SECONDS);
    }

    private function lastAppliedMigrationName(): ?string
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return null;
            }

            $name = DB::table('migrations')->orderByDesc('id')->value('migration');

            return is_string($name) && $name !== '' ? $name : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<string>
     */
    private function pendingMigrationFiles(): array
    {
        /** @var \Illuminate\Database\Migrations\Migrator $migrator */
        $migrator = app('migrator');

        $paths = array_values(array_unique(array_filter(array_merge(
            [database_path('migrations')],
            $migrator->paths(),
        ))));

        if (! $migrator->repositoryExists()) {
            return array_keys($migrator->getMigrationFiles($paths));
        }

        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    private function defaultIncompatibleMessage(int $pendingCount): string
    {
        $prefix = $pendingCount > 0
            ? 'Banco incompatível com esta versão: '.$pendingCount.' migration(s) pendente(s).'
            : 'Banco incompatível com esta versão do sistema.';

        return $prefix
            .' Não use o ERP até corrigir. Se houver atualização pendente no login, aceite para aplicar o migrate.'
            .' Caso contrário, execute Reparar Sistema.bat (aplica migrate --force sem apagar dados).';
    }

    private function storeSessionError(string $message): void
    {
        try {
            if (app()->bound('session')) {
                session()->flash('erp_migrate_error', $message);
            }
            Cache::put('erp.schema.migrate_error', $message, 120);
        } catch (Throwable) {
            // ignore
        }
    }
}
