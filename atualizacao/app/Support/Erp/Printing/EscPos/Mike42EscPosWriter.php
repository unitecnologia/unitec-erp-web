<?php

namespace App\Support\Erp\Printing\EscPos;

use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;

/**
 * Gera bytes ESC/POS com mike42 (para uso futuro / avançado).
 * O caminho padrão do cupom NFC-e NÃO usa RAW — usa QZ HTML + driver Windows.
 */
final class Mike42EscPosWriter
{
    private DummyPrintConnector $connector;

    private Printer $printer;

    public function __construct()
    {
        $this->connector = new DummyPrintConnector;
        $this->printer = new Printer($this->connector);
    }

    public function printer(): Printer
    {
        return $this->printer;
    }

    public function getData(): string
    {
        $data = $this->connector->getData();
        $this->printer->close();

        return $data;
    }
}
