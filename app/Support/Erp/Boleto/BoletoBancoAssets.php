<?php

namespace App\Support\Erp\Boleto;

/**
 * Logos dos bancos para o cabeçalho do boleto.
 *
 * Pasta: public/images/boleto/logos/
 * Nome do arquivo = código COMPE (ex.: 085.jpg = Ailos, 001.png = Banco do Brasil).
 * Extensões aceitas: .jpg / .jpeg / .png (nessa ordem de preferência).
 */
final class BoletoBancoAssets
{
    private const LOGOS_DIR = 'images/boleto/logos';

    /** @var list<string> */
    private const EXTENSIONS = ['jpg', 'jpeg', 'png'];

    /**
     * Caminho absoluto do arquivo de logo do banco (COMPE), se existir.
     */
    public static function logoAbsolutePath(?string $bancoCompe): ?string
    {
        $relative = self::logoPublicRelativePath($bancoCompe);
        if ($relative === null) {
            return null;
        }

        $path = public_path($relative);

        return is_file($path) ? $path : null;
    }

    /**
     * Caminho relativo a public/ (ex.: images/boleto/logos/085.jpg).
     */
    public static function logoPublicRelativePath(?string $bancoCompe): ?string
    {
        $banco = self::normalizeCompe($bancoCompe);
        if ($banco === null) {
            return null;
        }

        $dir = public_path(self::LOGOS_DIR);
        foreach (self::EXTENSIONS as $ext) {
            $relative = self::LOGOS_DIR.'/'.$banco.'.'.$ext;
            if (is_file($dir.DIRECTORY_SEPARATOR.$banco.'.'.$ext)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * URL pública da logo (quando o app está servindo public/).
     */
    public static function logoUrl(?string $bancoCompe): ?string
    {
        $relative = self::logoPublicRelativePath($bancoCompe);
        if ($relative === null) {
            return null;
        }

        return asset($relative);
    }

    /**
     * Data URI para embutir no PDF (DomPDF / HTML).
     */
    public static function logoDataUri(?string $bancoCompe): ?string
    {
        $path = self::logoAbsolutePath($bancoCompe);
        if ($path === null) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return null;
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private static function normalizeCompe(?string $bancoCompe): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $bancoCompe) ?? '';
        if ($digits === '') {
            return null;
        }

        // COMPE costuma ter 3 dígitos (085, 001, 237…).
        return str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT);
    }
}
