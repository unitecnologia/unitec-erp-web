<?php

namespace App\Support\Erp\Financeiro\Conciliacao;

/**
 * Heurísticas de identificação PIX no OFX (C4B).
 * FITID Ailos (32 hex) NÃO é tratado como txId/endToEndId.
 */
final class PixIdentificadorOfx
{
    /** TxId Ailos do ERP: AILOS + 8 alfanum + 20 hex. */
    public static function looksLikeAilosTxid(string $value): bool
    {
        $v = strtoupper(trim($value));

        return (bool) preg_match('/^AILOS[A-Z0-9]{8}[0-9A-F]{20}$/', $v);
    }

    /** FITID típico do OFX Ailos: 32 hex — distinto do txId. */
    public static function looksLikeOfxFitidHex32(string $value): bool
    {
        return (bool) preg_match('/^[0-9A-Fa-f]{32}$/', trim($value));
    }

    public const MATCH_EXATA = 'exata';

    public const MATCH_FRACA = 'fraca';

    public const MATCH_COMPATIVEL = 'compativel';

    /**
     * Extrai txId/endToEndId do texto (histórico/memo). Nunca usa o campo FITID.
     *
     * @return list<string>
     */
    public static function extrairTxidsDoTexto(?string ...$textos): array
    {
        return self::extrairIdentificadoresPix(...$textos);
    }

    /**
     * @return list<string>
     */
    public static function extrairIdentificadoresPix(?string ...$textos): array
    {
        $achados = [];
        foreach ($textos as $texto) {
            $texto = strtoupper((string) $texto);
            if ($texto === '') {
                continue;
            }
            if (preg_match_all('/AILOS[A-Z0-9]{8}[0-9A-F]{20}/', $texto, $m)) {
                foreach ($m[0] as $txid) {
                    $achados[$txid] = true;
                }
            }
            // endToEndId BACEN (E + ISPB + 23). 32 hex (FITID) é excluído de propósito.
            if (preg_match_all('/\bE[0-9]{8}[A-Z0-9]{23}\b/', $texto, $m2)) {
                foreach ($m2[0] as $e2e) {
                    if (! self::looksLikeOfxFitidHex32($e2e)) {
                        $achados[$e2e] = true;
                    }
                }
            }
        }

        return array_keys($achados);
    }

    /**
     * Identificadores do OFX: histórico/memo/payee/ref — FITID nunca entra.
     *
     * @return list<string>
     */
    public static function extrairIdentificadoresDoExtrato(object $extrato): array
    {
        $ids = self::extrairIdentificadoresPix(
            (string) ($extrato->historico ?? ''),
            (string) ($extrato->memo ?? ''),
            (string) ($extrato->payee ?? ''),
            (string) ($extrato->ref_num ?? ''),
        );
        $fitid = strtoupper(trim((string) ($extrato->fitid ?? '')));
        if ($fitid === '') {
            return $ids;
        }

        return array_values(array_filter(
            $ids,
            static fn (string $id): bool => $id !== $fitid && ! self::fitidEquivaleTxid($fitid, $id),
        ));
    }

    /**
     * @return list<string>
     */
    public static function identificadoresComuns(object $extrato, object $caixa): array
    {
        $ofx = self::extrairIdentificadoresDoExtrato($extrato);
        if ($ofx === []) {
            return [];
        }

        $caixaIds = array_fill_keys(
            self::extrairIdentificadoresPix(
                (string) ($caixa->historico ?? ''),
                (string) ($caixa->documento ?? ''),
            ),
            true,
        );

        $comuns = [];
        foreach ($ofx as $id) {
            if (isset($caixaIds[$id])) {
                $comuns[] = $id;
            }
        }

        return $comuns;
    }

    public static function classificarMatch(object $extrato, object $caixa): string
    {
        if (self::identificadoresComuns($extrato, $caixa) !== []) {
            return self::MATCH_EXATA;
        }

        if (self::historicoParecePix(
            (string) ($extrato->historico ?? ''),
            (string) ($extrato->memo ?? ''),
        )) {
            return self::MATCH_FRACA;
        }

        return self::MATCH_COMPATIVEL;
    }

    /**
     * Equivalência comprovada FITID × txId.
     * Sem comprovação de formato/igualdade explícita → false.
     */
    public static function fitidEquivaleTxid(?string $fitid, ?string $txid): bool
    {
        $fitid = strtoupper(trim((string) $fitid));
        $txid = strtoupper(trim((string) $txid));

        if ($fitid === '' || $txid === '') {
            return false;
        }

        // Formatos conhecidos distintos (hex32 vs AILOS…) → nunca equivalentes.
        if (self::looksLikeOfxFitidHex32($fitid) && self::looksLikeAilosTxid($txid)) {
            return false;
        }

        // Só se forem exatamente iguais E ambos parecerem o mesmo tipo de id.
        return $fitid === $txid
            && (self::looksLikeAilosTxid($fitid) || ! self::looksLikeOfxFitidHex32($fitid));
    }

    public static function historicoParecePix(?string $historico, ?string $memo = null): bool
    {
        $h = mb_strtoupper(trim((string) $historico.' '.(string) $memo), 'UTF-8');

        return $h !== '' && str_contains($h, 'PIX');
    }
}
