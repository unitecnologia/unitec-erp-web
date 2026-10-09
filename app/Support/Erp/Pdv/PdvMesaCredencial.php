<?php

namespace App\Support\Erp\Pdv;

/**
 * Credencial assinada (HMAC com APP_KEY) do painel de mesas. Permite ao pulso de
 * status rodar sem sessão: com SESSION_DRIVER=file, uma requisição paralela com
 * sessão regravaria o cupom da estação com dados antigos.
 */
final class PdvMesaCredencial
{
    private const VALIDADE_SEGUNDOS = 43200;

    public static function emitir(int $empresaId, ?int $terminalId, ?int $userId): string
    {
        $payload = self::base64UrlEncode((string) json_encode([
            'e' => $empresaId,
            't' => (int) $terminalId,
            'u' => (int) $userId,
            'x' => time() + self::VALIDADE_SEGUNDOS,
        ]));

        return $payload.'.'.self::assinar($payload);
    }

    /**
     * @return array{e: int, t: int, u: int}|null
     */
    public static function validar(?string $credencial): ?array
    {
        $partes = explode('.', (string) $credencial);

        if (count($partes) !== 2 || ! hash_equals(self::assinar($partes[0]), $partes[1])) {
            return null;
        }

        $base64 = strtr($partes[0], '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $dados = json_decode((string) base64_decode($base64, true), true);

        if (! is_array($dados) || (int) ($dados['x'] ?? 0) < time() || (int) ($dados['e'] ?? 0) <= 0) {
            return null;
        }

        return [
            'e' => (int) $dados['e'],
            't' => (int) ($dados['t'] ?? 0),
            'u' => (int) ($dados['u'] ?? 0),
        ];
    }

    private static function assinar(string $payload): string
    {
        return hash_hmac('sha256', 'pdv-mesas|'.$payload, (string) config('app.key'));
    }

    private static function base64UrlEncode(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }
}
