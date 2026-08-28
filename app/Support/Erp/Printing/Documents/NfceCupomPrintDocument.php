<?php

namespace App\Support\Erp\Printing\Documents;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Pdv\PdvDavCupomLayout;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use App\Support\Erp\Printing\EscPos\EscPosCharset;
use App\Support\Erp\Printing\EscPos\Mike42EscPosWriter;
use App\Support\Erp\Printing\EscPos\NfceQrInfoRaster;
use App\Support\Erp\Printing\PrintDocument;
use App\Support\Erp\Printing\PrintTarget;
use Mike42\Escpos\Printer;

/**
 * Cupom NFC-e: Device Service (RAW/ESC-POS) com fallback HTML no navegador.
 */
final class NfceCupomPrintDocument implements PrintDocument
{
    public function __construct(
        private readonly PdvVenda $venda,
        private readonly ?Empresa $empresa,
        private readonly string $usuario,
        private readonly string $operacao = PdvFinalizarOperacao::NFCE_TRANSMITIR,
    ) {}

    public function key(): string
    {
        return 'nfce_cupom';
    }

    public function htmlUrl(bool $autoPrint = false, int $copies = 1): string
    {
        return route('erp.reports.nfce-cupom', [
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
                ? route('erp.print.nfce-escpos', [
                    'venda' => $this->venda->id,
                    'copias' => $copies,
                ])
                : null,
        ];
    }

    /**
     * @return array{printer: string|null, raw_base64: string, copias: int}
     */
    public function buildEscPosPayload(PrintTarget $target, PdvNfceSimuladaService $service): array
    {
        $data = $service->buildViewData(
            venda: $this->venda->loadMissing(['itens.product', 'pagamentos', 'person', 'nfce']),
            empresa: $this->empresa,
            usuario: $this->usuario,
            operacao: $this->operacao,
            copias: 1,
            autoPrint: false,
        );

        $writer = new Mike42EscPosWriter;
        $p = $writer->printer();

        /** @var PdvVenda $venda */
        $venda = $data['venda'];
        $emitente = $data['emitente'] ?? [];
        $wItems = PdvDavCupomLayout::WIDTH_ITEMS;

        $p->selectCharacterTable(EscPosCharset::TABLE_PC850);
        $p->setJustification(Printer::JUSTIFY_CENTER);
        $p->setLineSpacing(30);
        $p->setFont(Printer::FONT_A);
        $p->setEmphasis(true);
        $p->textRaw(EscPosCharset::encode((string) ($emitente['fantasia'] ?: $emitente['nome'] ?? ''))."\n");
        $p->setEmphasis(false);
        $p->textRaw(EscPosCharset::encode((string) ($emitente['nome'] ?? ''))."\n");
        $p->textRaw(EscPosCharset::encode('CNPJ: '.($emitente['cnpj'] ?? '').' IE: '.($emitente['ie'] ?? ''))."\n");
        $p->textRaw(EscPosCharset::encode((string) ($emitente['endereco'] ?? ''))."\n");
        $p->textRaw(EscPosCharset::encode(trim(($emitente['municipio'] ?? '').' - '.($emitente['uf'] ?? '')))."\n");
        $p->textRaw(str_repeat('-', 48)."\n");
        $p->setEmphasis(true);
        $p->textRaw("DANFE NFC-e\n");
        $p->setEmphasis(false);
        $p->textRaw(EscPosCharset::encode((string) ($data['ambienteLabel'] ?? ''))."\n");
        $p->textRaw(EscPosCharset::encode('Emissao: '.$data['dataEmissao'].' '.$data['horaEmissao'])."\n");
        $p->textRaw(EscPosCharset::encode('Operador: '.$data['usuario'])."\n");

        if (! empty($data['numeroPedido'])) {
            $p->textRaw(EscPosCharset::encode('Numero: '.$data['numeroPedido'])."\n");
        }

        $p->textRaw(EscPosCharset::encode('DAV: '.($data['numeroPdv'] ?? str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT)))."\n");

        if (! empty($data['vendedorNome'])) {
            $p->textRaw(EscPosCharset::encode('Vendedor: '.$data['vendedorNome'])."\n");
        }

        if (! empty($data['consumidorNome'])) {
            $p->textRaw(EscPosCharset::encode('Consumidor: '.$data['consumidorNome'])."\n");
            if (! empty($data['consumidorEndereco'])) {
                $p->textRaw(EscPosCharset::encode('Endereco: '.$data['consumidorEndereco'])."\n");
            }
        }

        $p->textRaw(str_repeat('-', 48)."\n");
        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->setFont(Printer::FONT_B);
        $p->setLineSpacing(24);

        $p->setEmphasis(true);
        $p->textRaw(EscPosCharset::encode(PdvDavCupomLayout::itemHeaderLine($wItems))."\n");
        $p->setEmphasis(false);
        $p->textRaw(str_repeat('-', $wItems)."\n");

        $descricaoCompleta = (bool) ($this->empresa?->param_pdv_nfce_descricao_completa ?? false);
        $itens = $venda->itens->values();
        $ultimo = $itens->count() - 1;
        foreach ($itens as $index => $item) {
            foreach (PdvDavCupomLayout::formatItemLines($item, $wItems, $descricaoCompleta) as $linha) {
                $p->textRaw(EscPosCharset::encode($linha)."\n");
            }
            if ($index < $ultimo) {
                $p->textRaw("\n");
            }
        }

        $p->setFont(Printer::FONT_A);
        $p->setLineSpacing(30);
        $p->textRaw(str_repeat('-', 48)."\n");
        $p->textRaw(EscPosCharset::encode('Subtotal: R$ '.number_format((float) $venda->subtotal, 2, ',', ''))."\n");

        if ((float) $venda->desconto > 0) {
            $p->textRaw(EscPosCharset::encode('Desconto: R$ '.number_format((float) $venda->desconto, 2, ',', ''))."\n");
        }
        if ((float) $venda->acrescimo > 0) {
            $p->textRaw(EscPosCharset::encode('Acrescimo: R$ '.number_format((float) $venda->acrescimo, 2, ',', ''))."\n");
        }

        $p->setEmphasis(true);
        $p->textRaw(EscPosCharset::encode('TOTAL NFC-e: R$ '.number_format((float) $venda->total, 2, ',', ''))."\n");
        $p->setEmphasis(false);

        if ((float) $venda->troco > 0) {
            $p->textRaw(EscPosCharset::encode('Troco: R$ '.number_format((float) $venda->troco, 2, ',', ''))."\n");
        }

        $economizado = (float) ($data['economizado'] ?? 0);
        if ($economizado > 0) {
            $p->setEmphasis(true);
            $p->textRaw(EscPosCharset::encode('Voce economizou: R$ '.number_format($economizado, 2, ',', ''))."\n");
            $p->setEmphasis(false);
        }

        if ($venda->pagamentos->isNotEmpty()) {
            foreach ($venda->pagamentos as $pagamento) {
                $p->textRaw(EscPosCharset::encode($pagamento->linhaCupom())."\n");
            }
        }

        if (! empty($data['cpfNota'])) {
            $p->textRaw(EscPosCharset::encode('CPF: '.$data['cpfNota'])."\n");
        }

        $observacoes = trim((string) ($venda->observacoes ?? ''));
        if ($observacoes !== '') {
            foreach (preg_split('/\R/u', wordwrap($observacoes, 48, "\n", true)) ?: [] as $linhaObs) {
                if (trim($linhaObs) === '') {
                    continue;
                }
                $p->textRaw(EscPosCharset::encode($linhaObs)."\n");
            }
        }

        $obsNfce = trim((string) ($data['obsNfce'] ?? ''));
        if ($obsNfce !== '') {
            foreach (preg_split('/\R/u', wordwrap($obsNfce, 48, "\n", true)) ?: [] as $linhaObsNfce) {
                if (trim($linhaObsNfce) === '') {
                    continue;
                }
                $p->textRaw(EscPosCharset::encode($linhaObsNfce)."\n");
            }
        }

        $p->textRaw(str_repeat('-', 48)."\n");

        $qr = trim((string) ($data['qrTexto'] ?? ''));
        $chave = preg_replace('/\D+/', '', (string) ($data['chave'] ?? '')) ?: (string) ($data['chave'] ?? '');
        $protocoloLabel = ! empty($data['simulada']) ? 'Protocolo (simulado)' : 'Protocolo autorizacao';
        $infoLines = array_values(array_filter([
            $protocoloLabel,
            (string) ($data['protocoloFormatado'] ?? ''),
            'No '.$data['numeroNf'].' Serie '.$data['serie'].' Mod '.$data['modelo'],
            'Chave de acesso:',
            ...NfceQrInfoRaster::wrapChave($chave, 20),
        ], static fn (string $line): bool => trim($line) !== ''));

        $p->setJustification(Printer::JUSTIFY_CENTER);
        if ($qr !== '') {
            try {
                $img = NfceQrInfoRaster::toEscposImage($qr, $infoLines);
                $p->bitImage($img);
            } catch (\Throwable) {
                // Fallback textual se a impressora/raster falhar.
                $p->setJustification(Printer::JUSTIFY_LEFT);
                foreach ($infoLines as $line) {
                    $p->textRaw(EscPosCharset::encode($line)."\n");
                }
                try {
                    $p->setJustification(Printer::JUSTIFY_CENTER);
                    $p->qrCode($qr, Printer::QR_ECLEVEL_L, 5);
                } catch (\Throwable) {
                    // segue sem QR
                }
            }
        } else {
            $p->setJustification(Printer::JUSTIFY_LEFT);
            foreach ($infoLines as $line) {
                $p->textRaw(EscPosCharset::encode($line)."\n");
            }
        }

        $p->setJustification(Printer::JUSTIFY_LEFT);
        $p->setFont(Printer::FONT_A);
        $p->textRaw(str_repeat('-', 48)."\n");

        $textoIbpt = trim((string) ($data['textoIbpt'] ?? ''));
        if ($textoIbpt !== '') {
            $p->textRaw(EscPosCharset::encode($textoIbpt)."\n");
        } elseif ((float) ($data['vTotTrib'] ?? 0) > 0) {
            $p->textRaw(EscPosCharset::encode(sprintf(
                'Trib. aprox. Fed. R$ %s Est. R$ %s Mun. R$ %s (Lei 12.741/2012 - IBPT)',
                number_format((float) ($data['tribFed'] ?? 0), 2, ',', ''),
                number_format((float) ($data['tribEst'] ?? 0), 2, ',', ''),
                number_format((float) ($data['tribMun'] ?? 0), 2, ',', ''),
            ))."\n");
        } else {
            $p->textRaw(EscPosCharset::encode('Tributos aprox. conforme Lei 12.741/2012 - IBPT.')."\n");
        }

        $p->setJustification(Printer::JUSTIFY_CENTER);
        $p->setEmphasis(true);
        $p->textRaw(EscPosCharset::encode('DESENVOLVIDO POR UNITECNOLOGIA SISTEMAS LTDA')."\n");
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
