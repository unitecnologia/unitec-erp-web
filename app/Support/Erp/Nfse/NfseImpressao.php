<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use App\Support\Erp\Nfse\Ipm\NfseIpmImpressaoViewData;

/**
 * Escolhe o documento de impressão. NFS-e Nacional permanece no DANFSe; IPM usa layout próprio.
 */
final class NfseImpressao
{
    public const VIEW_NACIONAL = 'reports.nfse-impressao';

    public const VIEW_IPM = 'reports.nfse-ipm-impressao';

    /**
     * @return array<string, mixed>
     */
    public static function dados(Nfse $nfse, bool $autoPrint = false, bool $embedded = false): array
    {
        if (NfseIpmImpressaoViewData::aplica($nfse)) {
            return NfseIpmImpressaoViewData::for($nfse, $autoPrint, $embedded);
        }

        $data = NfseDanfseViewData::for($nfse, $autoPrint, $embedded);
        $data['impressao_view'] = self::VIEW_NACIONAL;

        return $data;
    }
}
