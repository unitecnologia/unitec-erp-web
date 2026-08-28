<?php

namespace App\Support\Fiscal;

/**
 * Etapas reais da transmissão NF-e / NFC-e à SEFAZ.
 * A UI só avança quando o servidor reporta cada passo (sem timer cosmético).
 */
final class FiscalTransmitProgress
{
    public const STEP_VALIDAR = 0;

    public const STEP_XML = 1;

    public const STEP_ASSINAR = 2;

    public const STEP_SEFAZ = 3;

    public const STEP_AUTORIZACAO = 4;

    /**
     * @return list<string>
     */
    public static function labels(string $documento = 'nfe'): array
    {
        $doc = strtoupper($documento) === 'NFCE' ? 'NFC-e' : 'NF-e';

        return [
            self::STEP_VALIDAR => "Validando dados da {$doc}",
            self::STEP_XML => 'Montando XML do documento',
            self::STEP_ASSINAR => 'Assinando digitalmente',
            self::STEP_SEFAZ => 'Enviando à SEFAZ (aguardando resposta)',
            self::STEP_AUTORIZACAO => 'Processando autorização',
        ];
    }

    /**
     * @param  (callable(int, string): void)|null  $reporter
     */
    public static function report(?callable $reporter, int $step, string $documento = 'nfe'): void
    {
        if ($reporter === null) {
            return;
        }

        $labels = self::labels($documento);
        $reporter($step, $labels[$step] ?? "Etapa {$step}");
    }
}
