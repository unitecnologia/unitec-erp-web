<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvMesa;
use App\Support\Erp\Pdv\PdvDavCupomLayout;
use App\Support\Erp\Pdv\PdvMesaService;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * Pedido da mesa, item avulso ou pré-conta → mesmo fluxo do cupom PDV (Device Service ou navegador).
 * Os itens vêm do que está gravado na mesa; nada aqui gera venda.
 */
final class PdvMesaCupomPrintDocument implements PrintDocument
{
    public function __construct(
        private readonly PdvMesa $mesa,
        private readonly string $tipo,
        private readonly ?int $itemIndex,
        private readonly ?Empresa $empresa,
        private readonly string $atendente,
        private readonly string $terminal,
    ) {}

    public static function tipoValido(string $tipo): bool
    {
        return in_array($tipo, [PdvDavCupomLayout::MESA_PEDIDO, PdvDavCupomLayout::MESA_ITEM, PdvDavCupomLayout::MESA_PARCIAL], true);
    }

    public function key(): string
    {
        return 'pdv_mesa_'.$this->tipo;
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.pdv-mesa', $this->rotaParams([
            'auto' => $autoPrint ? 1 : 0,
            'copias' => max(1, min(3, $copies)),
        ]));
    }

    public function clientPayload(PrintTarget $target): array
    {
        $copies = max(1, min(3, $target->copies));
        $mode = $target->impressoraA4() ? 'browser' : $target->preferredMode();
        $useDevice = $mode === 'device' && $target->hasPrinter();

        return [
            'document' => $this->key(),
            'url' => $this->htmlUrl(autoPrint: ! $useDevice, copies: $copies),
            'mode' => $mode,
            'copias' => $copies,
            'printer' => $target->printerName,
            'tipo' => $target->tipoImpressora,
            'escposUrl' => $useDevice
                ? route('erp.print.pdv-mesa-escpos', $this->rotaParams(['copias' => $copies]))
                : null,
        ];
    }

    /**
     * Null quando não há itens a imprimir.
     *
     * @return array{titulo: string, davNumero: string, lines: list<array<string, mixed>>, plain: list<string>}|null
     */
    public function layout(): ?array
    {
        $itens = PdvMesaService::make()->itens($this->mesa);

        if ($this->tipo === PdvDavCupomLayout::MESA_ITEM) {
            $item = $this->itemIndex !== null ? ($itens[$this->itemIndex] ?? null) : null;
            $itens = $item !== null ? [$item] : [];
        }

        if ($itens === []) {
            return null;
        }

        return PdvDavCupomLayout::buildMesa(
            (int) $this->mesa->numero,
            $itens,
            $this->tipo,
            $this->empresa,
            $this->atendente,
            $this->terminal,
            now(),
        );
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}|null
     */
    public function buildEscPosPayload(PrintTarget $target): ?array
    {
        $layout = $this->layout();

        if ($layout === null) {
            return null;
        }

        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);

        $fontAtual = Printer::FONT_A;
        $lineSpacingAtual = null;
        $p->setFont($fontAtual);
        $p->setLineSpacing(30);

        foreach ($layout['lines'] as $row) {
            $text = EscPosCharset::encode((string) ($row['text'] ?? ''));
            $bold = (bool) ($row['bold'] ?? false);
            $font = (($row['font'] ?? 'A') === 'B') ? Printer::FONT_B : Printer::FONT_A;
            $grande = (int) ($row['size'] ?? 1) > 1;

            if ($font !== $fontAtual) {
                $p->setFont($font);
                $fontAtual = $font;
            }

            $lineSpacing = $font === Printer::FONT_B ? 24 : 30;
            if ($lineSpacingAtual !== $lineSpacing && ! $grande) {
                $p->setLineSpacing($lineSpacing);
                $lineSpacingAtual = $lineSpacing;
            }

            if ($grande) {
                $p->setJustification(Printer::JUSTIFY_CENTER);
                $p->setTextSize(2, 2);
            }

            $p->setEmphasis($bold);
            $p->textRaw($text."\n");
            $p->setEmphasis(false);

            if ($grande) {
                $p->setTextSize(1, 1);
                $p->setJustification(Printer::JUSTIFY_LEFT);
            }
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

    /**
     * @param  array<string, int>  $extra
     * @return array<string, int|string>
     */
    private function rotaParams(array $extra): array
    {
        return array_filter([
            'mesa' => (int) $this->mesa->id,
            'tipo' => $this->tipo,
            'item' => $this->tipo === PdvDavCupomLayout::MESA_ITEM ? $this->itemIndex : null,
            ...$extra,
        ], static fn ($valor): bool => $valor !== null);
    }
}
