<?php

namespace Unitec\FiscalEngine\Xml;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\ItemDto;
use Unitec\FiscalEngine\Dto\RespTecnicoDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Nfce\NfcePublicUrls;
use Unitec\FiscalEngine\Util\ChaveAcesso;
use Unitec\FiscalEngine\Util\NumberFormatter;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfceXmlBuilder
{
    private const HOMOLOGACAO_DEST = 'NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';

    private const HOMOLOGACAO_ITEM = 'NOTA FISCAL EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL';

    /**
     * @return array{dom: DOMDocument, chave: string, dhEmiIso: string, valorIcms: float}
     */
    public function build(EmitirNfceRequest $request): array
    {
        if ($request->itens === []) {
            throw new FiscalEngineException('NFC-e sem itens.');
        }

        $emitente = $request->emitente;
        $ide = $request->ide;
        $chave = ChaveAcesso::gerar(
            uf: $emitente->uf,
            emissao: $ide->dataEmissao,
            cnpj: $emitente->cnpj,
            modelo: '65',
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

        $this->appendIde($infNFe, $emitente, $ide, $chave, $dhEmi);
        $this->appendEmitente($infNFe, $emitente);
        $this->appendDestinatario($infNFe, $request);

        $totaisImposto = [
            'vBc' => 0.0,
            'vIcms' => 0.0,
            'vIcmsDeson' => 0.0,
            'vIpi' => 0.0,
            'vPis' => 0.0,
            'vCofins' => 0.0,
            'vFcp' => 0.0,
            'vBcSt' => 0.0,
            'vSt' => 0.0,
            'vFcpSt' => 0.0,
            'vFcpStRet' => 0.0,
        ];

        foreach ($request->itens as $item) {
            $totaisImposto = NfeImpostoXmlBuilder::somarTotais($item->imposto, $totaisImposto);
            $this->appendItem($infNFe, $item, $request->homologacao && $item->numero === 1);
        }

        $this->appendTotais($infNFe, $request, $totaisImposto);
        $this->appendTransporte($infNFe);
        $this->appendPagamentos($infNFe, $request);
        $this->appendInformacoes($infNFe, $request->informacoesComplementares);
        $this->appendRespTecnico($infNFe, $request->respTecnico);

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

    private function appendIde(DOMElement $infNFe, $emitente, $ide, string $chave, string $dhEmi): void
    {
        $ideEl = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ide');
        $infNFe->appendChild($ideEl);

        $cUf = substr($chave, 0, 2);
        XmlHelper::append($ideEl, 'cUF', $cUf);
        XmlHelper::append($ideEl, 'cNF', substr($chave, 35, 8));
        XmlHelper::append($ideEl, 'natOp', XmlHelper::sanitizeText($ide->natOp, 60));
        XmlHelper::append($ideEl, 'mod', '65');
        XmlHelper::append($ideEl, 'serie', (string) $ide->serie);
        XmlHelper::append($ideEl, 'nNF', (string) $ide->numero);
        XmlHelper::append($ideEl, 'dhEmi', $dhEmi);
        XmlHelper::append($ideEl, 'tpNF', '1');
        XmlHelper::append($ideEl, 'idDest', '1');
        XmlHelper::append($ideEl, 'cMunFG', $ide->codigoMunicipioFg);
        XmlHelper::append($ideEl, 'tpImp', '4');
        XmlHelper::append($ideEl, 'tpEmis', (string) $ide->tpEmis);
        XmlHelper::append($ideEl, 'cDV', substr($chave, -1));
        XmlHelper::append($ideEl, 'tpAmb', (string) $ide->tpAmb);
        XmlHelper::append($ideEl, 'finNFe', '1');
        XmlHelper::append($ideEl, 'indFinal', '1');
        XmlHelper::append($ideEl, 'indPres', '1');
        XmlHelper::append($ideEl, 'procEmi', '0');
        XmlHelper::append($ideEl, 'verProc', 'UnitecERP-1.0');

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

    private function appendDestinatario(DOMElement $infNFe, EmitirNfceRequest $request): void
    {
        $destinatario = $request->destinatario;

        if ($destinatario === null) {
            return;
        }

        $cpf = NumberFormatter::onlyDigits($destinatario->cpf);
        $cnpj = NumberFormatter::onlyDigits($destinatario->cnpj);

        if ($cpf === '' && $cnpj === '') {
            return;
        }

        $dest = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'dest');
        $infNFe->appendChild($dest);

        if (strlen($cpf) === 11) {
            XmlHelper::append($dest, 'CPF', $cpf);
        } elseif (strlen($cnpj) === 14) {
            XmlHelper::append($dest, 'CNPJ', $cnpj);
        }

        $nome = $request->homologacao
            ? self::HOMOLOGACAO_DEST
            : ($destinatario->nome ?: 'CONSUMIDOR');
        XmlHelper::append($dest, 'xNome', XmlHelper::sanitizeText($nome, 60));

        if ($destinatario->hasEndereco()) {
            $ender = $dest->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'enderDest');
            $dest->appendChild($ender);

            XmlHelper::append($ender, 'xLgr', XmlHelper::sanitizeText((string) $destinatario->logradouro, 60));
            XmlHelper::append($ender, 'nro', XmlHelper::sanitizeText((string) ($destinatario->numero ?: 'S/N'), 60));
            XmlHelper::append($ender, 'xBairro', XmlHelper::sanitizeText((string) $destinatario->bairro, 60));
            XmlHelper::append($ender, 'cMun', NumberFormatter::onlyDigits((string) $destinatario->codigoMunicipio));
            XmlHelper::append($ender, 'xMun', XmlHelper::sanitizeText((string) $destinatario->municipio, 60));
            XmlHelper::append($ender, 'UF', strtoupper(trim((string) $destinatario->uf)));
            XmlHelper::append(
                $ender,
                'CEP',
                str_pad(NumberFormatter::onlyDigits((string) $destinatario->cep), 8, '0', STR_PAD_LEFT),
            );

            $fone = NumberFormatter::onlyDigits((string) ($destinatario->telefone ?? ''));
            if ($fone !== '') {
                XmlHelper::append($ender, 'fone', $fone);
            }
        }

        XmlHelper::append($dest, 'indIEDest', '9');

        $email = trim((string) ($destinatario->email ?? ''));
        if ($email !== '') {
            XmlHelper::append($dest, 'email', mb_substr($email, 0, 60, 'UTF-8'));
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
        $cestRaw = trim((string) ($item->cest ?? ''));
        if ($cestRaw !== '') {
            $cest = NumberFormatter::onlyDigits($cestRaw);
            if (strlen($cest) > 0 && strlen($cest) < 7) {
                $cest = str_pad($cest, 7, '0', STR_PAD_LEFT);
            }
            if (strlen($cest) !== 7) {
                throw new FiscalEngineException(
                    'CEST inválido no item '.$item->numero.': deve ter 7 dígitos (zeros à esquerda). '
                    .'Não omitir CEST cadastrado inválido.',
                );
            }
            XmlHelper::append($prod, 'CEST', $cest);
        }
        XmlHelper::append($prod, 'CFOP', NumberFormatter::onlyDigits($item->cfop));
        XmlHelper::append($prod, 'uCom', XmlHelper::sanitizeText($item->unidade, 6));
        XmlHelper::append($prod, 'qCom', NumberFormatter::decimal($item->quantidade, 4));
        XmlHelper::append($prod, 'vUnCom', NumberFormatter::decimal($item->valorUnitario, 4));
        XmlHelper::append($prod, 'vProd', NumberFormatter::decimal($item->valorTotal));
        XmlHelper::append($prod, 'cEANTrib', 'SEM GTIN');
        XmlHelper::append($prod, 'uTrib', XmlHelper::sanitizeText($item->unidade, 6));
        XmlHelper::append($prod, 'qTrib', NumberFormatter::decimal($item->quantidade, 4));
        XmlHelper::append($prod, 'vUnTrib', NumberFormatter::decimal($item->valorUnitario, 4));

        if ($item->desconto > 0) {
            XmlHelper::append($prod, 'vDesc', NumberFormatter::decimal($item->desconto));
        }

        if (($item->acrescimo ?? 0) > 0) {
            XmlHelper::append($prod, 'vOutro', NumberFormatter::decimal($item->acrescimo));
        }

        XmlHelper::append($prod, 'indTot', '1');

        if ($item->combustivel !== null) {
            CombXmlBuilder::append($prod, $item->combustivel);
        }

        $imposto = $det->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'imposto');
        $det->appendChild($imposto);

        if ($item->imposto->vTotTrib > 0) {
            XmlHelper::append($imposto, 'vTotTrib', NumberFormatter::decimal($item->imposto->vTotTrib));
        }

        // ICMS: CRT 1/4 → ICMSSN; CRT 2/3 → ICMS+CST. PIS/COFINS conforme DTO (CRT 2 sem alíquotas Presumido).
        NfeImpostoXmlBuilder::appendIcms($imposto, $item->imposto);
        NfeImpostoXmlBuilder::appendPis($imposto, $item->imposto);
        NfeImpostoXmlBuilder::appendCofins($imposto, $item->imposto);

        IbscbsXmlBuilder::appendItem($imposto, $item->imposto);
    }

    /**
     * @param  array{vBc: float, vIcms: float, vIcmsDeson: float, vIpi: float, vPis: float, vCofins: float}  $totaisImposto
     */
    private function appendTotais(DOMElement $infNFe, EmitirNfceRequest $request, array $totaisImposto): void
    {
        $total = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'total');
        $infNFe->appendChild($total);

        $icmsTot = $total->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'ICMSTot');
        $total->appendChild($icmsTot);

        XmlHelper::append($icmsTot, 'vBC', NumberFormatter::decimal($totaisImposto['vBc']));
        XmlHelper::append($icmsTot, 'vICMS', NumberFormatter::decimal($totaisImposto['vIcms']));
        XmlHelper::append($icmsTot, 'vICMSDeson', NumberFormatter::decimal($totaisImposto['vIcmsDeson']));
        // NFC-e online normalmente sem ST; totais refletem o DTO (zeros se não houver).
        XmlHelper::append($icmsTot, 'vFCP', NumberFormatter::decimal((float) ($totaisImposto['vFcp'] ?? 0)));
        XmlHelper::append($icmsTot, 'vBCST', NumberFormatter::decimal((float) ($totaisImposto['vBcSt'] ?? 0)));
        XmlHelper::append($icmsTot, 'vST', NumberFormatter::decimal((float) ($totaisImposto['vSt'] ?? 0)));
        XmlHelper::append($icmsTot, 'vFCPST', NumberFormatter::decimal((float) ($totaisImposto['vFcpSt'] ?? 0)));
        XmlHelper::append($icmsTot, 'vFCPSTRet', NumberFormatter::decimal((float) ($totaisImposto['vFcpStRet'] ?? 0)));
        XmlHelper::append($icmsTot, 'vProd', NumberFormatter::decimal($request->valorProdutos));
        XmlHelper::append($icmsTot, 'vFrete', '0.00');
        XmlHelper::append($icmsTot, 'vSeg', '0.00');
        XmlHelper::append($icmsTot, 'vDesc', NumberFormatter::decimal($request->valorDesconto));
        XmlHelper::append($icmsTot, 'vII', '0.00');
        XmlHelper::append($icmsTot, 'vIPI', NumberFormatter::decimal($totaisImposto['vIpi']));
        XmlHelper::append($icmsTot, 'vIPIDevol', '0.00');
        XmlHelper::append($icmsTot, 'vPIS', NumberFormatter::decimal($totaisImposto['vPis']));
        XmlHelper::append($icmsTot, 'vCOFINS', NumberFormatter::decimal($totaisImposto['vCofins']));
        XmlHelper::append($icmsTot, 'vOutro', NumberFormatter::decimal($request->valorAcrescimo));
        XmlHelper::append($icmsTot, 'vNF', NumberFormatter::decimal($request->valorNota));
        XmlHelper::append($icmsTot, 'vTotTrib', NumberFormatter::decimal($request->valorTotTrib));

        IbscbsXmlBuilder::appendTotais($total, $request->itens);
    }

    private function appendTransporte(DOMElement $infNFe): void
    {
        $transp = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'transp');
        $infNFe->appendChild($transp);
        XmlHelper::append($transp, 'modFrete', '9');
    }

    private function appendPagamentos(DOMElement $infNFe, EmitirNfceRequest $request): void
    {
        $pag = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'pag');
        $infNFe->appendChild($pag);

        foreach ($request->pagamentos as $pagamento) {
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

    private function appendInformacoes(DOMElement $infNFe, string $informacoes): void
    {
        $texto = trim($informacoes);

        if ($texto === '') {
            return;
        }

        $infAdic = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'infAdic');
        $infNFe->appendChild($infAdic);
        XmlHelper::append($infAdic, 'infCpl', XmlHelper::sanitizeText($texto, 5000));
    }

    private function appendRespTecnico(DOMElement $infNFe, ?RespTecnicoDto $respTecnico): void
    {
        if ($respTecnico === null || ! $respTecnico->isComplete()) {
            return;
        }

        $infRespTec = $infNFe->ownerDocument->createElementNS(XmlHelper::NFE_NS, 'infRespTec');
        $infNFe->appendChild($infRespTec);

        XmlHelper::append($infRespTec, 'CNPJ', NumberFormatter::onlyDigits($respTecnico->cnpj));
        XmlHelper::append($infRespTec, 'xContato', XmlHelper::sanitizeText($respTecnico->contato, 60));
        XmlHelper::append($infRespTec, 'email', mb_substr(trim($respTecnico->email), 0, 60, 'UTF-8'));
        XmlHelper::append($infRespTec, 'fone', NumberFormatter::onlyDigits($respTecnico->fone));

        if (filled($respTecnico->idCsrt) && filled($respTecnico->hashCsrt)) {
            XmlHelper::append(
                $infRespTec,
                'idCSRT',
                str_pad(NumberFormatter::onlyDigits($respTecnico->idCsrt), 2, '0', STR_PAD_LEFT),
            );
            XmlHelper::append($infRespTec, 'hashCSRT', $respTecnico->hashCsrt);
        }
    }

    public function appendQrCode(DOMDocument $dom, string $qrUrl, int $tpAmb): void
    {
        $nfe = $dom->getElementsByTagName('NFe')->item(0);

        if (! $nfe instanceof DOMElement) {
            throw new FiscalEngineException('NFe não encontrada para QR Code.');
        }

        $chave = $this->chaveFromDom($dom);

        $supl = $dom->createElementNS(XmlHelper::NFE_NS, 'infNFeSupl');
        XmlHelper::append($supl, 'qrCode', $qrUrl);
        XmlHelper::append($supl, 'urlChave', NfcePublicUrls::urlChave($chave !== '' ? $chave : 'SC', $tpAmb));

        $signature = $dom->getElementsByTagName('Signature')->item(0);

        if ($signature !== null) {
            $nfe->insertBefore($supl, $signature);
        } else {
            $nfe->appendChild($supl);
        }
    }

    private function chaveFromDom(DOMDocument $dom): string
    {
        $infNFe = $dom->getElementsByTagName('infNFe')->item(0);

        if (! $infNFe instanceof DOMElement) {
            return '';
        }

        $id = trim($infNFe->getAttribute('Id'));

        if (str_starts_with($id, 'NFe') && strlen($id) >= 47) {
            return substr($id, 3, 44);
        }

        return '';
    }
}
