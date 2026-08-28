<?php

namespace App\Support\Erp\Printing;

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
}
