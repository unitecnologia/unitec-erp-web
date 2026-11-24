<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use Illuminate\Support\Facades\Log;

/**
 * Diagnóstico do cancelamento NFS-e Nacional (SEFIN).
 * Sem certificado, token, CPF, dados pessoais ou XML completo.
 * Usa warning para aparecer com LOG_LEVEL=warning nos clientes.
 */
final class NfseSefinCancelamentoLog
{
    public const OPERACAO_POST_CANCELAMENTO = 'POST cancelamento';

    public const OPERACAO_GET_CONSULTA = 'GET consulta evento';

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function falha(string $operacao, Nfse $nfse, string $chave, array $extra = []): void
    {
        Log::warning('NFS-e Nacional: diagnóstico de cancelamento.', self::contexto($operacao, $nfse, $chave, $extra));
    }

    /**
     * @param  list<array{codigo?: string, descricao?: string}>|null  $erros
     * @return array<string, mixed>
     */
    public static function contexto(
        string $operacao,
        Nfse $nfse,
        string $chave,
        array $extra = [],
    ): array {
        $numero = trim((string) ($nfse->numero_nfse ?? ''));
        if ($numero === '') {
            $numero = trim((string) ($nfse->numero_dps ?? ''));
        }

        $payload = [
            'operacao' => $operacao,
            'nfse_id' => (int) ($nfse->id ?? 0),
            'numero_nfse' => $numero !== '' ? $numero : null,
            'chave' => $chave,
        ];

        if (array_key_exists('http', $extra)) {
            $payload['http'] = (int) $extra['http'];
        }

        if (array_key_exists('registrado', $extra)) {
            $payload['registrado'] = (bool) $extra['registrado'];
        }

        if (array_key_exists('ja_cancelada', $extra)) {
            $payload['ja_cancelada'] = (bool) $extra['ja_cancelada'];
        }

        if (array_key_exists('resultado_consulta', $extra)) {
            $payload['resultado_consulta'] = (string) $extra['resultado_consulta'];
        }

        if (array_key_exists('motivo', $extra) && is_string($extra['motivo']) && $extra['motivo'] !== '') {
            $payload['motivo'] = self::sanitizarTexto($extra['motivo']);
        }

        if (isset($extra['erros']) && is_array($extra['erros'])) {
            $payload['erros'] = self::sanitizarErros($extra['erros']);
        }

        return $payload;
    }

    /**
     * @param  list<mixed>  $erros
     * @return list<array{codigo: string, descricao: string}>
     */
    public static function sanitizarErros(array $erros): array
    {
        $lista = [];

        foreach ($erros as $erro) {
            if (! is_array($erro)) {
                continue;
            }

            $lista[] = [
                'codigo' => self::sanitizarTexto((string) ($erro['codigo'] ?? '')),
                'descricao' => self::sanitizarTexto((string) ($erro['descricao'] ?? '')),
            ];
        }

        return $lista;
    }

    public static function sanitizarTexto(string $texto): string
    {
        $texto = trim($texto);
        // Evita colar XML/PEM acidentalmente no log.
        if (str_contains($texto, '<') || str_contains($texto, 'BEGIN CERTIFICATE') || str_contains($texto, 'BEGIN PRIVATE')) {
            return mb_substr(preg_replace('/\s+/', ' ', $texto) ?? '', 0, 180, 'UTF-8');
        }

        return mb_substr($texto, 0, 500, 'UTF-8');
    }
}
