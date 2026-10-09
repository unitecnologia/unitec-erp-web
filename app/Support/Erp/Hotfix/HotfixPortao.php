<?php

namespace App\Support\Erp\Hotfix;

/**
 * Segura novas requisições HTTP enquanto o hotfix troca arquivos, para nenhuma requisição
 * carregar metade dos arquivos novos e metade dos antigos.
 *
 * Usa o gancho nativo do public/index.php (storage/framework/maintenance.php é incluído antes do
 * autoload, se existir). O arquivo gerado só espera: não mostra página de manutenção e não
 * responde 503. Se o hotfix morrer no meio, o portão expira sozinho (mtime > 120 s ou 20 s de espera).
 */
final class HotfixPortao
{
    private const MARCADOR = 'UNITEC-HOTFIX-PORTAO';

    /** Tempo para requisições já em andamento terminarem antes da troca. */
    private const ESPERA_EM_CURSO_MICROS = 1_500_000;

    public static function path(): string
    {
        return storage_path('framework'.DIRECTORY_SEPARATOR.'maintenance.php');
    }

    /**
     * @return bool true se o portão foi aberto por este processo (fechar depois)
     */
    public static function abrir(): bool
    {
        $path = self::path();

        // Manutenção real do Laravel (artisan down): não há requisições da aplicação para segurar.
        if (is_file($path) && ! self::ehNosso($path)) {
            return false;
        }

        $conteudo = <<<'PHP'
<?php
// UNITEC-HOTFIX-PORTAO: segura a requisição por instantes enquanto um hotfix troca arquivos.
$__unitecHotfixLimite = microtime(true) + 20;
while (microtime(true) < $__unitecHotfixLimite) {
    clearstatcache(true, __FILE__);
    $__unitecHotfixMtime = @filemtime(__FILE__);
    if ($__unitecHotfixMtime === false || $__unitecHotfixMtime < time() - 120) {
        break;
    }
    usleep(50000);
}
unset($__unitecHotfixLimite, $__unitecHotfixMtime);

PHP;

        $tmp = $path.'.hotfix-tmp';

        if (@file_put_contents($tmp, $conteudo, LOCK_EX) === false) {
            return false;
        }

        if (! @rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        usleep(self::ESPERA_EM_CURSO_MICROS);

        return true;
    }

    public static function fechar(): void
    {
        $path = self::path();

        if (is_file($path) && self::ehNosso($path)) {
            @unlink($path);
        }
    }

    private static function ehNosso(string $path): bool
    {
        $inicio = (string) @file_get_contents($path, false, null, 0, 200);

        return str_contains($inicio, self::MARCADOR);
    }
}
