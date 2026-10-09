<?php

namespace App\Support\Erp\Hotfix;

/**
 * Log dos hotfixes. Falha ao gravar nunca interrompe a verificação nem o login.
 */
final class HotfixLog
{
    public static function line(string $etapa, string $detalhe = ''): void
    {
        try {
            $path = storage_path('logs'.DIRECTORY_SEPARATOR.'erp-hotfix.log');
            $dir = dirname($path);

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $detalhe = mb_substr(trim(str_replace(["\r", "\n"], ' ', $detalhe)), 0, 1000);

            @file_put_contents(
                $path,
                date('Y-m-d H:i:s').'  '.$etapa.($detalhe !== '' ? '  '.$detalhe : '').PHP_EOL,
                FILE_APPEND | LOCK_EX,
            );
        } catch (\Throwable) {
        }
    }
}
