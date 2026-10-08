<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Fiscal\NfceContingenciaConsistencia;

/**
 * Impressão fiscal (cupom/DANFE NFC-e) só para documento válido: autorizada, contingência
 * consistente ou cancelada já autorizada (DANFE com aviso). Simulado mantém o fluxo não fiscal.
 */
final class NfceImpressaoFiscal
{
    public static function motivoBloqueio(?PdvVendaNfce $nfce): ?string
    {
        if ($nfce === null || $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return null;
        }

        $numero = 'NFC-e nº '.($nfce->numero ?: '—');

        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_AUTORIZADA => preg_match('/^\d{44}$/', (string) $nfce->chave) === 1 && filled($nfce->protocolo)
                ? null
                : $numero.' autorizada sem chave/protocolo gravados. Use F4 Recuperar antes de imprimir.',
            PdvVendaNfce::STATUS_CONTINGENCIA => ($motivo = NfceContingenciaConsistencia::motivo($nfce)) === null
                ? null
                : $numero.' em contingência inconsistente: '.$motivo.'.',
            PdvVendaNfce::STATUS_CANCELADA => filled($nfce->protocolo)
                ? null
                : $numero.' cancelada sem protocolo de autorização.',
            PdvVendaNfce::STATUS_PENDENTE => $numero.' pendente na SEFAZ (situação não confirmada). Consulte (F4) ou transmita (F5) antes de imprimir.',
            PdvVendaNfce::STATUS_REJEITADA => $numero.' rejeitada pela SEFAZ: não é documento fiscal válido.',
            PdvVendaNfce::STATUS_DENEGADA => $numero.' com uso denegado pela SEFAZ: não pode ser impressa como DANFE.',
            PdvVendaNfce::STATUS_DUPLICIDADE => $numero.' em duplicidade (539): resolva na aba Duplicidade antes de imprimir.',
            default => $numero.' ('.$nfce->status.') não pode ser impressa.',
        };
    }

    public static function abortSeBloqueada(PdvVenda $venda): void
    {
        $motivo = self::motivoBloqueio($venda->nfce);

        abort_if($motivo !== null, 422, $motivo ?? '');
    }
}
