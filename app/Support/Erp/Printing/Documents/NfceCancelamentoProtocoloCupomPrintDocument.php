<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvNfceCancelamentoProtocoloService;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * Protocolo de cancelamento NFC-e → ESC/POS via Device Service.
 */
final class NfceCancelamentoProtocoloCupomPrintDocument implements PrintDocument
{
    public function __construct(
        private readonly PdvVenda $venda,
        private readonly ?Empresa $empresa,
        private readonly string $usuario,
    ) {}

    public function key(): string
    {
        return 'nfce_cancelamento_protocolo';
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.nfce-cancelamento-protocolo', [
            'venda' => $this->venda->id,
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
                $escposUrl = route('erp.print.nfce-cancelamento-escpos', [
                    'venda' => $this->venda->id,
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
            'vendaId' => (int) $this->venda->id,
            'escposUrl' => $escposUrl,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target, PdvNfceCancelamentoProtocoloService $service): array
    {
        $data = $service->buildViewData(
            venda: $this->venda,
            empresa: $this->empresa,
            usuario: $this->usuario,
            autoPrint: false,
        );

        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);
        $p->setFont(Printer::FONT_A);
        $p->setLineSpacing(30);

        foreach ($data['lines'] as $line) {
            $p->textRaw(EscPosCharset::encode((string) $line)."\n");
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
