<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvDavCupomLayout;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Carbon\CarbonInterface;
use Mike42\Escpos\Printer;

/**
 * Cupom PDV não fiscal (DAV) → ESC/POS para Device Service.
 */
final class PdvCupomPrintDocument implements PrintDocument
{
    public function __construct(
        private readonly PdvVenda $venda,
        private readonly ?Empresa $empresa,
        private readonly string $usuario,
        private readonly CarbonInterface|null $printedAt = null,
    ) {}

    public function key(): string
    {
        return 'pdv_cupom';
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.pdv-cupom', [
            'venda' => $this->venda->id,
            'auto' => $autoPrint ? 1 : 0,
            'copias' => max(1, min(3, $copies)),
        ]);
    }

    public function clientPayload(PrintTarget $target): array
    {
        $copies = max(1, min(3, $target->copies));
        $mode = $target->preferredMode();
        $useDevice = $mode === 'device' && $target->hasPrinter();

        return [
            'document' => $this->key(),
            'url' => $this->htmlUrl(autoPrint: ! $useDevice, copies: $copies),
            'mode' => $mode,
            'copias' => $copies,
            'printer' => $target->printerName,
            'tipo' => $target->tipoImpressora,
            'vendaId' => (int) $this->venda->id,
            'escposUrl' => $useDevice
                ? route('erp.print.pdv-escpos', [
                    'venda' => $this->venda->id,
                    'copias' => $copies,
                ])
                : null,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target): array
    {
        $layout = PdvDavCupomLayout::build($this->venda, $this->empresa, $this->printedAt);

        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        $p->setJustification(Printer::JUSTIFY_LEFT);
        // CP850 — acentos PT-BR (Á É Í Ó Ú Ã Õ Ç etc.)
        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);

        $fontAtual = Printer::FONT_A;
        $lineSpacingAtual = null;
        $p->setFont($fontAtual);
        $p->setLineSpacing(30);

        foreach ($layout['lines'] as $row) {
            $text = EscPosCharset::encode((string) ($row['text'] ?? ''));
            $bold = (bool) ($row['bold'] ?? false);
            $font = (($row['font'] ?? 'A') === 'B') ? Printer::FONT_B : Printer::FONT_A;

            if ($font !== $fontAtual) {
                $p->setFont($font);
                $fontAtual = $font;
            }

            // Só a área de itens usa entrelinha um pouco mais baixa.
            $lineSpacing = $font === Printer::FONT_B ? 24 : 30;
            if ($lineSpacingAtual !== $lineSpacing) {
                $p->setLineSpacing($lineSpacing);
                $lineSpacingAtual = $lineSpacing;
            }

            $p->setEmphasis($bold);
            $p->textRaw($text."\n");
            $p->setEmphasis(false);
        }

        $p->setEmphasis(false);
        $p->setFont(Printer::FONT_A);
        $p->setLineSpacing();
        $p->feed(2);
        $p->cut();

        return [
            'printer' => $target->printerName,
            'copias' => max(1, min(3, $target->copies)),
            'raw_base64' => base64_encode($writer->getData()),
        ];
    }
}
