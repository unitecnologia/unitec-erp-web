<?php

namespace Unitec\FiscalEngine\Xml;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\FaturaParcelaDto;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\NfeDestinatarioDto;
use Unitec\FiscalEngine\Dto\NfeTransporteDto;
use Unitec\FiscalEngine\Dto\PagamentoDto;
use Unitec\FiscalEngine\Dto\RespTecnicoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\ChaveAcesso;
use Unitec\FiscalEngine\Util\NumberFormatter;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfeXmlBuilder
{
    private const HOMOLOGACAO_DEST = 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';

    private const HOMOLOGACAO_ITEM = 'NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';

    /**
     * @return array{dom: DOMDocument, chave: string, dhEmiIso: string, valorIcms: float}
     */
    public function build(EmitirNfeRequest $request): array
    {
        if ($request->itens === []) {
            throw new FiscalEngineException('NF-e sem itens.');
        }

        $emitente = $request->emitente;
        $ide = $request->ide;
        $chave = ChaveAcesso::gerar(
            uf: $emitente->uf,
            emissao: $ide->dataEmissao,
            cnpj: $emitente->cnpj,
            modelo: '55',
            serie: $ide->serie,
            numero: $ide->numero,
            tpEmis: $ide->tpEmis,
            cNf: $ide->cNf,
        );

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $nfe = $dom->createElementNS(XmlHelper::NFE_NS, 'NFe');
        $dom->appendChild($nfe);

        $infNFe = $dom->createElementNS(XmlHelper::NFE_NS, 'infNFe');
        $infNFe->setAttribute('Id', 'NFe' . $chave);
        $infNFe->setAttribute('versao', '4.00');
        $nfe->appendChild($infNFe);

        $dhEmi = $ide->dataEmissao->format('Y-m-d\TH:i:sP');
        $dhEmiIso = $ide->dataEmissao->format('Y-m-d\TH:i:sP');

        $this->appendIde($infNFe, $ide, $chave, $dhEmi, $request);
        $this->appendEmitente($infNFe, $emitente);
        $this->appendDestinatario($infNFe, $request->destinatario, $request->homologacao);

        $totaisImposto = [
            'vBc' => 0.0,
            'vIcms' => 0.0,
            'vIcmsDeson' => 0.0,
            'vIpi' => 0.0,
            'vPis' => 0.0,
            'vCofins' => 0.0,
        ];

        foreach ($request->itens as $item) {
            $totaisImposto = NfeImpostoXmlBuilder::somarTotais($item->imposto, $totaisImposto);
            $this->appendItem($infNFe, $item, $request->homologacao && $item->numero === 1);
        }

        $this->appendTotais($infNFe, $request, $totaisImposto);
        $this->appendTransporte($infNFe, $request);
        $this->appendCobranca($infNFe, $request);
        $this->appendPagamentos($infNFe, $request->pagamentos);
        $this->appendInformacoes($infNFe, $request->informacoesComplementares, $request->informacoesFisco);
        $this->appendRespTecnico($infNFe, $request->respTecnico, $chave);

        return [
            'dom' => $dom,
            'chave' => $chave,
            'dhEmiIso' => $dhEmiIso,
            'valorIcms' => $totaisImposto['vIcms'],
        ];
    }

    public function wrapEnviNfe(string $nfeXml, int $loteId = 1): string
    {
        $nfeXml = XmlHelper::stripXmlDeclaration(trim($nfeXml));

        return XmlHelper::compact(
            '<enviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . '<idLote>' . str_pad((string) $loteId, 15, '0', STR_PAD_LEFT) . '</idLote>'
            . '<indSinc>1</indSinc>'
            . $nfeXml
            . '</enviNFe>',
        );
    }

    public function finalizeNfeXml(DOMDocument $dom): string
    {
        $xml = $dom->saveXML($dom->documentElement) ?: '';

        return XmlHelper::compact($xml);
    }

    private function appendIde(DOMElement $infNFe, $ide, string $chave, string $dhEmi, EmitirNfeRequest $request): void
    {
        $ideEl = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ide');
        $infNFe->appendChild($ideEl);

        $cUf = substr($chave, 0, 2);
        XmlHelper::append($ideEl, 'cUF', $cUf);
        XmlHelper::append($ideEl, 'cNF', substr($chave, 35, 8));
        XmlHelper::append($ideEl, 'natOp', XmlHelper::sanitizeText($ide->natOp, 60));
        XmlHelper::append($ideEl, 'mod', '55');
        XmlHelper::append($ideEl, 'serie', (string) $ide->serie);
        XmlHelper::append($ideEl, 'nNF', (string) $ide->numero);
        XmlHelper::append($ideEl, 'dhEmi', $dhEmi);

        if ($request->dataSaida instanceof \DateTimeInterface) {
            XmlHelper::append($ideEl, 'dhSaiEnt', $request->dataSaida->format('Y-m-d\TH:i:sP'));
        }

        XmlHelper::append($ideEl, 'tpNF', '1');
        XmlHelper::append($ideEl, 'idDest', (string) $request->idDest);
        XmlHelper::append($ideEl, 'cMunFG', $ide->codigoMunicipioFg);
        XmlHelper::append($ideEl, 'tpImp', '1');
        XmlHelper::append($ideEl, 'tpEmis', (string) $ide->tpEmis);
        XmlHelper::append($ideEl, 'cDV', substr($chave, -1));
        XmlHelper::append($ideEl, 'tpAmb', (string) $ide->tpAmb);
        XmlHelper::append($ideEl, 'finNFe', (string) $request->finNFe);
        XmlHelper::append($ideEl, 'indFinal', (string) $request->indFinal);
        XmlHelper::append($ideEl, 'indPres', '0');
        XmlHelper::append($ideEl, 'procEmi', '0');
        XmlHelper::append($ideEl, 'verProc', 'UnitecERP-1.0');

        $this->appendReferencias($ideEl, $request->chavesReferenciadas);

        if ($ide->tpEmis !== 1) {
            $justificativa = XmlHelper::sanitizeText($ide->justificativaContingencia ?? '', 256);

            if (mb_strlen($justificativa, 'UTF-8') < 15) {
                throw new FiscalEngineException('Justificativa de contingência deve ter no mínimo 15 caracteres.');
            }

            $dataContingencia = $ide->dataContingencia ?? $ide->dataEmissao;
            XmlHelper::append($ideEl, 'dhCont', $dataContingencia->format('Y-m-d\TH:i:sP'));
            XmlHelper::append($ideEl, 'xJust', $justificativa);
        }
    }

    /**
     * @param  list<string>  $chavesReferenciadas
     */
    private function appendReferencias(DOMElement $ideEl, array $chavesReferenciadas): void
    {
        foreach ($chavesReferenciadas as $chave) {
            $digits = NumberFormatter::onlyDigits((string) $chave);

            if (strlen($digits) !== 44) {
                continue;
            }

            $nfRef = $ideEl->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'NFref');
            $ideEl->appendChild($nfRef);
            XmlHelper::append($nfRef, 'refNFe', $digits);
        }
    }

    private function appendEmitente(DOMElement $infNFe, $emitente): void
    {
        $emit = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'emit');
        $infNFe->appendChild($emit);

        XmlHelper::append($emit, 'CNPJ', NumberFormatter::onlyDigits($emitente->cnpj));
        XmlHelper::append($emit, 'xNome', XmlHelper::sanitizeText($emitente->razaoSocial, 60));
        XmlHelper::append($emit, 'xFant', XmlHelper::sanitizeText($emitente->nomeFantasia, 60));

        $ender = $emit->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'enderEmit');
        $emit->appendChild($ender);
        XmlHelper::append($ender, 'xLgr', XmlHelper::sanitizeText($emitente->logradouro, 60));
        XmlHelper::append($ender, 'nro', XmlHelper::sanitizeText($emitente->numero, 60));
        XmlHelper::append($ender, 'xBairro', XmlHelper::sanitizeText($emitente->bairro, 60));
        XmlHelper::append($ender, 'cMun', $emitente->codigoMunicipio);
        XmlHelper::append($ender, 'xMun', XmlHelper::sanitizeText($emitente->municipio, 60));
        XmlHelper::append($ender, 'UF', strtoupper($emitente->uf));
        XmlHelper::append($ender, 'CEP', str_pad(NumberFormatter::onlyDigits($emitente->cep), 8, '0', STR_PAD_LEFT));
        XmlHelper::append($ender, 'cPais', '1058');
        XmlHelper::append($ender, 'xPais', 'BRASIL');

        if ($emitente->telefone !== '') {
            XmlHelper::append($ender, 'fone', NumberFormatter::onlyDigits($emitente->telefone));
        }

        XmlHelper::append($emit, 'IE', NumberFormatter::onlyDigits($emitente->ie));
        XmlHelper::append($emit, 'CRT', (string) $emitente->crt);
    }

    private function appendDestinatario(DOMElement $infNFe, NfeDestinatarioDto $destinatario, bool $homologacao): void
    {
        $cpf = NumberFormatter::onlyDigits($destinatario->cpf ?? '');
        $cnpj = NumberFormatter::onlyDigits($destinatario->cnpj ?? '');

        if ($cpf === '' && $cnpj === '') {
            throw new FiscalEngineException('Destinatário da NF-e deve ter CPF ou CNPJ.');
        }

        $dest = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'dest');
        $infNFe->appendChild($dest);

        if (strlen($cpf) === 11) {
            XmlHelper::append($dest, 'CPF', $cpf);
        } else {
            XmlHelper::append($dest, 'CNPJ', $cnpj);
        }

        $nome = $homologacao ? self::HOMOLOGACAO_DEST : ($destinatario->nome ?: 'DESTINATARIO');
        XmlHelper::append($dest, 'xNome', XmlHelper::sanitizeText($nome, 60));

        $ender = $dest->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'enderDest');
        $dest->appendChild($ender);
        XmlHelper::append($ender, 'xLgr', XmlHelper::sanitizeText($destinatario->logradouro, 60));
        XmlHelper::append($ender, 'nro', XmlHelper::sanitizeText($destinatario->numero, 60));
        XmlHelper::append($ender, 'xBairro', XmlHelper::sanitizeText($destinatario->bairro, 60));
        XmlHelper::append($ender, 'cMun', $destinatario->codigoMunicipio);
        XmlHelper::append($ender, 'xMun', XmlHelper::sanitizeText($destinatario->municipio, 60));
        XmlHelper::append($ender, 'UF', strtoupper($destinatario->uf));
        XmlHelper::append($ender, 'CEP', str_pad(NumberFormatter::onlyDigits($destinatario->cep), 8, '0', STR_PAD_LEFT));
        XmlHelper::append($ender, 'cPais', '1058');
        XmlHelper::append($ender, 'xPais', 'BRASIL');

        if ($destinatario->telefone !== '') {
            XmlHelper::append($ender, 'fone', NumberFormatter::onlyDigits($destinatario->telefone));
        }

        XmlHelper::append($dest, 'indIEDest', (string) $destinatario->indIeDest);

        if ($destinatario->indIeDest === 1 && filled($destinatario->ie)) {
            XmlHelper::append($dest, 'IE', NumberFormatter::onlyDigits($destinatario->ie));
        }

        if ($destinatario->email !== '') {
            XmlHelper::append($dest, 'email', mb_substr(trim($destinatario->email), 0, 60, 'UTF-8'));
        }
    }

    private function appendItem(DOMElement $infNFe, ItemDto $item, bool $forcarHomologacao): void
    {
        $det = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'det');
        $det->setAttribute('nItem', (string) $item->numero);
        $infNFe->appendChild($det);

        $prod = $det->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'prod');
        $det->appendChild($prod);

        $descricao = $forcarHomologacao ? self::HOMOLOGACAO_ITEM : $item->descricao;
        XmlHelper::append($prod, 'cProd', XmlHelper::sanitizeText($item->codigo, 60));
        XmlHelper::append($prod, 'cEAN', 'SEM GTIN');
        XmlHelper::append($prod, 'xProd', XmlHelper::sanitizeText($descricao, 120));
        XmlHelper::append($prod, 'NCM', str_pad(NumberFormatter::onlyDigits($item->ncm), 8, '0', STR_PAD_LEFT));
        XmlHelper::append($prod, 'CFOP', NumberFormatter::onlyDigits($item->cfop));
        XmlHelper::append($prod, 'uCom', XmlHelper::sanitizeText($item->unidade, 6));
        XmlHelper::append($prod, 'qCom', NumberFormatter::decimal($item->quantidade, 4));
        XmlHelper::append($prod, 'vUnCom', NumberFormatter::decimal($item->valorUnitario, 4));
        XmlHelper::append($prod, 'vProd', NumberFormatter::decimal($item->valorTotal));
        XmlHelper::append($prod, 'cEANTrib', 'SEM GTIN');
        XmlHelper::append($prod, 'uTrib', XmlHelper::sanitizeText($item->unidade, 6));
        XmlHelper::append($prod, 'qTrib', NumberFormatter::decimal($item->quantidade, 4));
        XmlHelper::append($prod, 'vUnTrib', NumberFormatter::decimal($item->valorUnitario, 4));

        if ($item->frete > 0) {
            XmlHelper::append($prod, 'vFrete', NumberFormatter::decimal($item->frete));
        }

        if ($item->seguro > 0) {
            XmlHelper::append($prod, 'vSeg', NumberFormatter::decimal($item->seguro));
        }

        if ($item->desconto > 0) {
            XmlHelper::append($prod, 'vDesc', NumberFormatter::decimal($item->desconto));
        }

        if ($item->acrescimo > 0) {
            XmlHelper::append($prod, 'vOutro', NumberFormatter::decimal($item->acrescimo));
        }

        XmlHelper::append($prod, 'indTot', '1');

        $infoAdicionais = XmlHelper::sanitizeInfAdProd((string) ($item->infoAdicionais ?? ''));
        if ($infoAdicionais !== '') {
            XmlHelper::append($prod, 'infAdProd', $infoAdicionais);
        }

        $imposto = $det->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'imposto');
        $det->appendChild($imposto);

        if ($item->imposto->vTotTrib > 0) {
            XmlHelper::append($imposto, 'vTotTrib', NumberFormatter::decimal($item->imposto->vTotTrib));
        }

        NfeImpostoXmlBuilder::appendIcms($imposto, $item->imposto);
        NfeImpostoXmlBuilder::appendIpi($imposto, $item->imposto);
        NfeImpostoXmlBuilder::appendPis($imposto, $item->imposto);
        NfeImpostoXmlBuilder::appendCofins($imposto, $item->imposto);

        IbscbsXmlBuilder::appendItem($imposto, $item->imposto);
    }

    /**
     * @param  array{vBc: float, vIcms: float, vIcmsDeson: float, vIpi: float, vPis: float, vCofins: float}  $totaisImposto
     */
    private function appendTotais(DOMElement $infNFe, EmitirNfeRequest $request, array $totaisImposto): void
    {
        $total = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'total');
        $infNFe->appendChild($total);

        $icmsTot = $total->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ICMSTot');
        $total->appendChild($icmsTot);

        XmlHelper::append($icmsTot, 'vBC', NumberFormatter::decimal($totaisImposto['vBc']));
        XmlHelper::append($icmsTot, 'vICMS', NumberFormatter::decimal($totaisImposto['vIcms']));
        XmlHelper::append($icmsTot, 'vICMSDeson', NumberFormatter::decimal($totaisImposto['vIcmsDeson']));
        XmlHelper::append($icmsTot, 'vFCP', '0.00');
        XmlHelper::append($icmsTot, 'vBCST', '0.00');
        XmlHelper::append($icmsTot, 'vST', '0.00');
        XmlHelper::append($icmsTot, 'vFCPST', '0.00');
        XmlHelper::append($icmsTot, 'vFCPSTRet', '0.00');
        XmlHelper::append($icmsTot, 'vProd', NumberFormatter::decimal($request->valorProdutos));
        XmlHelper::append($icmsTot, 'vFrete', NumberFormatter::decimal($request->valorFrete));
        XmlHelper::append($icmsTot, 'vSeg', NumberFormatter::decimal($request->valorSeguro));
        XmlHelper::append($icmsTot, 'vDesc', NumberFormatter::decimal($request->valorDesconto));
        XmlHelper::append($icmsTot, 'vII', '0.00');
        XmlHelper::append($icmsTot, 'vIPI', NumberFormatter::decimal($totaisImposto['vIpi']));
        XmlHelper::append($icmsTot, 'vIPIDevol', '0.00');
        XmlHelper::append($icmsTot, 'vPIS', NumberFormatter::decimal($totaisImposto['vPis']));
        XmlHelper::append($icmsTot, 'vCOFINS', NumberFormatter::decimal($totaisImposto['vCofins']));
        XmlHelper::append($icmsTot, 'vOutro', NumberFormatter::decimal($request->valorOutros));
        XmlHelper::append($icmsTot, 'vNF', NumberFormatter::decimal($request->valorNota));
        XmlHelper::append($icmsTot, 'vTotTrib', NumberFormatter::decimal($request->valorTotTrib));

        IbscbsXmlBuilder::appendTotais($total, $request->itens);
    }

    private function appendTransporte(DOMElement $infNFe, EmitirNfeRequest $request): void
    {
        $transp = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'transp');
        $infNFe->appendChild($transp);
        XmlHelper::append($transp, 'modFrete', (string) $request->modFrete);

        $detalhe = $request->transporte;
        if (! $detalhe instanceof NfeTransporteDto) {
            return;
        }

        $semFrete = $request->modFrete === 9;

        if (! $semFrete && $detalhe->hasTransportadora()) {
            $transporta = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'transporta');
            $transp->appendChild($transporta);

            $doc = NumberFormatter::onlyDigits((string) ($detalhe->transportadoraDocumento ?? ''));
            if (strlen($doc) === 14) {
                XmlHelper::append($transporta, 'CNPJ', $doc);
            } elseif (strlen($doc) === 11) {
                XmlHelper::append($transporta, 'CPF', $doc);
            }

            if ($this->filledText($detalhe->transportadoraNome)) {
                XmlHelper::append(
                    $transporta,
                    'xNome',
                    XmlHelper::sanitizeText((string) $detalhe->transportadoraNome, 60),
                );
            }

            $ieRaw = trim((string) ($detalhe->transportadoraIe ?? ''));
            $ie = NumberFormatter::onlyDigits($ieRaw);
            if (strtoupper($ieRaw) === 'ISENTO') {
                XmlHelper::append($transporta, 'IE', 'ISENTO');
            } elseif ($ie !== '') {
                XmlHelper::append($transporta, 'IE', $ie);
            }

            if ($this->filledText($detalhe->transportadoraEndereco)) {
                XmlHelper::append(
                    $transporta,
                    'xEnder',
                    XmlHelper::sanitizeText((string) $detalhe->transportadoraEndereco, 60),
                );
            }

            if ($this->filledText($detalhe->transportadoraMunicipio)) {
                XmlHelper::append(
                    $transporta,
                    'xMun',
                    XmlHelper::sanitizeText((string) $detalhe->transportadoraMunicipio, 60),
                );
            }

            if ($this->filledText($detalhe->transportadoraUf)) {
                XmlHelper::append(
                    $transporta,
                    'UF',
                    strtoupper(substr((string) $detalhe->transportadoraUf, 0, 2)),
                );
            }
        }

        if (! $semFrete && $detalhe->hasVeiculo()) {
            $veiculo = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'veicTransp');
            $transp->appendChild($veiculo);
            XmlHelper::append(
                $veiculo,
                'placa',
                strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $detalhe->placa) ?? ''),
            );
            XmlHelper::append(
                $veiculo,
                'UF',
                strtoupper(substr((string) $detalhe->ufPlaca, 0, 2)),
            );
        }

        if ($detalhe->hasVolume()) {
            $vol = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'vol');
            $transp->appendChild($vol);

            if ($detalhe->qVol !== null && $detalhe->qVol > 0) {
                XmlHelper::append($vol, 'qVol', (string) $detalhe->qVol);
            }

            if ($this->filledText($detalhe->esp)) {
                XmlHelper::append($vol, 'esp', XmlHelper::sanitizeText((string) $detalhe->esp, 60));
            }

            if ($this->filledText($detalhe->marca)) {
                XmlHelper::append($vol, 'marca', XmlHelper::sanitizeText((string) $detalhe->marca, 60));
            }

            if ($this->filledText($detalhe->nVol)) {
                XmlHelper::append($vol, 'nVol', XmlHelper::sanitizeText((string) $detalhe->nVol, 60));
            }

            if ($detalhe->pesoL !== null && $detalhe->pesoL > 0) {
                XmlHelper::append($vol, 'pesoL', NumberFormatter::decimal($detalhe->pesoL, 3));
            }

            if ($detalhe->pesoB !== null && $detalhe->pesoB > 0) {
                XmlHelper::append($vol, 'pesoB', NumberFormatter::decimal($detalhe->pesoB, 3));
            }
        }
    }

    private function filledText(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    private function appendCobranca(DOMElement $infNFe, EmitirNfeRequest $request): void
    {
        if ($request->parcelas === []) {
            return;
        }

        $cob = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'cobr');
        $infNFe->appendChild($cob);

        $valorOriginal = array_reduce(
            $request->parcelas,
            fn (float $carry, FaturaParcelaDto $parcela): float => $carry + $parcela->valor,
            0.0,
        );

        $fat = $cob->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'fat');
        $cob->appendChild($fat);
        XmlHelper::append($fat, 'nFat', (string) $request->ide->numero);
        XmlHelper::append($fat, 'vOrig', NumberFormatter::decimal($valorOriginal));
        XmlHelper::append($fat, 'vLiq', NumberFormatter::decimal($request->valorNota));

        foreach ($request->parcelas as $parcela) {
            $dup = $cob->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'dup');
            $cob->appendChild($dup);
            XmlHelper::append($dup, 'nDup', XmlHelper::sanitizeText($parcela->numero, 60));
            XmlHelper::append($dup, 'dVenc', $parcela->vencimento->format('Y-m-d'));
            XmlHelper::append($dup, 'vDup', NumberFormatter::decimal($parcela->valor));
        }
    }

    /**
     * @param  list<PagamentoDto>  $pagamentos
     */
    private function appendPagamentos(DOMElement $infNFe, array $pagamentos): void
    {
        if ($pagamentos === []) {
            return;
        }

        $pag = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'pag');
        $infNFe->appendChild($pag);

        foreach ($pagamentos as $pagamento) {
            if ($pagamento->valor <= 0) {
                continue;
            }

            $detPag = $pag->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'detPag');
            $pag->appendChild($detPag);
            $tPag = str_pad($pagamento->tipo, 2, '0', STR_PAD_LEFT);
            XmlHelper::append($detPag, 'tPag', $tPag);

            if ($tPag === '99' || filled($pagamento->descricao)) {
                $xPag = trim((string) ($pagamento->descricao ?: 'Outros'));
                XmlHelper::append($detPag, 'xPag', XmlHelper::sanitizeText($xPag, 60));
            }

            XmlHelper::append($detPag, 'vPag', NumberFormatter::decimal($pagamento->valor));

            if ($pagamento->isCartao()) {
                $card = $detPag->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'card');
                $detPag->appendChild($card);

                XmlHelper::append($card, 'tpIntegra', $pagamento->tpIntegra ?: '2');

                if (filled($pagamento->cnpjCredenciadora)) {
                    XmlHelper::append($card, 'CNPJ', NumberFormatter::onlyDigits($pagamento->cnpjCredenciadora));
                }

                if (filled($pagamento->tBand)) {
                    XmlHelper::append($card, 'tBand', str_pad($pagamento->tBand, 2, '0', STR_PAD_LEFT));
                }

                if (filled($pagamento->cAut)) {
                    XmlHelper::append($card, 'cAut', XmlHelper::sanitizeText($pagamento->cAut, 128));
                }
            }
        }
    }

    private function appendInformacoes(DOMElement $infNFe, string $informacoes, string $informacoesFisco = ''): void
    {
        $texto = trim($informacoes);
        $fisco = trim($informacoesFisco);

        if ($texto === '' && $fisco === '') {
            return;
        }

        $infAdic = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'infAdic');
        $infNFe->appendChild($infAdic);

        if ($fisco !== '') {
            XmlHelper::append($infAdic, 'infAdFisco', XmlHelper::sanitizeText($fisco, 2000));
        }

        if ($texto !== '') {
            XmlHelper::append($infAdic, 'infCpl', XmlHelper::sanitizeText($texto, 5000));
        }
    }

    private function appendRespTecnico(DOMElement $infNFe, ?RespTecnicoDto $respTecnico, string $chave): void
    {
        if ($respTecnico === null || ! $respTecnico->isComplete()) {
            return;
        }

        $fone = $this->normalizeRespTecnicoFone($respTecnico->fone);

        if (strlen($fone) < 6) {
            return;
        }

        $infRespTec = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'infRespTec');
        $infNFe->appendChild($infRespTec);

        XmlHelper::append($infRespTec, 'CNPJ', NumberFormatter::onlyDigits($respTecnico->cnpj));
        XmlHelper::append($infRespTec, 'xContato', XmlHelper::sanitizeText($respTecnico->contato, 60));
        XmlHelper::append($infRespTec, 'email', mb_strtolower(mb_substr(trim($respTecnico->email), 0, 60, 'UTF-8'), 'UTF-8'));
        XmlHelper::append($infRespTec, 'fone', $fone);

        $hashCsrt = $respTecnico->hashCsrt;

        if (($hashCsrt === null || $hashCsrt === '') && filled($respTecnico->idCsrt) && filled($respTecnico->csrtToken)) {
            $hashCsrt = base64_encode(hash('sha1', $chave . $respTecnico->csrtToken, true));
        }

        if (filled($respTecnico->idCsrt) && filled($hashCsrt)) {
            XmlHelper::append(
                $infRespTec,
                'idCSRT',
                str_pad(NumberFormatter::onlyDigits($respTecnico->idCsrt), 2, '0', STR_PAD_LEFT),
            );
            XmlHelper::append($infRespTec, 'hashCSRT', $hashCsrt);
        }
    }

    private function normalizeRespTecnicoFone(string $fone): string
    {
        $digits = ltrim(NumberFormatter::onlyDigits($fone), '0');

        if (strlen($digits) > 14) {
            $digits = substr($digits, 0, 14);
        }

        return $digits;
    }
}
