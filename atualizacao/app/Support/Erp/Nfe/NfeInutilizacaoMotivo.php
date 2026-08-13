<?php

namespace App\Support\Erp\Nfe;

final class NfeInutilizacaoMotivo
{
    public const MIN_LENGTH = 15;

    public const MAX_LENGTH = 255;

    public const TEXTO_PADRAO = 'Inutilizacao de numeracao por quebra de sequencia na emissao da NF-e';

    public const TITULO_SUCESSO = 'NUMERAÇÃO NF-E INUTILIZADA COM SUCESSO';

    public const HINT_SUCESSO = 'Clique em OK para continuar.';

    public static function formatDetalheSucesso(int $serie, int $numeroInicial, int $numeroFinal, string $protocolo): string
    {
        $faixa = $numeroInicial === $numeroFinal
            ? (string) $numeroInicial
            : "{$numeroInicial} a {$numeroFinal}";

        $detalhe = "Série {$serie} — notas {$faixa}.";

        if (trim($protocolo) !== '') {
            $detalhe .= "\nProtocolo: {$protocolo}";
        }

        return $detalhe;
    }

    public static function normalize(string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', $texto) ?? '');
    }

    public static function validate(string $texto): ?string
    {
        $normalized = self::normalize($texto);
        $length = mb_strlen($normalized, 'UTF-8');

        if ($length < self::MIN_LENGTH) {
            return 'Justificativa da inutilização deve ter no mínimo ' . self::MIN_LENGTH . ' caracteres.';
        }

        if ($length > self::MAX_LENGTH) {
            return 'Justificativa da inutilização deve ter no máximo ' . self::MAX_LENGTH . ' caracteres.';
        }

        return null;
    }
}
