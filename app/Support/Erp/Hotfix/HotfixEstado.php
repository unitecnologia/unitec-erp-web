<?php

namespace App\Support\Erp\Hotfix;

use App\Support\Erp\License\LicencaSnapshot;
use Illuminate\Support\Facades\File;

/**
 * Estado local dos hotfixes em storage/app/hotfixes (o atualizador oficial nunca apaga storage/).
 *
 * estado.json    hotfix aplicado na versão instalada, pacotes recusados e última verificação
 * permissao.json último modo_atualizacao visto por CNPJ (só evita consultas; não autoriza)
 * historico.jsonl uma linha por aplicação, falha ou reversão
 */
final class HotfixEstado
{
    /** @var resource|null */
    private static $lock = null;

    public static function root(): string
    {
        return storage_path('app'.DIRECTORY_SEPARATOR.'hotfixes');
    }

    public static function path(string $relativo): string
    {
        return self::root().DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativo);
    }

    /**
     * @return array<string, mixed>
     */
    public static function ler(): array
    {
        return self::lerJson(self::path('estado.json'));
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    public static function gravar(array $estado): void
    {
        self::gravarJson(self::path('estado.json'), $estado);
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     */
    public static function atualizar(array $alteracoes): void
    {
        self::gravar(array_merge(self::ler(), $alteracoes));
    }

    /**
     * Último modo_atualizacao visto por CNPJ. Só serve para evitar consultas inúteis ao portal;
     * a autorização real é sempre refeita para todas as empresas no momento da aplicação.
     *
     * @return array<string, ?string> cnpj => modo
     */
    public static function permissoes(): array
    {
        $data = self::lerJson(self::path('permissao.json'));

        if (isset($data['cnpj'])) {
            return [(string) $data['cnpj'] => $data['modo'] ?? null];
        }

        $modos = is_array($data['modos'] ?? null) ? $data['modos'] : [];

        return array_map(static fn (mixed $m): ?string => is_string($m) ? $m : null, $modos);
    }

    public static function algumaPermiteHotfix(): bool
    {
        return in_array(LicencaSnapshot::MODO_ATUALIZACAO_HOTFIX, self::permissoes(), true);
    }

    public static function lembrarPermissao(string $cnpj, ?string $modo): void
    {
        self::lembrarPermissoes([$cnpj => $modo]);
    }

    /**
     * @param  array<string, ?string>  $modos
     * @param  bool  $substituir  true quando $modos contém todas as empresas ativas
     */
    public static function lembrarPermissoes(array $modos, bool $substituir = false): void
    {
        $atual = self::permissoes();
        $novo = $substituir ? $modos : array_merge($atual, $modos);
        ksort($novo);
        ksort($atual);

        if ($novo === $atual) {
            return;
        }

        self::gravarJson(self::path('permissao.json'), [
            'modos' => $novo,
            'em' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $evento
     */
    public static function registrarHistorico(array $evento): void
    {
        try {
            File::ensureDirectoryExists(self::root());
            $evento = ['em' => now()->toIso8601String(), ...$evento];
            @file_put_contents(
                self::path('historico.jsonl'),
                json_encode($evento, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        } catch (\Throwable) {
        }
    }

    public static function segundosDesdeUltimaVerificacao(): ?int
    {
        $em = (int) (self::ler()['ultima_verificacao_ts'] ?? 0);

        return $em > 0 ? max(0, time() - $em) : null;
    }

    /**
     * Trava exclusiva entre processos (login, agendador e linha de comando).
     */
    public static function adquirirLock(): bool
    {
        if (self::$lock !== null) {
            return true;
        }

        File::ensureDirectoryExists(self::root());
        $handle = @fopen(self::path('hotfix.lock'), 'c');

        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        self::$lock = $handle;

        return true;
    }

    public static function liberarLock(): void
    {
        if (self::$lock === null) {
            return;
        }

        flock(self::$lock, LOCK_UN);
        fclose(self::$lock);
        self::$lock = null;
    }

    public static function lockOcupado(): bool
    {
        if (! self::adquirirLock()) {
            return true;
        }

        self::liberarLock();

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private static function lerJson(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function gravarJson(string $path, array $data): void
    {
        File::ensureDirectoryExists(dirname($path));
        $tmp = $path.'.tmp';
        file_put_contents($tmp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);

        if (! @rename($tmp, $path)) {
            @copy($tmp, $path);
            @unlink($tmp);
        }
    }
}
