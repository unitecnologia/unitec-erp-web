<?php

namespace App\Support\Erp\Import;

/**
 * Replica Dados.crypt do Delphi (Action C/D + chave XNGREX...).
 *
 * Algoritmo clássico hex XOR usado no Retaguarda/PDV legado.
 */
final class FirebirdDelphiCrypt
{
    public const KEY = 'XNGREXCPAJHKQWERYTUIOP98756LKJHASFGMNBVCAXZ13450';

    /**
     * Descriptografa valor gravado em USUARIOS.SENHA (hex).
     * Se não parecer ciphertext, devolve o texto original (já em claro).
     */
    public static function decrypt(string $value, string $key = self::KEY): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (! self::looksEncrypted($value)) {
            return $value;
        }

        try {
            return self::crypt('D', $value, $key);
        } catch (\Throwable) {
            return $value;
        }
    }

    public static function encrypt(string $plain, string $key = self::KEY, ?int $offset = null): string
    {
        return self::crypt('C', $plain, $key, $offset);
    }

    public static function looksEncrypted(string $value): bool
    {
        $value = trim($value);

        // Ciphertext Delphi: hex par, mínimo offset(2) + 1 char(2) = 4.
        return $value !== ''
            && strlen($value) >= 4
            && strlen($value) % 2 === 0
            && ctype_xdigit($value);
    }

    /**
     * @param  'C'|'D'|'E'  $action  C/E = criptografa, D = descriptografa
     */
    public static function crypt(string $action, string $src, string $key = self::KEY, ?int $offset = null): string
    {
        $action = strtoupper($action);
        $keyLen = strlen($key);

        if ($keyLen === 0) {
            $key = 'delphi';
            $keyLen = strlen($key);
        }

        $keyPos = 0;

        if ($action === 'C' || $action === 'E') {
            $range = 256;
            $offset ??= random_int(0, $range - 1);
            $dest = sprintf('%02x', $offset);

            for ($srcPos = 0; $srcPos < strlen($src); $srcPos++) {
                $srcAsc = (ord($src[$srcPos]) + $offset) % 255;
                $keyPos = ($keyPos < $keyLen) ? $keyPos + 1 : 1;
                $srcAsc = $srcAsc ^ ord($key[$keyPos - 1]);
                $dest .= sprintf('%02x', $srcAsc);
                $offset = $srcAsc;
            }

            return $dest;
        }

        // Decrypt
        $offset = hexdec(substr($src, 0, 2));
        $dest = '';
        $srcPos = 2;

        while ($srcPos < strlen($src)) {
            $srcAsc = hexdec(substr($src, $srcPos, 2));
            $keyPos = ($keyPos < $keyLen) ? $keyPos + 1 : 1;
            $tmp = $srcAsc ^ ord($key[$keyPos - 1]);

            if ($tmp <= $offset) {
                $tmp = 255 + $tmp - $offset;
            } else {
                $tmp = $tmp - $offset;
            }

            $dest .= chr($tmp);
            $offset = $srcAsc;
            $srcPos += 2;
        }

        return $dest;
    }
}
