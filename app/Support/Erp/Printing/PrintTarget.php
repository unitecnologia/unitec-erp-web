<?php

namespace App\Support\Erp\Printing;

use App\Support\Erp\Pdv\PdvPedidoReportData;

/**
 * Destino da impressão no caixa.
 *
 * Device Service é o caminho preferido quando há impressora Windows/RAW
 * configurada no terminal; senão / offline → navegador.
 */
final class PrintTarget
{
    public function __construct(
        public readonly ?string $printerName,
        public readonly int $copies = 1,
        public readonly string $tipoImpressora = '1',
        public readonly bool $useDeviceService = true,
    ) {}

    public function hasPrinter(): bool
    {
        return filled($this->printerName);
    }

    public function preferredMode(): string
    {
        return ($this->useDeviceService && $this->hasPrinter()) ? 'device' : 'browser';
    }

    /** NFC-e no layout DANFE A4 (Terminal: "NFC-e - A4"). */
    public function nfceA4(): bool
    {
        return $this->tipoImpressora === PdvPedidoReportData::TIPO_IMPRESSORA_NFCE_A4;
    }

    /**
     * Impressora de folha A4 (Pedido A4 / NFC-e A4): imprime pelo navegador e nunca recebe ESC/POS.
     */
    public function impressoraA4(): bool
    {
        return in_array($this->tipoImpressora, [
            PdvPedidoReportData::TIPO_IMPRESSORA_PEDIDO_A4,
            PdvPedidoReportData::TIPO_IMPRESSORA_NFCE_A4,
        ], true);
    }
}
