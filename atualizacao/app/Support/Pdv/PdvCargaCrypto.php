<?php

namespace App\Support\Pdv;

/**
 * Criptografia simétrica (AES-256-GCM) para o certificado A1 na carga do PDV.
 * A chave deriva de empresa_id + terminal_id (sem token compartilhado).
 */
final class PdvCargaCrypto
{
    private const ALGO = 'aes-256-gcm';

    public static function keyFromTerminal(int $empresaId, int $terminalId): string
    {
        return hash('sha256', 'pdv-carga|e'.$empresaId.'|t'.$terminalId, true);
    }

    /**
     * @deprecated Use keyFromTerminal
     */
    public static function keyFromToken(string $token): string
    {
        return hash('sha256', 'pdv-carga|'.$token, true);
    }

    /**
     * @return array{algo: string, iv: string, tag: string, data: string}
     */
    public static function encrypt(string $plaintext, int $empresaId, int $terminalId): array
    {
        $iv = random_bytes(12);
        $tag = '';

        $cipher = openssl_encrypt(
            $plaintext,
            self::ALGO,
            self::keyFromTerminal($empresaId, $terminalId),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($cipher === false) {
            throw new \RuntimeException('Falha ao criptografar payload da carga.');
        }

        return [
            'algo' => self::ALGO,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'data' => base64_encode($cipher),
        ];
    }

    /**
     * @param  array{algo?: string, iv?: string, tag?: string, data?: string}  $blob
     */
    public static function decrypt(array $blob, int $empresaId, int $terminalId): ?string
    {
        if (($blob['algo'] ?? '') !== self::ALGO) {
            return null;
        }

        $plain = openssl_decrypt(
            base64_decode((string) ($blob['data'] ?? ''), true) ?: '',
            self::ALGO,
            self::keyFromTerminal($empresaId, $terminalId),
            OPENSSL_RAW_DATA,
            base64_decode((string) ($blob['iv'] ?? ''), true) ?: '',
            base64_decode((string) ($blob['tag'] ?? ''), true) ?: '',
        );

        return $plain === false ? null : $plain;
    }
}
