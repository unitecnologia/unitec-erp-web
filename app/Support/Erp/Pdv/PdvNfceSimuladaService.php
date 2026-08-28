<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvVenda;
use App\Support\Erp\Compra\CompraDanfeReportService;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;
use App\Support\ForcaVendas\ForcaVendasPairing;
use Illuminate\Support\Carbon;

final class PdvNfceSimuladaService
{
    private const UF_IBGE = [
        'RO' => '11', 'AC' => '12', 'AM' => '13', 'RR' => '14', 'PA' => '15', 'AP' => '16', 'TO' => '17',
        'MA' => '21', 'PI' => '22', 'CE' => '23', 'RN' => '24', 'PB' => '25', 'PE' => '26', 'AL' => '27',
        'SE' => '28', 'BA' => '29', 'MG' => '31', 'ES' => '32', 'RJ' => '33', 'SP' => '35', 'PR' => '41',
        'SC' => '42', 'RS' => '43', 'MS' => '50', 'MT' => '51', 'GO' => '52', 'DF' => '53',
    ];

    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(
        PdvVenda $venda,
        ?Empresa $empresa,
        string $usuario,
        string $operacao,
        int $copias = 1,
        bool $autoPrint = false,
        ?Carbon $printedAt = null,
    ): array {
        $venda->loadMissing(['itens.product', 'pagamentos', 'person', 'nfce', 'venda', 'vendedor']);
        $printedAt ??= now();
        $documento = $venda->nfce;
        $operacao = $this->resolveOperacao($venda, $operacao, $documento);
        $chave = $documento?->chave ?? $this->gerarChaveAcesso($empresa, $venda, $operacao);
        $emissao = ErpTimezone::toLocal($venda->fechado_em ?? $printedAt);
        $serie = str_pad((string) ($documento?->serie ?? '1'), 3, '0', STR_PAD_LEFT);
        $numeroNf = str_pad((string) ($documento?->numero ?? $venda->numero), 9, '0', STR_PAD_LEFT);
        $protocolo = $documento?->protocolo ?? $this->gerarProtocolo($venda);
        $qrTexto = $documento?->qr_code_conteudo ?? $this->gerarQrTexto($chave, $operacao);
        $ibpt = $this->calcularIbptDaVenda($venda);
        $consumidor = NfceConsumidorIdentificado::resolvePerson($venda);
        $numeroPdv = str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);
        $numeroPedido = $venda->venda?->numero !== null
            ? str_pad((string) $venda->venda->numero, 6, '0', STR_PAD_LEFT)
            : null;
        $vendedorNome = trim((string) ($venda->vendedor_nome ?: $venda->vendedor?->nome ?: ''));

        return [
            'venda' => $venda,
            'empresa' => $empresa,
            'usuario' => $usuario,
            'operacao' => $operacao,
            'simulada' => $documento === null || $documento->simulada,
            'pageTitle' => $this->pageTitle($documento, $venda),
            'modoLabel' => $this->modoLabel($operacao),
            'statusLabel' => $this->statusLabel($operacao, $documento),
            'ambienteLabel' => $this->ambienteLabel($documento),
            'chave' => $chave,
            'chaveFormatada' => $this->danfe->formatChave($chave),
            'barcodeDataUri' => $this->danfe->barcodeDataUri($chave),
            'qrTexto' => $qrTexto,
            'qrSvg' => ForcaVendasPairing::qrSvg($qrTexto, 140),
            'protocolo' => $protocolo,
            'protocoloFormatado' => $this->formatarProtocolo($protocolo),
            'serie' => str_pad($serie, 3, '0', STR_PAD_LEFT),
            'numeroNf' => $this->danfe->formatNumeroNota($numeroNf),
            'modelo' => '65',
            'naturezaOperacao' => 'VENDA',
            'dataEmissao' => $emissao->format('d/m/Y'),
            'horaEmissao' => $emissao->format('H:i:s'),
            'emitente' => $this->buildEmitente($empresa),
            'consumidorNome' => NfceConsumidorIdentificado::nome($consumidor),
            'consumidorEndereco' => NfceConsumidorIdentificado::endereco($consumidor),
            'cpfNota' => NfceConsumidorIdentificado::cpfFormatado($venda),
            'numeroPdv' => $numeroPdv,
            'numeroPedido' => $numeroPedido,
            'vendedorNome' => $vendedorNome !== '' ? $vendedorNome : null,
            'obsNfce' => trim((string) ($empresa?->obs_nfce ?? '')),
            'textoIbpt' => $ibpt['texto'],
            'tribFed' => $ibpt['trib_fed'],
            'tribEst' => $ibpt['trib_est'],
            'tribMun' => $ibpt['trib_mun'],
            'vTotTrib' => $ibpt['v_tot_trib'],
            'economizado' => PdvDavCupomLayout::totalDescontosEconomizados($venda),
            'itemHeader' => PdvDavCupomLayout::itemHeaderLine(PdvDavCupomLayout::WIDTH_ITEMS),
            'itemLines' => $venda->itens->map(
                static fn ($item) => PdvDavCupomLayout::formatItemLines(
                    $item,
                    PdvDavCupomLayout::WIDTH_ITEMS,
                    (bool) ($empresa?->param_pdv_nfce_descricao_completa ?? false),
                )
            )->all(),
            'copias' => max(1, min(3, $copias)),
            'autoPrint' => $autoPrint,
            'printedAt' => $printedAt,
        ];
    }

    /**
     * @return array{texto: string, trib_fed: float, trib_est: float, trib_mun: float, v_tot_trib: float}
     */
    private function calcularIbptDaVenda(PdvVenda $venda): array
    {
        $lookup = app(\App\Support\Erp\Fiscal\IbptLookupService::class);
        $rows = [];

        foreach ($venda->itens as $item) {
            $ibpt = $lookup->calcularParaProduto($item->product, (float) $item->total);
            $rows[] = [
                'trib_fed' => $ibpt['trib_fed'],
                'trib_est' => $ibpt['trib_est'],
                'trib_mun' => $ibpt['trib_mun'],
                'trib_imp' => $ibpt['trib_imp'],
                'ibpt_fonte' => $ibpt['fonte'],
                'ibpt_chave' => $ibpt['chave'],
                'ibpt_versao' => $ibpt['versao'],
            ];
        }

        $agg = $lookup->agregarItens($rows);

        return [
            'texto' => $lookup->formatarTextoLei12741($agg),
            'trib_fed' => (float) $agg['trib_fed'],
            'trib_est' => (float) $agg['trib_est'],
            'trib_mun' => (float) $agg['trib_mun'],
            'v_tot_trib' => (float) $agg['v_tot_trib'],
        ];
    }

    public function gerarChaveAcesso(?Empresa $empresa, PdvVenda $venda, string $operacao): string
    {
        $uf = self::UF_IBGE[strtoupper((string) ($empresa?->uf ?? 'SC'))] ?? '42';
        $emissao = ErpTimezone::toLocal($venda->fechado_em ?? now());
        $aamm = $emissao->format('ym');
        $cnpj = str_pad(substr(preg_replace('/\D/', '', (string) ($empresa?->cnpj ?? '00000000000000')) ?: '0', 0, 14), 14, '0', STR_PAD_LEFT);
        $modelo = '65';
        $serie = '001';
        $numero = str_pad((string) $venda->numero, 9, '0', STR_PAD_LEFT);
        $tpEmis = $operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA ? '9' : '1';
        $cNf = str_pad((string) (($venda->id * 97 + $venda->numero) % 99999999), 8, '0', STR_PAD_LEFT);
        $base43 = $uf . $aamm . $cnpj . $modelo . $serie . $numero . $tpEmis . $cNf;
        $dv = $this->calcularDigitoVerificador($base43);

        return $base43 . (string) $dv;
    }

    public function gerarProtocolo(PdvVenda $venda): string
    {
        $seed = str_pad((string) (($venda->id * 1000) + $venda->numero), 12, '0', STR_PAD_LEFT);

        return '999' . substr($seed, -12);
    }

    public function gerarQrTexto(string $chave, string $operacao): string
    {
        $tipo = $operacao === PdvFinalizarOperacao::NFCE_CONTINGENCIA ? '9' : '1';

        return 'NFC-e SIMULADA|chNFe=' . $chave . '|tpAmb=2|tpEmis=' . $tipo;
    }

    private function ambienteLabel(?\App\Models\PdvVendaNfce $documento): string
    {
        if ($documento !== null && ! $documento->simulada) {
            if ($documento->status === \App\Models\PdvVendaNfce::STATUS_CONTINGENCIA) {
                return $documento->ambiente === \App\Models\PdvVendaNfce::AMBIENTE_PRODUCAO
                    ? 'CONTINGÊNCIA — PRODUÇÃO'
                    : 'CONTINGÊNCIA — HOMOLOGAÇÃO';
            }

            return $documento->ambiente === \App\Models\PdvVendaNfce::AMBIENTE_PRODUCAO
                ? 'PRODUÇÃO'
                : 'HOMOLOGAÇÃO';
        }

        return 'SIMULADO — SEM VALOR FISCAL';
    }

    private function pageTitle(?\App\Models\PdvVendaNfce $documento, PdvVenda $venda): string
    {
        $numero = str_pad((string) ($documento?->numero ?? $venda->numero), 6, '0', STR_PAD_LEFT);

        if ($documento !== null && ! $documento->simulada) {
            return 'NFC-e #' . $numero;
        }

        return 'NFC-e Simulada #' . $numero;
    }

    private function resolveOperacao(PdvVenda $venda, string $operacao, ?\App\Models\PdvVendaNfce $documento = null): string
    {
        if ($documento !== null && filled($documento->operacao)) {
            return (string) $documento->operacao;
        }

        if (filled($venda->nfce_operacao)) {
            return (string) $venda->nfce_operacao;
        }

        return $operacao;
    }

    private function modoLabel(string $operacao): string
    {
        return match ($operacao) {
            PdvFinalizarOperacao::NFCE_CONTINGENCIA => 'CONTINGÊNCIA OFFLINE',
            PdvFinalizarOperacao::FINALIZAR => 'FINALIZAÇÃO FISCAL',
            default => 'EMISSÃO NORMAL',
        };
    }

    private function statusLabel(string $operacao, ?\App\Models\PdvVendaNfce $documento = null): string
    {
        if ($documento !== null && ! $documento->simulada) {
            return match ($documento->status) {
                \App\Models\PdvVendaNfce::STATUS_CONTINGENCIA => 'CONTINGÊNCIA',
                \App\Models\PdvVendaNfce::STATUS_AUTORIZADA => 'AUTORIZADA',
                \App\Models\PdvVendaNfce::STATUS_CANCELADA => 'CANCELADA',
                \App\Models\PdvVendaNfce::STATUS_REJEITADA => 'REJEITADA',
                default => mb_strtoupper((string) $documento->status, 'UTF-8'),
            };
        }

        return match ($operacao) {
            PdvFinalizarOperacao::NFCE_CONTINGENCIA => 'CONTINGÊNCIA (SIMULADO)',
            default => 'AUTORIZADA (SIMULADO)',
        };
    }

    private function formatarProtocolo(string $protocolo): string
    {
        $digits = preg_replace('/\D/', '', $protocolo) ?? '';

        if (strlen($digits) < 15) {
            return $protocolo;
        }

        return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6, 3) . ' ' . substr($digits, 9);
    }

    /**
     * @return array<string, string>
     */
    private function buildEmitente(?Empresa $empresa): array
    {
        if ($empresa === null) {
            return [
                'nome' => 'UNITEC',
                'fantasia' => 'UNITEC',
                'cnpj' => '',
                'ie' => '',
                'endereco' => '',
                'municipio' => '',
                'uf' => '',
                'telefone' => '',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) ($empresa->endereco ?? '')),
            filled($empresa->numero) ? 'nº ' . $empresa->numero : null,
            trim((string) ($empresa->bairro ?? '')),
        ])));

        if (filled($empresa->cep)) {
            $endereco .= ($endereco !== '' ? ' — ' : '') . 'CEP ' . $empresa->cep;
        }

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'fantasia' => mb_strtoupper((string) ($empresa->fantasia ?: $empresa->nome), 'UTF-8'),
            'cnpj' => $this->formatCnpj((string) $empresa->cnpj),
            'ie' => (string) ($empresa->ie ?? ''),
            'endereco' => mb_strtoupper($endereco, 'UTF-8'),
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'uf' => (string) ($empresa->uf ?? ''),
            'telefone' => (string) ($empresa->telefone ?? ''),
        ];
    }

    private function formatCnpj(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) !== 14) {
            return $value;
        }

        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
    }

    private function calcularDigitoVerificador(string $chave43): int
    {
        $multiplicadores = [2, 3, 4, 5, 6, 7, 8, 9];
        $soma = 0;
        $pos = 0;

        for ($i = strlen($chave43) - 1; $i >= 0; $i--) {
            $soma += (int) $chave43[$i] * $multiplicadores[$pos % 8];
            $pos++;
        }

        $resto = $soma % 11;

        if ($resto === 0 || $resto === 1) {
            return 0;
        }

        return 11 - $resto;
    }
}
