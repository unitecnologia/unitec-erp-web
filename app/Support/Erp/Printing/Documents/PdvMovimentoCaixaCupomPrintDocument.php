<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Support\Erp\Pdv\PdvMovimentoCaixaBobinaBuilder;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * Comprovante Sangria/Suprimento → ESC/POS via Device Service (impressora do Terminal).
 */
final class PdvMovimentoCaixaCupomPrintDocument implements PrintDocument
{
    public function __construct(
        private readonly PdvCaixaMovimento $movimento,
        private readonly ?Empresa $empresa,
        private readonly ?string $usuarioFallback = null,
    ) {}

    public function key(): string
    {
        return 'pdv_movimento_caixa';
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.pdv-movimento-caixa', [
            'movimento' => $this->movimento->id,
            'auto' => $autoPrint ? 1 : 0,
        ]);
    }

    public function clientPayload(PrintTarget $target): array
    {
        $copies = max(1, min(3, $target->copies));
        $mode = $target->preferredMode();
        $useDevice = $mode === 'device' && $target->hasPrinter();

        $escposUrl = null;

        if ($useDevice) {
            try {
                $escposUrl = route('erp.print.pdv-movimento-escpos', [
                    'movimento' => $this->movimento->id,
                    'copias' => $copies,
                ]);
            } catch (\Throwable) {
                $useDevice = false;
                $mode = 'browser';
            }
        }

        return [
            'document' => $this->key(),
            'url' => $this->htmlUrl(autoPrint: ! $useDevice, copies: $copies),
            'mode' => $mode,
            'copias' => $copies,
            'printer' => $useDevice ? $this->normalizePrinterName($target->printerName) : $target->printerName,
            'tipo' => $target->tipoImpressora,
            'movimentoId' => (int) $this->movimento->id,
            'escposUrl' => $escposUrl,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target): array
    {
        $built = app(PdvMovimentoCaixaBobinaBuilder::class)->build(
            $this->movimento,
            $this->empresa,
            $this->usuarioFallback,
        );

        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);
        $p->setFont(Printer::FONT_A);
        $p->setLineSpacing(30);

        foreach ($built['lines'] as $line) {
            $text = EscPosCharset::encode((string) $line);
            $p->textRaw($text."\n");
        }

        $p->feed(2);
        $p->cut();

        return [
            'printer' => $this->normalizePrinterName($target->printerName),
            'copias' => max(1, min(3, $target->copies)),
            'raw_base64' => base64_encode($writer->getData()),
        ];
    }

    protected function normalizePrinterName(?string $printer): ?string
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
