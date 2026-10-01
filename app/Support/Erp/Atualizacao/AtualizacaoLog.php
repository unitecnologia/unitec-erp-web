<?php

namespace App\Support\Erp\Atualizacao;

/**
 * Log da atualização confirmada no login. Falha ao gravar nunca cancela o apply.
 */
final class AtualizacaoLog
{
    public static function line(string $etapa, string $detalhe = '', ?string $appPath = null): void
    {
        try {
            $root = rtrim($appPath ?: base_path(), '\\/');
            $path = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'
                .DIRECTORY_SEPARATOR.'erp-atualizacao.log';
            $dir = dirname($path);

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }

            $text = date('Y-m-d H:i:s')
                .'  '.self::oneLine($etapa)
                .(($clean = self::oneLine($detalhe)) !== '' ? '  '.$clean : '')
                .PHP_EOL;

            @file_put_contents($path, $text, FILE_APPEND | LOCK_EX);
        } catch (\Throwable) {
            // Disco ou permissão não podem interromper a atualização nem o login.
        }
    }

    private static function oneLine(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], ' ', $value));

        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, 1000);
    }
}
