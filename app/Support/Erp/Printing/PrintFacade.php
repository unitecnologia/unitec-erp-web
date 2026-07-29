<?php

namespace App\Support\Erp\Printing;

use App\Support\Erp\Pdv\PdvConfig;

/**
 * Fachada do módulo de impressão — monta destino a partir do Terminal do caixa.
 */
final class PrintFacade
{
    public static function targetFromTerminal(int $copies = 1): PrintTarget
    {
        $config = PdvConfig::make();

        return new PrintTarget(
            printerName: $config->impressoraNome(),
            copies: max(1, min(3, $copies)),
            tipoImpressora: $config->tipoImpressora(),
            useDeviceService: $config->usarDeviceService(),
        );
    }

    /** JS: Device Service RAW se online; senão diálogo do navegador. */
    public static function livewireOpenJs(PrintDocument $document, int $copies = 1): string
    {
        $payload = json_encode(
            $document->clientPayload(self::targetFromTerminal($copies)),
            JSON_THROW_ON_ERROR,
        );

        return '(function (payload) {
            if (window.ErpPrint?.openCupom) {
                window.ErpPrint.openCupom(payload);
                return;
            }
            if (window.ErpPdvPrint?.openCupom) {
                window.ErpPdvPrint.openCupom(payload);
                return;
            }
            if (payload.url) {
                window.open(payload.url, "_blank");
            }
        })('.$payload.')';
    }
}
