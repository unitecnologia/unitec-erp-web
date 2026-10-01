<?php

namespace App\Support\Erp\Printing\Documents;

use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * Etiqueta de Gôndola 8,90 × 3,00 cm — ESC/POS RAW (Device Service).
 *
 * Layout (modo página, 203 dpi):
 *  - descrição centralizada no topo
 *  - código de barras à esquerda + R$ / preço à direita
 *  - Cód. Interno na base
 */
final class GondolaEtiquetaPrintDocument implements PrintDocument
{
    /** Largura da etiqueta em mm. */
    public const WIDTH_MM = 89.0;

    /** Altura da etiqueta em mm. */
    public const HEIGHT_MM = 30.0;

    /** Densidade padrão de impressoras de etiqueta ESC/POS. */
    public const DPI = 203;

    /**
     * @param  list<array{
     *     codigo: string,
     *     codigo_barras: string,
     *     descricao: string,
     *     preco: float|string,
     *     quantidade: int
     * }>  $itens
     */
    public function __construct(
        private readonly array $itens,
    ) {}

    public function key(): string
    {
        return 'etiqueta_gondola';
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return '';
    }

    public function clientPayload(PrintTarget $target): array
    {
        $useDevice = $target->preferredMode() === 'device' && $target->hasPrinter();

        return [
            'document' => $this->key(),
            'url' => '',
            'mode' => $useDevice ? 'device' : 'none',
            'copias' => 1,
            'printer' => $useDevice ? $this->normalizePrinterName($target->printerName) : null,
            'tipo' => $target->tipoImpressora,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target): array
    {
        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);

        foreach ($this->itens as $item) {
            $quantidade = max(1, (int) ($item['quantidade'] ?? 1));

            for ($i = 0; $i < $quantidade; $i++) {
                $this->writeLabel($p, $item);
            }
        }

        return [
            'printer' => $this->normalizePrinterName($target->printerName),
            'copias' => 1,
            'raw_base64' => base64_encode($writer->getData()),
        ];
    }

    /**
     * @param  array{
     *     codigo: string,
     *     codigo_barras: string,
     *     descricao: string,
     *     preco: float|string,
     *     quantidade?: int
     * }  $item
     */
    private function writeLabel(Printer $p, array $item): void
    {
        $descricao = $this->truncate(mb_strtoupper(trim((string) ($item['descricao'] ?? '')), 'UTF-8'), 32);
        $barras = preg_replace('/\D/', '', (string) ($item['codigo_barras'] ?? '')) ?? '';
        $codigo = (string) ($item['codigo'] ?? '');
        $codigoInterno = str_pad(preg_replace('/\D/', '', $codigo) ?: $codigo, 9, '0', STR_PAD_LEFT);
        $preco = $this->formatPreco($item['preco'] ?? 0);

        $widthDots = (int) round(self::WIDTH_MM / 25.4 * self::DPI);
        $heightDots = (int) round(self::HEIGHT_MM / 25.4 * self::DPI);

        // ESC @ init
        $p->textRaw("\x1b\x40");
        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);

        // ESC L — modo página (posicionamento absoluto na área da etiqueta)
        $p->textRaw("\x1bL");

        // ESC W — área de impressão 0,0 × width × height
        $p->textRaw("\x1bW".$this->u16(0).$this->u16(0).$this->u16($widthDots).$this->u16($heightDots));

        // ESC T 0 — esquerda→direita, cima→baixo
        $p->textRaw("\x1bT\x00");

        // Descrição (topo, centralizada)
        $this->moveTo($p, (int) ($widthDots * 0.06), (int) ($heightDots * 0.08));
        $p->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_HEIGHT);
        $p->setJustification(Printer::JUSTIFY_CENTER);
        $p->textRaw(EscPosCharset::encode($descricao)."\n");
        $p->selectPrintMode();

        // Código de barras (esquerda)
        $this->moveTo($p, (int) ($widthDots * 0.06), (int) ($heightDots * 0.38));
        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->setBarcodeHeight(56);
        $p->setBarcodeWidth(2);
        $p->setBarcodeTextPosition(Printer::BARCODE_TEXT_BELOW);

        if ($barras !== '') {
            $this->printBarcode($p, $barras);
        } else {
            $p->textRaw(EscPosCharset::encode('(sem cód. barras)')."\n");
        }

        // Preço (direita)
        $this->moveTo($p, (int) ($widthDots * 0.58), (int) ($heightDots * 0.36));
        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->selectPrintMode(Printer::MODE_EMPHASIZED);
        $p->textRaw(EscPosCharset::encode('R$')."\n");
        $p->selectPrintMode(Printer::MODE_EMPHASIZED | Printer::MODE_DOUBLE_WIDTH | Printer::MODE_DOUBLE_HEIGHT);
        $p->textRaw(EscPosCharset::encode($preco)."\n");
        $p->selectPrintMode();

        // Cód. Interno (base)
        $this->moveTo($p, (int) ($widthDots * 0.06), (int) ($heightDots * 0.82));
        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->selectPrintMode();
        $p->textRaw(EscPosCharset::encode('Cód. Interno: '.$codigoInterno)."\n");

        // FF — imprime buffer do modo página e volta ao modo padrão
        $p->textRaw("\x0c");
    }

    private function printBarcode(Printer $p, string $digits): void
    {
        $len = strlen($digits);

        try {
            if ($len === 13) {
                $p->barcode($digits, Printer::BARCODE_JAN13);

                return;
            }

            if ($len === 8) {
                $p->barcode($digits, Printer::BARCODE_JAN8);

                return;
            }

            if ($len === 12) {
                $p->barcode($digits, Printer::BARCODE_UPCA);

                return;
            }

            // CODE128 — conteúdo alfanumérico/numérico genérico
            $p->barcode('{B'.$digits, Printer::BARCODE_CODE128);
        } catch (\Throwable) {
            $p->textRaw(EscPosCharset::encode($digits)."\n");
        }
    }

    private function moveTo(Printer $p, int $x, int $y): void
    {
        $x = max(0, $x);
        $y = max(0, $y);

        // GS $ — posição vertical absoluta (modo página)
        $p->textRaw("\x1d\x24".$this->u16($y));
        // ESC $ — posição horizontal absoluta
        $p->textRaw("\x1b\x24".$this->u16($x));
    }

    private function u16(int $value): string
    {
        $value = max(0, min(65535, $value));

        return chr($value & 0xFF).chr(($value >> 8) & 0xFF);
    }

    private function formatPreco(float|string $preco): string
    {
        if (is_string($preco)) {
            $normalized = str_replace(['.', ','], ['', '.'], preg_replace('/[^\d,.]/', '', $preco) ?? '0');
            $preco = (float) $normalized;
        }

        return number_format((float) $preco, 2, ',', '.');
    }

    private function truncate(string $text, int $max): string
    {
        if (mb_strlen($text, 'UTF-8') <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 3, 'UTF-8')).'...';
    }

    private function normalizePrinterName(?string $printer): ?string
    {
        $printer = trim((string) $printer);

        if ($printer === '') {
            return null;
        }

        if (preg_match('/^RAW:(.+)$/iu', $printer, $m) === 1) {
            $name = trim($m[1]);

            return $name !== '' ? $name : null;
        }

        return $printer;
    }
}
