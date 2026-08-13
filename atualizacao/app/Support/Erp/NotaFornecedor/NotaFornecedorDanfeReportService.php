<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Models\ContadorCloudSyncLog;
use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Support\Erp\Compra\CompraDanfeReportService;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\Auth;

class NotaFornecedorDanfeReportService
{
    public function __construct(
        private readonly CompraDanfeReportService $danfe = new CompraDanfeReportService(),
    ) {}

    public function resolveEmpresa(?int $empresaId = null): ?Empresa
    {
        $empresaId ??= session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? Empresa::query()->find($empresaId) : Auth::user()?->empresa;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildViewData(NotaFornecedor $nota, ?Empresa $empresa = null): array
    {
        $empresa ??= $nota->empresa ?? $this->resolveEmpresa($nota->empresa_id);
        $xml = $this->resolveXml($nota);
        $fromXml = $xml !== null ? $this->parseXml($xml) : null;

        if ($fromXml !== null) {
            $emitente = $fromXml['emitente'];
            $destinatario = $fromXml['destinatario'] ?? $this->buildDestinatarioFromEmpresa($empresa);
            $itens = $fromXml['itens'];
            $totais = $fromXml['totais'];
            $natureza = $fromXml['natureza_operacao'];
            $protocolo = $fromXml['protocolo'];
            $serie = $fromXml['serie'];
            $tipoOperacao = $fromXml['tipo_operacao'];
            $tipoOperacaoLabel = $fromXml['tipo_operacao_label'];
            $numeroNota = $this->danfe->formatNumeroNota($fromXml['numero'] ?: $nota->numero);
            $dataEmissao = $fromXml['data_emissao'] ?: ($nota->data_emissao?->format('d/m/Y') ?? '');
            $dataEntrada = $fromXml['data_entrada'] ?: ($nota->data_entrada?->format('d/m/Y') ?? '');
            $horaEntrada = (string) ($fromXml['hora_entrada'] ?? '');
            $informacoes = $fromXml['informacoes_complementares'];
            $informacoesFisco = (string) ($fromXml['informacoes_fisco'] ?? '');
            $transportador = $fromXml['transportador'] ?? $this->emptyTransportador();
            $volumes = $fromXml['volumes'] ?? $this->emptyVolumes();
            $duplicatas = $fromXml['duplicatas'] ?? [];
            $fatura = $fromXml['fatura'] ?? $this->emptyFatura();
            $chaveXml = preg_replace('/\D/', '', (string) ($fromXml['chave'] ?? '')) ?? '';
        } else {
            $emitente = $this->buildEmitenteFromNota($nota);
            $destinatario = $this->buildDestinatarioFromEmpresa($empresa);
            $total = (float) $nota->total;
            $itens = [[
                'item' => '1',
                'codigo' => '—',
                'descricao' => 'ITENS CONFORME XML DA NF-e (resumo DF-e — detalhamento indisponível)',
                'ncm' => '',
                'cst' => '',
                'cfop' => '',
                'un' => 'UN',
                'quant' => '1,0000',
                'valor_unit' => number_format($total, 4, ',', '.'),
                'valor_total' => number_format($total, 2, ',', '.'),
                'desconto' => '0,00',
                'base_icms' => '0,00',
                'valor_icms' => '0,00',
                'valor_ipi' => '0,00',
                'aliq_icms' => '0,00',
                'aliq_ipi' => '0,00',
            ]];
            $totais = $this->emptyTotais($total);
            $natureza = 'ENTRADA DE MERCADORIAS';
            $protocolo = '';
            $keyParts = $this->danfe->extractNfeKeyParts($nota->chave);
            $serie = str_pad($keyParts['serie'], 3, '0', STR_PAD_LEFT);
            $tipoOperacao = '1';
            $tipoOperacaoLabel = 'SAÍDA';
            $numeroNota = $this->danfe->formatNumeroNota($nota->numero);
            $dataEmissao = $nota->data_emissao?->format('d/m/Y') ?? '';
            $dataEntrada = $nota->data_entrada?->format('d/m/Y') ?? '';
            $horaEntrada = '';
            $informacoes = implode("\n", array_filter([
                filled($nota->chave) ? 'CHAVE NF-e: ' . preg_replace('/\D/', '', (string) $nota->chave) : null,
                'Documento gerado a partir do resumo DF-e. Itens detalhados exigem o XML completo (procNFe).',
            ]));
            $informacoesFisco = '';
            $transportador = $this->emptyTransportador();
            $volumes = $this->emptyVolumes();
            $duplicatas = [];
            $fatura = $this->emptyFatura();
            $chaveXml = '';
        }

        $chave = strlen($chaveXml) === 44
            ? $chaveXml
            : (preg_replace('/\D/', '', (string) $nota->chave) ?? '');

        return [
            'nota' => $nota,
            'empresa' => $empresa,
            'emitente' => $emitente,
            'destinatario' => $destinatario,
            'chave' => $chave,
            'chaveFormatada' => $this->danfe->formatChave($chave),
            'barcodeDataUri' => $this->danfe->barcodeDataUri($chave),
            'numeroNota' => $numeroNota,
            'serie' => $serie,
            'modelo' => '55',
            'tipoOperacao' => $tipoOperacao,
            'tipoOperacaoLabel' => $tipoOperacaoLabel,
            'naturezaOperacao' => $natureza,
            'protocolo' => $protocolo,
            'dataEmissao' => $dataEmissao,
            'dataEntrada' => $dataEntrada,
            'horaEntrada' => $horaEntrada,
            'itens' => $itens,
            'totais' => $totais,
            'transportador' => $transportador,
            'volumes' => $volumes,
            'duplicatas' => $duplicatas,
            'fatura' => $fatura,
            'informacoesComplementares' => $informacoes,
            'informacoesFisco' => $informacoesFisco,
            'printedAt' => now(),
            'espelho' => false,
        ];
    }

    public function resolveXml(NotaFornecedor $nota): ?string
    {
        if (filled($nota->xml) && $this->isProcNfeXml((string) $nota->xml)) {
            return (string) $nota->xml;
        }

        if (filled($nota->xml) && str_contains((string) $nota->xml, '<NFe')) {
            return (string) $nota->xml;
        }

        $fromLog = $this->xmlFromSyncLog($nota);

        if ($fromLog !== null && ($this->isProcNfeXml($fromLog) || str_contains($fromLog, '<NFe'))) {
            if (blank($nota->xml) || ! $this->isProcNfeXml((string) $nota->xml)) {
                $nota->forceFill(['xml' => $fromLog])->saveQuietly();
            }

            return $fromLog;
        }

        return filled($nota->xml) ? (string) $nota->xml : $fromLog;
    }

    private function xmlFromSyncLog(NotaFornecedor $nota): ?string
    {
        if (blank($nota->chave)) {
            return null;
        }

        $log = ContadorCloudSyncLog::query()
            ->where('empresa_id', $nota->empresa_id)
            ->where('chave', $nota->chave)
            ->where('referencia_type', 'nota_fornecedor')
            ->orderByDesc('id')
            ->first();

        if (! $log || blank($log->payload_json)) {
            return null;
        }

        $payload = json_decode((string) $log->payload_json, true);

        if (! is_array($payload) || blank($payload['xml_base64'] ?? null)) {
            return null;
        }

        $xml = base64_decode((string) $payload['xml_base64'], true);

        return is_string($xml) && $xml !== '' ? $xml : null;
    }

    private function isProcNfeXml(string $xml): bool
    {
        return str_contains($xml, '<nfeProc') || str_contains($xml, '<infNFe');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseXml(string $xml): ?array
    {
        if (! $this->isProcNfeXml($xml) && ! str_contains($xml, '<NFe')) {
            return null;
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            return null;
        }

        $infNfe = $dom->getElementsByTagName('infNFe')->item(0);

        if (! $infNfe instanceof DOMElement) {
            return null;
        }

        $ide = $infNfe->getElementsByTagName('ide')->item(0);
        $emit = $infNfe->getElementsByTagName('emit')->item(0);
        $dest = $infNfe->getElementsByTagName('dest')->item(0);
        $totalNode = $infNfe->getElementsByTagName('total')->item(0);
        $transp = $infNfe->getElementsByTagName('transp')->item(0);
        $cobr = $infNfe->getElementsByTagName('cobr')->item(0);
        $prot = $dom->getElementsByTagName('infProt')->item(0);

        $itens = [];
        $index = 0;
        $somaBasePis = 0.0;
        $somaBaseCofins = 0.0;
        $somaBaseIpi = 0.0;

        foreach ($infNfe->getElementsByTagName('det') as $det) {
            if (! $det instanceof DOMElement) {
                continue;
            }

            $index++;
            $prod = $det->getElementsByTagName('prod')->item(0);
            $imposto = $det->getElementsByTagName('imposto')->item(0);

            if (! $prod instanceof DOMElement) {
                continue;
            }

            $impostoEl = $imposto instanceof DOMElement ? $imposto : null;
            $icmsVals = $this->extractIcms($impostoEl);
            $ipiVals = $this->extractIpi($impostoEl);
            $pisVals = $this->extractPis($impostoEl);
            $cofinsVals = $this->extractCofins($impostoEl);
            $qCom = (float) str_replace(',', '.', $this->child($prod, 'qCom') ?: '0');
            $vUnCom = (float) str_replace(',', '.', $this->child($prod, 'vUnCom') ?: '0');
            $vProd = (float) str_replace(',', '.', $this->child($prod, 'vProd') ?: '0');
            $vDesc = (float) str_replace(',', '.', $this->child($prod, 'vDesc') ?: '0');
            $ean = preg_replace('/\D/', '', $this->child($prod, 'cEAN') ?: $this->child($prod, 'cEANTrib') ?: '') ?? '';

            $somaBasePis += $pisVals['v_bc'];
            $somaBaseCofins += $cofinsVals['v_bc'];
            $somaBaseIpi += $ipiVals['v_bc'];

            $itens[] = [
                'item' => (string) $index,
                'codigo' => $this->child($prod, 'cProd') ?: '—',
                'ean' => $ean,
                'descricao' => mb_strtoupper($this->child($prod, 'xProd') ?: '—', 'UTF-8'),
                'ncm' => $this->child($prod, 'NCM'),
                'cst' => $icmsVals['cst'],
                'cfop' => $this->child($prod, 'CFOP'),
                'un' => $this->child($prod, 'uCom') ?: 'UN',
                'quant' => number_format($qCom, 4, ',', '.'),
                'valor_unit' => number_format($vUnCom, 4, ',', '.'),
                'valor_total' => number_format($vProd, 2, ',', '.'),
                'desconto' => number_format($vDesc, 2, ',', '.'),
                'base_icms' => number_format($icmsVals['v_bc'], 2, ',', '.'),
                'valor_icms' => number_format($icmsVals['v_icms'], 2, ',', '.'),
                'valor_ipi' => number_format($ipiVals['v_ipi'], 2, ',', '.'),
                'aliq_icms' => number_format($icmsVals['p_icms'], 2, ',', '.'),
                'aliq_ipi' => number_format($ipiVals['p_ipi'], 2, ',', '.'),
            ];
        }

        if ($itens === []) {
            return null;
        }

        $icmsTot = $totalNode instanceof DOMElement
            ? $totalNode->getElementsByTagName('ICMSTot')->item(0)
            : null;

        $totais = [
            'subtotal' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vProd') : '0'),
            'frete' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vFrete') : '0'),
            'despesas' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vOutro') : '0'),
            'seguro' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vSeg') : '0'),
            'desconto' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vDesc') : '0'),
            'total' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vNF') : '0'),
            'base_icms' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vBC') : '0'),
            'total_icms' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vICMS') : '0'),
            'base_pis' => number_format(
                $somaBasePis > 0
                    ? $somaBasePis
                    : (float) str_replace(',', '.', $icmsTot instanceof DOMElement ? ($this->child($icmsTot, 'vProd') ?: '0') : '0'),
                2,
                ',',
                '.',
            ),
            'total_pis' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vPIS') : '0'),
            'base_cofins' => number_format(
                $somaBaseCofins > 0
                    ? $somaBaseCofins
                    : (float) str_replace(',', '.', $icmsTot instanceof DOMElement ? ($this->child($icmsTot, 'vProd') ?: '0') : '0'),
                2,
                ',',
                '.',
            ),
            'total_cofins' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vCOFINS') : '0'),
            'base_ipi' => number_format($somaBaseIpi, 2, ',', '.'),
            'total_ipi' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vIPI') : '0'),
            'base_st' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vBCST') : '0'),
            'total_st' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vST') : '0'),
            // aliases usados no DANFE
            'valor_icms' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vICMS') : '0'),
            'base_icms_st' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vBCST') : '0'),
            'valor_icms_st' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vST') : '0'),
            'total_produtos' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vProd') : '0'),
            'outras' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vOutro') : '0'),
            'total_nota' => $this->money($icmsTot instanceof DOMElement ? $this->child($icmsTot, 'vNF') : '0'),
        ];

        $serie = $ide instanceof DOMElement ? ($this->child($ide, 'serie') ?: '1') : '1';
        $numero = $ide instanceof DOMElement ? $this->child($ide, 'nNF') : '';
        $natOp = $ide instanceof DOMElement ? $this->child($ide, 'natOp') : 'VENDA';
        $tpNF = $ide instanceof DOMElement ? $this->child($ide, 'tpNF') : '1';
        $dhEmi = $ide instanceof DOMElement ? $this->child($ide, 'dhEmi') : '';
        $dhSaiEnt = $ide instanceof DOMElement ? $this->child($ide, 'dhSaiEnt') : '';
        $nProt = $prot instanceof DOMElement ? $this->child($prot, 'nProt') : '';
        $dhRecbto = $prot instanceof DOMElement ? $this->child($prot, 'dhRecbto') : '';

        $infAdic = $infNfe->getElementsByTagName('infAdic')->item(0);
        $infCpl = $infAdic instanceof DOMElement ? $this->child($infAdic, 'infCpl') : '';
        $infAdFisco = $infAdic instanceof DOMElement ? $this->child($infAdic, 'infAdFisco') : '';

        $chave = '';
        $idAttr = $infNfe->getAttribute('Id');
        if (is_string($idAttr) && preg_match('/(\d{44})/', $idAttr, $mId)) {
            $chave = $mId[1];
        }
        if ($chave === '' && $prot instanceof DOMElement) {
            $chProt = preg_replace('/\D/', '', $this->child($prot, 'chNFe')) ?? '';
            if (strlen($chProt) === 44) {
                $chave = $chProt;
            }
        }

        $emitente = $this->buildPartyFromXml($emit instanceof DOMElement ? $emit : null, 'enderEmit');
        if ($emit instanceof DOMElement) {
            $emitente['ie_st'] = $this->child($emit, 'IEST');
        }

        return [
            'chave' => $chave,
            'emitente' => $emitente,
            'destinatario' => $this->buildPartyFromXml($dest instanceof DOMElement ? $dest : null, 'enderDest'),
            'itens' => $itens,
            'totais' => $totais,
            'transportador' => $this->extractTransportador($transp instanceof DOMElement ? $transp : null),
            'volumes' => $this->extractVolumes($transp instanceof DOMElement ? $transp : null),
            'duplicatas' => $this->extractDuplicatas($cobr instanceof DOMElement ? $cobr : null),
            'fatura' => $this->extractFatura($cobr instanceof DOMElement ? $cobr : null),
            'natureza_operacao' => mb_strtoupper($natOp !== '' ? $natOp : 'VENDA', 'UTF-8'),
            'protocolo' => trim($nProt.($dhRecbto !== '' ? ' '.$this->formatDateTime($dhRecbto) : '')),
            'serie' => str_pad(ltrim($serie, '0') ?: '0', 3, '0', STR_PAD_LEFT),
            'numero' => $numero,
            'tipo_operacao' => $tpNF === '0' ? '0' : '1',
            'tipo_operacao_label' => $tpNF === '0' ? 'ENTRADA' : 'SAÍDA',
            'data_emissao' => $this->formatDate($dhEmi),
            'data_entrada' => $this->formatDate($dhSaiEnt !== '' ? $dhSaiEnt : $dhRecbto),
            'hora_entrada' => $this->formatTime($dhSaiEnt !== '' ? $dhSaiEnt : $dhRecbto),
            'informacoes_complementares' => $infCpl !== ''
                ? $infCpl
                : 'DOCUMENTO AUXILIAR GERADO PELO UNITECH ERP WEB A PARTIR DO XML DA NF-e.',
            'informacoes_fisco' => $infAdFisco,
        ];
    }

    /**
     * @return array{cst: string, v_bc: float, p_icms: float, v_icms: float}
     */
    private function extractIcms(?DOMElement $imposto): array
    {
        $empty = ['cst' => '', 'v_bc' => 0.0, 'p_icms' => 0.0, 'v_icms' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $icms = $imposto->getElementsByTagName('ICMS')->item(0);

        if (! $icms instanceof DOMElement) {
            return $empty;
        }

        foreach ($icms->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $cst = $this->child($child, 'CST') ?: $this->child($child, 'CSOSN');
            $orig = $this->child($child, 'orig');

            return [
                'cst' => $orig !== '' ? $orig.$cst : $cst,
                'v_bc' => (float) str_replace(',', '.', $this->child($child, 'vBC') ?: '0'),
                'p_icms' => (float) str_replace(',', '.', $this->child($child, 'pICMS') ?: '0'),
                'v_icms' => (float) str_replace(',', '.', $this->child($child, 'vICMS') ?: '0'),
            ];
        }

        return $empty;
    }

    /**
     * @return array{p_ipi: float, v_ipi: float, v_bc: float}
     */
    private function extractIpi(?DOMElement $imposto): array
    {
        $empty = ['p_ipi' => 0.0, 'v_ipi' => 0.0, 'v_bc' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $ipi = $imposto->getElementsByTagName('IPI')->item(0);

        if (! $ipi instanceof DOMElement) {
            return $empty;
        }

        $ipiTrib = $ipi->getElementsByTagName('IPITrib')->item(0);

        if (! $ipiTrib instanceof DOMElement) {
            return $empty;
        }

        return [
            'p_ipi' => (float) str_replace(',', '.', $this->child($ipiTrib, 'pIPI') ?: '0'),
            'v_ipi' => (float) str_replace(',', '.', $this->child($ipiTrib, 'vIPI') ?: '0'),
            'v_bc' => (float) str_replace(',', '.', $this->child($ipiTrib, 'vBC') ?: '0'),
        ];
    }

    /**
     * @return array{v_bc: float, v_pis: float}
     */
    private function extractPis(?DOMElement $imposto): array
    {
        $empty = ['v_bc' => 0.0, 'v_pis' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $pis = $imposto->getElementsByTagName('PIS')->item(0);

        if (! $pis instanceof DOMElement) {
            return $empty;
        }

        foreach ($pis->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            return [
                'v_bc' => (float) str_replace(',', '.', $this->child($child, 'vBC') ?: '0'),
                'v_pis' => (float) str_replace(',', '.', $this->child($child, 'vPIS') ?: '0'),
            ];
        }

        return $empty;
    }

    /**
     * @return array{v_bc: float, v_cofins: float}
     */
    private function extractCofins(?DOMElement $imposto): array
    {
        $empty = ['v_bc' => 0.0, 'v_cofins' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $cofins = $imposto->getElementsByTagName('COFINS')->item(0);

        if (! $cofins instanceof DOMElement) {
            return $empty;
        }

        foreach ($cofins->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            return [
                'v_bc' => (float) str_replace(',', '.', $this->child($child, 'vBC') ?: '0'),
                'v_cofins' => (float) str_replace(',', '.', $this->child($child, 'vCOFINS') ?: '0'),
            ];
        }

        return $empty;
    }

    /**
     * @return array<string, string>
     */
    private function buildPartyFromXml(?DOMElement $party, string $enderTag): array
    {
        if (! $party) {
            return [
                'nome' => '',
                'fantasia' => '',
                'endereco' => '',
                'logradouro' => '',
                'numero' => '',
                'complemento' => '',
                'bairro' => '',
                'cep' => '',
                'municipio' => '',
                'municipio_codigo' => '',
                'uf' => '',
                'telefone' => '',
                'ie' => '',
                'im' => '',
                'cnpj' => '',
            ];
        }

        $ender = $party->getElementsByTagName($enderTag)->item(0);
        $cnpj = $this->child($party, 'CNPJ') ?: $this->child($party, 'CPF');

        $logradouro = $ender instanceof DOMElement ? mb_strtoupper($this->child($ender, 'xLgr'), 'UTF-8') : '';
        $numero = $ender instanceof DOMElement ? $this->child($ender, 'nro') : '';
        $complemento = $ender instanceof DOMElement ? mb_strtoupper($this->child($ender, 'xCpl'), 'UTF-8') : '';
        $bairro = $ender instanceof DOMElement ? mb_strtoupper($this->child($ender, 'xBairro'), 'UTF-8') : '';
        $cep = $ender instanceof DOMElement ? $this->child($ender, 'CEP') : '';
        $municipio = $ender instanceof DOMElement ? mb_strtoupper($this->child($ender, 'xMun'), 'UTF-8') : '';
        $municipioCodigo = $ender instanceof DOMElement ? $this->child($ender, 'cMun') : '';
        $uf = $ender instanceof DOMElement ? $this->child($ender, 'UF') : '';
        $telefone = $ender instanceof DOMElement ? $this->child($ender, 'fone') : '';

        $endereco = implode(', ', array_filter([
            $logradouro !== '' ? $logradouro : null,
            filled($numero) ? $numero : null,
            $complemento !== '' ? $complemento : null,
        ]));

        return [
            'nome' => mb_strtoupper($this->child($party, 'xNome'), 'UTF-8'),
            'fantasia' => mb_strtoupper($this->child($party, 'xFant'), 'UTF-8'),
            'endereco' => $endereco,
            'logradouro' => $logradouro,
            'numero' => $numero,
            'complemento' => $complemento,
            'bairro' => $bairro,
            'cep' => $cep !== '' ? $this->formatCep($cep) : '',
            'municipio' => $municipio,
            'municipio_codigo' => $municipioCodigo,
            'uf' => $uf,
            'telefone' => $telefone,
            'ie' => $this->child($party, 'IE'),
            'ie_st' => $this->child($party, 'IEST'),
            'im' => $this->child($party, 'IM'),
            'cnpj' => $this->danfe->formatCpfCnpj($cnpj),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractTransportador(?DOMElement $transp): array
    {
        $empty = $this->emptyTransportador();

        if (! $transp) {
            return $empty;
        }

        $modFrete = $this->child($transp, 'modFrete');
        $transporta = $transp->getElementsByTagName('transporta')->item(0);
        $veiculo = $transp->getElementsByTagName('veicTransp')->item(0);

        $nome = '';
        $cnpj = '';
        $ie = '';
        $endereco = '';
        $municipio = '';
        $uf = '';

        if ($transporta instanceof DOMElement) {
            $nome = mb_strtoupper($this->child($transporta, 'xNome'), 'UTF-8');
            $cnpj = $this->danfe->formatCpfCnpj(
                $this->child($transporta, 'CNPJ') ?: $this->child($transporta, 'CPF')
            );
            $ie = $this->child($transporta, 'IE');
            $endereco = mb_strtoupper($this->child($transporta, 'xEnder'), 'UTF-8');
            $municipio = mb_strtoupper($this->child($transporta, 'xMun'), 'UTF-8');
            $uf = $this->child($transporta, 'UF');
        }

        $placa = '';
        $placaUf = '';
        $antt = '';

        if ($veiculo instanceof DOMElement) {
            $placa = mb_strtoupper($this->child($veiculo, 'placa'), 'UTF-8');
            $placaUf = $this->child($veiculo, 'UF');
            $antt = $this->child($veiculo, 'RNTC');
        }

        return [
            'nome' => $nome,
            'cnpj' => $cnpj,
            'ie' => $ie,
            'endereco' => $endereco,
            'municipio' => $municipio,
            'uf' => $uf,
            'placa' => $placa,
            'placa_uf' => $placaUf,
            'antt' => $antt,
            'mod_frete' => $modFrete,
            'mod_frete_label' => $this->modFreteLabel($modFrete),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractVolumes(?DOMElement $transp): array
    {
        $empty = $this->emptyVolumes();

        if (! $transp) {
            return $empty;
        }

        $qtde = 0.0;
        $especie = [];
        $marca = [];
        $numeracao = [];
        $pesoBruto = 0.0;
        $pesoLiquido = 0.0;

        foreach ($transp->getElementsByTagName('vol') as $vol) {
            if (! $vol instanceof DOMElement) {
                continue;
            }

            $qtde += (float) str_replace(',', '.', $this->child($vol, 'qVol') ?: '0');
            $esp = trim($this->child($vol, 'esp'));
            if ($esp !== '') {
                $especie[] = mb_strtoupper($esp, 'UTF-8');
            }
            $mar = trim($this->child($vol, 'marca'));
            if ($mar !== '') {
                $marca[] = mb_strtoupper($mar, 'UTF-8');
            }
            $nVol = trim($this->child($vol, 'nVol'));
            if ($nVol !== '') {
                $numeracao[] = $nVol;
            }
            $pesoBruto += (float) str_replace(',', '.', $this->child($vol, 'pesoB') ?: '0');
            $pesoLiquido += (float) str_replace(',', '.', $this->child($vol, 'pesoL') ?: '0');
        }

        return [
            'quantidade' => $qtde > 0 ? rtrim(rtrim(number_format($qtde, 3, ',', '.'), '0'), ',') : '',
            'especie' => implode(' / ', array_unique($especie)),
            'marca' => implode(' / ', array_unique($marca)),
            'numeracao' => implode(' / ', array_unique($numeracao)),
            'peso_bruto' => $pesoBruto > 0 ? number_format($pesoBruto, 3, ',', '.') : '',
            'peso_liquido' => $pesoLiquido > 0 ? number_format($pesoLiquido, 3, ',', '.') : '',
        ];
    }

    /**
     * @return list<array{numero: string, vencimento: string, valor: string}>
     */
    private function extractDuplicatas(?DOMElement $cobr): array
    {
        if (! $cobr) {
            return [];
        }

        $out = [];

        foreach ($cobr->getElementsByTagName('dup') as $dup) {
            if (! $dup instanceof DOMElement) {
                continue;
            }

            $out[] = [
                'numero' => $this->child($dup, 'nDup'),
                'vencimento' => $this->formatDate($this->child($dup, 'dVenc')),
                'valor' => $this->money($this->child($dup, 'vDup') ?: '0'),
            ];
        }

        return $out;
    }

    /**
     * @return array{numero: string, valor_original: string, valor_desconto: string, valor_liquido: string}
     */
    private function extractFatura(?DOMElement $cobr): array
    {
        $empty = $this->emptyFatura();

        if (! $cobr) {
            return $empty;
        }

        $fat = $cobr->getElementsByTagName('fat')->item(0);

        if (! $fat instanceof DOMElement) {
            return $empty;
        }

        return [
            'numero' => $this->child($fat, 'nFat'),
            'valor_original' => $this->money($this->child($fat, 'vOrig') ?: '0'),
            'valor_desconto' => $this->money($this->child($fat, 'vDesc') ?: '0'),
            'valor_liquido' => $this->money($this->child($fat, 'vLiq') ?: '0'),
        ];
    }

    private function modFreteLabel(string $modFrete): string
    {
        return match ($modFrete) {
            '0' => '0 - Por conta do Remetente',
            '1' => '1 - Por conta do Destinatário',
            '2' => '2 - Por conta de Terceiros',
            '3' => '3 - Próprio Remetente',
            '4' => '4 - Próprio Destinatário',
            '9' => '9 - Sem Frete',
            default => $modFrete !== '' ? $modFrete : '',
        };
    }

    /**
     * @return array<string, string>
     */
    private function emptyTransportador(): array
    {
        return [
            'nome' => '',
            'cnpj' => '',
            'ie' => '',
            'endereco' => '',
            'municipio' => '',
            'uf' => '',
            'placa' => '',
            'placa_uf' => '',
            'antt' => '',
            'mod_frete' => '',
            'mod_frete_label' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyVolumes(): array
    {
        return [
            'quantidade' => '',
            'especie' => '',
            'marca' => '',
            'numeracao' => '',
            'peso_bruto' => '',
            'peso_liquido' => '',
        ];
    }

    /**
     * @return array{numero: string, valor_original: string, valor_desconto: string, valor_liquido: string}
     */
    private function emptyFatura(): array
    {
        return [
            'numero' => '',
            'valor_original' => '',
            'valor_desconto' => '',
            'valor_liquido' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildEmitenteFromNota(NotaFornecedor $nota): array
    {
        return [
            'nome' => mb_strtoupper((string) $nota->nome, 'UTF-8'),
            'fantasia' => '',
            'endereco' => '',
            'logradouro' => '',
            'numero' => '',
            'complemento' => '',
            'bairro' => '',
            'cep' => '',
            'municipio' => '',
            'municipio_codigo' => '',
            'uf' => '',
            'telefone' => '',
            'ie' => '',
            'ie_st' => '',
            'im' => '',
            'cnpj' => $this->danfe->formatCpfCnpj($nota->cnpj),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildDestinatarioFromEmpresa(?Empresa $empresa): array
    {
        if (! $empresa) {
            return [
                'nome' => '',
                'endereco' => '',
                'logradouro' => '',
                'numero' => '',
                'complemento' => '',
                'bairro' => '',
                'cep' => '',
                'municipio' => '',
                'municipio_codigo' => '',
                'uf' => '',
                'telefone' => '',
                'ie' => '',
                'ie_st' => '',
                'im' => '',
                'cnpj' => '',
            ];
        }

        $logradouro = filled($empresa->endereco) ? mb_strtoupper(trim((string) $empresa->endereco), 'UTF-8') : '';
        $numero = filled($empresa->numero) ? trim((string) $empresa->numero) : '';
        $bairro = filled($empresa->bairro) ? mb_strtoupper(trim((string) $empresa->bairro), 'UTF-8') : '';
        $cep = filled($empresa->cep) ? $this->formatCep((string) $empresa->cep) : '';

        return [
            'nome' => mb_strtoupper((string) ($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia), 'UTF-8'),
            'endereco' => implode(', ', array_filter([
                $logradouro !== '' ? $logradouro : null,
                $numero !== '' ? $numero : null,
            ])),
            'logradouro' => $logradouro,
            'numero' => $numero,
            'complemento' => '',
            'bairro' => $bairro,
            'cep' => $cep,
            'municipio' => mb_strtoupper((string) ($empresa->cidade ?? ''), 'UTF-8'),
            'municipio_codigo' => '',
            'uf' => (string) ($empresa->uf ?? ''),
            'telefone' => (string) ($empresa->telefone ?? ''),
            'ie' => (string) ($empresa->ie ?? ''),
            'ie_st' => '',
            'im' => (string) ($empresa->im ?? ''),
            'cnpj' => $this->danfe->formatCpfCnpj($empresa->cnpj),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyTotais(float $totalNota): array
    {
        $zero = '0,00';

        return [
            'subtotal' => number_format($totalNota, 2, ',', '.'),
            'frete' => $zero,
            'despesas' => $zero,
            'seguro' => $zero,
            'desconto' => $zero,
            'total' => number_format($totalNota, 2, ',', '.'),
            'base_icms' => $zero,
            'total_icms' => $zero,
            'base_pis' => $zero,
            'total_pis' => $zero,
            'base_cofins' => $zero,
            'total_cofins' => $zero,
            'base_ipi' => $zero,
            'total_ipi' => $zero,
            'base_st' => $zero,
            'total_st' => $zero,
            'valor_icms' => $zero,
            'base_icms_st' => $zero,
            'valor_icms_st' => $zero,
            'total_produtos' => number_format($totalNota, 2, ',', '.'),
            'outras' => $zero,
            'total_nota' => number_format($totalNota, 2, ',', '.'),
        ];
    }

    private function child(DOMElement $parent, string $tag): string
    {
        $node = $parent->getElementsByTagName($tag)->item(0);

        return $node ? trim((string) $node->textContent) : '';
    }

    private function money(string $value): string
    {
        return number_format((float) str_replace(',', '.', $value ?: '0'), 2, ',', '.');
    }

    private function formatCep(string $cep): string
    {
        $digits = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digits) !== 8) {
            return $cep;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }

    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    private function formatTime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value))->format('H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }

    private function formatDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            return (new \DateTimeImmutable($value))->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return '';
        }
    }
}
