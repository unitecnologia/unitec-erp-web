<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\Pdv\PdvCaixaResumoBobinaBuilder;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * RESUMO CAIXA → formato do "Tipo de fechamento" do Terminal:
 * A4 padrão / A4 detalhado (navegador) ou bobina detalhada / sintética (ESC/POS ou navegador).
 */
final class PdvCaixaResumoCupomPrintDocument implements PrintDocument
{
    private readonly string $formato;

    public function __construct(
        private readonly PdvCaixaSessao $sessao,
        private readonly ?Empresa $empresa,
        private readonly float $dinheiroInformado = 0.0,
        private readonly ?string $usuarioFallback = null,
        ?string $formato = null,
    ) {
        $this->formato = $formato ?? PdvConfig::make()->tipoFechamento();
    }

    public function key(): string
    {
        return 'pdv_resumo_caixa';
    }

    public function formatoA4(): bool
    {
        return in_array($this->formato, [PdvConfig::FECHAMENTO_A4_PADRAO, PdvConfig::FECHAMENTO_A4_DETALHADO], true);
    }

    public function sintetico(): bool
    {
        return $this->formato === PdvConfig::FECHAMENTO_BOBINA_SINTETICO;
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.pdv-resumo-caixa', array_filter([
            'sessao' => $this->sessao->id,
            'dinheiro' => number_format($this->dinheiroInformado, 2, '.', ''),
            'auto' => $autoPrint ? 1 : 0,
            'sintetico' => $this->sintetico() ? 1 : null,
        ], static fn ($v): bool => $v !== null));
    }

    public function a4Url(bool $autoPrint = false): string
    {
        return route('erp.reports.pdv-resumo-caixa', array_filter([
            'sessao' => $this->sessao->id,
            'dinheiro' => $this->dinheiroInformado > 0 ? number_format($this->dinheiroInformado, 2, '.', '') : null,
            'auto' => $autoPrint ? 1 : 0,
            'a4' => 1,
            'detalhado' => $this->formato === PdvConfig::FECHAMENTO_A4_PADRAO ? 0 : 1,
        ], static fn ($v): bool => $v !== null));
    }

    public function clientPayload(PrintTarget $target): array
    {
        $copies = max(1, min(3, $target->copies));

        if ($this->formatoA4()) {
            return [
                'document' => $this->key(),
                'url' => $this->a4Url(autoPrint: true),
                'mode' => 'browser',
                'copias' => 1,
                'printer' => $target->printerName,
                'tipo' => $target->tipoImpressora,
                'sessaoId' => (int) $this->sessao->id,
                'escposUrl' => null,
                'printInFrame' => true,
            ];
        }

        // Bobina numa impressora A4 nunca recebe ESC/POS: sai o HTML 80 mm pelo navegador.
        $mode = $target->impressoraA4() ? 'browser' : $target->preferredMode();
        $useDevice = $mode === 'device' && $target->hasPrinter();

        $escposUrl = null;

        if ($useDevice) {
            try {
                $escposUrl = route('erp.print.pdv-resumo-caixa-escpos', array_filter([
                    'sessao' => $this->sessao->id,
                    'dinheiro' => number_format($this->dinheiroInformado, 2, '.', ''),
                    'copias' => $copies,
                    'sintetico' => $this->sintetico() ? 1 : null,
                ], static fn ($v): bool => $v !== null));
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
            'sessaoId' => (int) $this->sessao->id,
            'escposUrl' => $escposUrl,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target): array
    {
        $built = app(PdvCaixaResumoBobinaBuilder::class)->buildFromSessao(
            $this->sessao,
            $this->empresa,
            $this->dinheiroInformado,
            $this->usuarioFallback,
            $this->sintetico(),
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
