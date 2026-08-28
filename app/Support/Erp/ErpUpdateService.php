<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\File;

/**
 * Utilitários mínimos compartilhados pelo ERP.
 *
 * A atualização de produção é feita pelo UnitecErpServer:
 * GitHub ZIP -> atualizacao/ -> confirmação no login.
 */
final class ErpUpdateService
{
    public static function ensureFrameworkStorageDirectories(): void
    {
        static $ensured = false;

        if ($ensured) {
            return;
        }

        $ensured = true;

        foreach ([
            'framework/sessions',
            'framework/cache',
            'framework/cache/data',
            'framework/views',
            'framework/testing',
            'logs',
            'app/private',
        ] as $relative) {
            File::ensureDirectoryExists(storage_path($relative));
        }
    }

    /**
     * Versão efetiva instalada: o arquivo no disco é a fonte de verdade pós-update.
     */
    public static function readInstalledVersion(): string
    {
        $path = base_path('config/unitec.php');
        $content = is_file($path) ? (string) @file_get_contents($path) : '';

        if (preg_match("/'versao'\s*=>\s*'([^']+)'/", $content, $matches)) {
            return trim($matches[1]);
        }

        return (string) config('unitec.versao', '');
    }
}
