<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceSimuladaService;
use App\Support\ForcaVendas\ForcaVendasPairing;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Carbon;
use Throwable;
use Unitec\FiscalEngine\Nfce\ScNfceEndpoints;

/**
 * Dados do DANFE NFC-e A4 (Manual DANFE NFC-e 6.0).
 *
 * Parte dos mesmos dados do cupom térmico (PdvNfceSimuladaService) e, quando houver XML
 * gravado, usa os valores fiscais do próprio XML (itens, totais, pagamento, urlChave, protocolo).
 * Não altera nada do layout térmico.
 */
final class NfceDanfeA4Data
{
    public const MSG_HOMOLOGACAO = 'EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO – SEM VALOR FISCAL';

    public const MSG_CONTINGENCIA = 'EMITIDA EM CONTINGÊNCIA';

    public const MSG_PENDENTE = 'Pendente de autorização';

    public const MSG_SIMULADA = 'DOCUMENTO SIMULADO – SEM VALOR FISCAL';

    public const MSG_CANCELADA = 'NFC-e CANCELADA';

    public function __construct(
        private readonly PdvNfceSimuladaService $cupom = new PdvNfceSimuladaService(),
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        string $operacao = PdvFinalizarOperacao::NFCE_TRANSMITIR,
        int $copias = 1,
        bool $autoPrint = false,
    ): array {
        $base = $this->cupom->buildViewData(
            venda: $venda,
            empresa: $empresa,
            usuario: $usuario,
            operacao: $operacao,
            copias: $copias,
            autoPrint: $autoPrint,
        );

        /** @var PdvVendaNfce|null $documento */
        $documento = $venda->nfce;
        $simulada = (bool) ($base['simulada'] ?? true);
        $x = $simulada ? null : $this->xpath($documento?->xml);

        $tpAmb = (int) ($this->v($x, $this->q('ide', 'tpAmb')) ?: ($documento?->ambiente ?: 2));
        $tpEmis = (string) ($this->v($x, $this->q('ide', 'tpEmis')) ?: ($documento?->tipo_emissao ?: ''));
        $tpEmis = ltrim($tpEmis, '0');
        $status = (string) ($documento?->status ?? '');

        $contingencia = ! $simulada && (
            $tpEmis === '9'
            || $status === PdvVendaNfce::STATUS_CONTINGENCIA
            || (string) ($base['operacao'] ?? '') === PdvFinalizarOperacao::NFCE_CONTINGENCIA
        );
        $autorizada = ! $simulada && in_array($status, [PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_CANCELADA], true);

        [$dataEmissao, $horaEmissao] = $this->emissao($x, $base);

        $itens = $x ? $this->itensDoXml($x) : [];
        if ($itens === []) {
            $itens = $this->itensDaVenda($venda);
        }

        $totais = $x ? $this->totaisDoXml($x) : null;
        $totais ??= [
            'valor_total' => round((float) $venda->subtotal, 2),
            'descontos' => round((float) $venda->desconto, 2),
            'acrescimos' => round((float) $venda->acrescimo, 2),
            'valor_pagar' => round((float) $venda->total, 2),
        ];

        $trocoXml = $this->v($x, $this->q('pag', 'vTroco'));
        $troco = $trocoXml !== '' ? round((float) $trocoXml, 2) : round((float) $venda->troco, 2);

        $chave = preg_replace('/\D/', '', (string) ($base['chave'] ?? '')) ?? '';
        $qrTexto = (string) ($this->v($x, $this->q('infNFeSupl', 'qrCode')) ?: ($base['qrTexto'] ?? ''));

        $protocolo = '';
        $dhAutorizacao = '';
        if ($autorizada) {
            $protocolo = (string) ($this->v($x, $this->q('infProt', 'nProt')) ?: ($documento?->protocolo ?? ''));
            $dhAutorizacao = $this->formatarDataHora(
                $this->v($x, $this->q('infProt', 'dhRecbto')) ?: $documento?->autorizada_em?->toIso8601String()
            );
        }

        $cpf = (string) ($base['cpfNota'] ?? '');

        return array_merge($base, [
            'logoDataUri' => $this->danfe->logoDataUri($empresa),
            'itensA4' => $itens,
            'qtdItens' => count($itens),
            'totaisA4' => $totais,
            'pagamentosA4' => $this->pagamentos($venda, (float) $totais['valor_pagar']),
            'trocoA4' => $troco,
            'urlConsulta' => $this->urlConsulta($x, $empresa, $tpAmb),
            'chaveBlocos' => trim(chunk_split($chave, 4, ' ')),
            'consumidorIdentificado' => $cpf !== '',
            'consumidorDocumento' => $cpf !== '' ? 'CPF: '.$cpf : '',
            'dataEmissaoA4' => $dataEmissao,
            'horaEmissaoA4' => $horaEmissao,
            'protocoloA4' => $protocolo,
            'dhAutorizacaoA4' => $dhAutorizacao,
            'homologacao' => ! $simulada && $tpAmb !== 1,
            'contingencia' => $contingencia,
            'pendenteAutorizacao' => $contingencia && ! $autorizada,
            'cancelada' => ! $simulada && $status === PdvVendaNfce::STATUS_CANCELADA,
            'qrImagemDataUri' => $qrTexto !== '' ? $this->qrDataUri($qrTexto) : null,
            'tributosTexto' => trim(implode(' ', array_map('trim', (array) ($base['linhasIbpt'] ?? [])))),
        ]);
    }

    /**
     * @return list<array{codigo: string, descricao: string, quantidade: string, unidade: string, unitario: float, total: float, desconto: float}>
     */
    private function itensDoXml(DOMXPath $x): array
    {
        $itens = [];

        foreach ($x->query("//*[local-name()='det']") ?: [] as $det) {
            $p = "*[local-name()='prod']/*[local-name()='%s']";
            $itens[] = [
                'codigo' => $this->v($x, sprintf($p, 'cProd'), $det),
                'descricao' => $this->v($x, sprintf($p, 'xProd'), $det),
                'quantidade' => $this->formatarQuantidade((float) $this->v($x, sprintf($p, 'qCom'), $det)),
                'unidade' => $this->v($x, sprintf($p, 'uCom'), $det),
                'unitario' => round((float) $this->v($x, sprintf($p, 'vUnCom'), $det), 2),
                'total' => round((float) $this->v($x, sprintf($p, 'vProd'), $det), 2),
                'desconto' => round((float) $this->v($x, sprintf($p, 'vDesc'), $det), 2),
            ];
        }

        return $itens;
    }

    /**
     * @return list<array{codigo: string, descricao: string, quantidade: string, unidade: string, unitario: float, total: float, desconto: float}>
     */
    private function itensDaVenda(PdvVenda $venda): array
    {
        return $venda->itens->values()->map(fn ($item): array => [
            'codigo' => (string) ($item->codigo ?: $item->product?->codigo ?: $item->product_id),
            'descricao' => mb_strtoupper((string) $item->descricao, 'UTF-8'),
            'quantidade' => $this->formatarQuantidade((float) $item->quantidade),
            'unidade' => mb_strtoupper((string) ($item->unidade ?: 'UN'), 'UTF-8'),
            'unitario' => round((float) $item->preco_unitario, 2),
            'total' => round((float) $item->total, 2),
            'desconto' => 0.0,
        ])->all();
    }

    /**
     * @return array{valor_total: float, descontos: float, acrescimos: float, valor_pagar: float}|null
     */
    private function totaisDoXml(DOMXPath $x): ?array
    {
        $vNf = $this->v($x, $this->q('ICMSTot', 'vNF'));

        if ($vNf === '') {
            return null;
        }

        return [
            'valor_total' => round((float) $this->v($x, $this->q('ICMSTot', 'vProd')), 2),
            'descontos' => round((float) $this->v($x, $this->q('ICMSTot', 'vDesc')), 2),
            'acrescimos' => round((float) $this->v($x, $this->q('ICMSTot', 'vOutro'))
                + (float) $this->v($x, $this->q('ICMSTot', 'vFrete'))
                + (float) $this->v($x, $this->q('ICMSTot', 'vSeg')), 2),
            'valor_pagar' => round((float) $vNf, 2),
        ];
    }

    /**
     * @return list<array{forma: string, valor: float}>
     */
    private function pagamentos(PdvVenda $venda, float $valorPagar): array
    {
        $linhas = $venda->pagamentos
            ->map(fn ($pagamento): array => [
                'forma' => mb_strtoupper(trim((string) $pagamento->forma), 'UTF-8') ?: 'OUTROS',
                'valor' => round((float) $pagamento->valor, 2),
            ])
            ->filter(fn (array $linha): bool => $linha['valor'] > 0)
            ->values()
            ->all();

        if ($linhas === []) {
            $linhas[] = [
                'forma' => mb_strtoupper(trim((string) ($venda->forma_pagamento ?: 'DINHEIRO')), 'UTF-8'),
                'valor' => round($valorPagar, 2),
            ];
        }

        return $linhas;
    }

    private function urlConsulta(?DOMXPath $x, ?Empresa $empresa, int $tpAmb): string
    {
        $url = $this->v($x, $this->q('infNFeSupl', 'urlChave'));

        if ($url === '' && strtoupper((string) ($empresa?->uf ?? '')) === 'SC' && class_exists(ScNfceEndpoints::class)) {
            $url = ScNfceEndpoints::consultaQrCode($tpAmb === 1 ? 1 : 2);
        }

        return $url;
    }

    /**
     * @param  array<string, mixed>  $base
     * @return array{0: string, 1: string}
     */
    private function emissao(?DOMXPath $x, array $base): array
    {
        $dhEmi = $this->v($x, $this->q('ide', 'dhEmi'));

        if ($dhEmi !== '') {
            try {
                $local = Carbon::parse($dhEmi)->setTimezone(ErpTimezone::DEFAULT);

                return [$local->format('d/m/Y'), $local->format('H:i:s')];
            } catch (Throwable) {
            }
        }

        return [(string) ($base['dataEmissao'] ?? ''), (string) ($base['horaEmissao'] ?? '')];
    }

    private function formatarDataHora(?string $valor): string
    {
        if (blank($valor)) {
            return '';
        }

        try {
            return Carbon::parse((string) $valor)->setTimezone(ErpTimezone::DEFAULT)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return '';
        }
    }

    private function formatarQuantidade(float $quantidade): string
    {
        $texto = number_format($quantidade, 3, ',', '.');

        return rtrim(rtrim($texto, '0'), ',');
    }

    /** PNG via GD (nítido no navegador e no DomPDF); sem GD cai para SVG. */
    private function qrDataUri(string $texto): ?string
    {
        try {
            if (! extension_loaded('gd')) {
                return 'data:image/svg+xml;base64,'.base64_encode(ForcaVendasPairing::qrSvg($texto, 300));
            }

            $matrix = Encoder::encode($texto, ErrorCorrectionLevel::M())->getMatrix();
            $modulos = $matrix->getWidth();
            $escala = max(4, (int) floor(400 / ($modulos + 8)));
            $margem = 4 * $escala;
            $lado = $modulos * $escala + 2 * $margem;

            $img = imagecreate($lado, $lado);
            $branco = imagecolorallocate($img, 255, 255, 255);
            $preto = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $branco);

            for ($y = 0; $y < $modulos; $y++) {
                for ($xx = 0; $xx < $modulos; $xx++) {
                    if ($matrix->get($xx, $y) === 1) {
                        $px = $margem + $xx * $escala;
                        $py = $margem + $y * $escala;
                        imagefilledrectangle($img, $px, $py, $px + $escala - 1, $py + $escala - 1, $preto);
                    }
                }
            }

            ob_start();
            imagepng($img);
            $png = (string) ob_get_clean();
            imagedestroy($img);

            return 'data:image/png;base64,'.base64_encode($png);
        } catch (Throwable) {
            return null;
        }
    }

    private function xpath(?string $xml): ?DOMXPath
    {
        $xml = trim((string) $xml);

        if ($xml === '') {
            return null;
        }

        $dom = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        return $ok ? new DOMXPath($dom) : null;
    }

    /** Caminho por local-name (XML com ou sem namespace da NF-e). */
    private function q(string ...$nos): string
    {
        return '//'.implode('/', array_map(fn (string $no): string => "*[local-name()='{$no}']", $nos));
    }

    private function v(?DOMXPath $x, string $caminho, ?DOMNode $contexto = null): string
    {
        if ($x === null) {
            return '';
        }

        $nodes = $contexto !== null ? $x->query($caminho, $contexto) : $x->query($caminho);

        return $nodes !== false && $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }
}
