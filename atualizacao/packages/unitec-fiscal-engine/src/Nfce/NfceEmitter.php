<?php

namespace Unitec\FiscalEngine\Nfce;

use DOMDocument;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitirNfceRequest;
use Unitec\FiscalEngine\Dto\EmitirNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Xml\NfceXmlBuilder;
use Unitec\FiscalEngine\Xml\XmlSigner;

final class NfceEmitter
{
    public function __construct(
        private readonly NfceXmlBuilder $xmlBuilder = new NfceXmlBuilder(),
        private readonly XmlSigner $signer = new XmlSigner(),
        private readonly NfceQrCodeBuilder $qrCodeBuilder = new NfceQrCodeBuilder(),
        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),
    ) {}

    public function emitir(EmitirNfceRequest $request): EmitirNfceResponse
    {
        $prepared = $this->prepararAssinada($request);

        return $this->autorizarNfeAssinada(
            nfeXml: $prepared['nfeXml'],
            certificate: $request->certificate,
            tpAmb: $request->ide->tpAmb,
            chave: $prepared['chave'],
            qrCodeUrl: $prepared['qrUrl'],
            numero: $request->ide->numero,
            serie: $request->ide->serie,
            cNf: $request->ide->cNf,
        );
    }

    /**
     * Monta e assina NFC-e sem transmitir (contingência offline).
     *
     * @return array{nfeXml: string, chave: string, qrUrl: string, enviNfe: string}
     */
    public function prepararAssinada(EmitirNfceRequest $request): array
    {
        $built = $this->xmlBuilder->build($request);
        $dom = $built['dom'];
        $chave = $built['chave'];
        $dhEmiIso = $built['dhEmiIso'];

        $cpf = $request->destinatario?->cpf;
        $cnpj = $request->destinatario?->cnpj;
        $tpEmis = $request->ide->tpEmis;

        $this->signer->signInfNFe($dom, $request->certificate);
        $digest = $this->signer->extractDigestValue($dom);

        $qrUrl = $this->qrCodeBuilder->buildUrl(
            chave: $chave,
            tpAmb: $request->ide->tpAmb,
            tpEmis: $tpEmis,
            versaoQrcode: $request->versaoQrcode,
            idToken: $request->idToken,
            csc: $request->csc,
            dhEmiIso: $dhEmiIso,
            valorNota: $request->valorNota,
            digestValueBase64: $tpEmis === 9 ? $digest : '',
            certificate: $request->certificate,
            cpf: $cpf,
            cnpj: $cnpj,
        );
        $this->xmlBuilder->appendQrCode($dom, $qrUrl, $request->ide->tpAmb);

        $nfeXml = $this->xmlBuilder->finalizeNfeXml($dom);

        return [
            'nfeXml' => $nfeXml,
            'chave' => $chave,
            'qrUrl' => $qrUrl,
            'enviNfe' => $this->xmlBuilder->wrapEnviNfe($nfeXml),
        ];
    }

    public function autorizarNfeAssinada(
        string $nfeXml,
        Certificate $certificate,
        int $tpAmb,
        string $chave,
        string $qrCodeUrl,
        int $numero,
        int $serie,
        int $cNf,
    ): EmitirNfceResponse {
        $enviNfe = $this->xmlBuilder->wrapEnviNfe($nfeXml);
        $endpoint = ScNfceEndpoints::autorizacao($tpAmb);
        $cUf = substr($chave, 0, 2);
        $soapResponse = $this->soapClient->autorizar($endpoint, $enviNfe, $certificate, $cUf);
        $parsed = $this->parseAutorizacaoResponse($soapResponse);

        if (! $parsed['autorizada']) {
            $motivo = $parsed['motivo'] ?: 'NFC-e rejeitada pela SEFAZ.';
            $codigo = $parsed['codigo'] !== '' ? " [cStat {$parsed['codigo']}]" : '';

            throw new FiscalEngineException(
                $motivo . $codigo,
                $parsed['codigo'] !== '' ? $parsed['codigo'] : null,
                $parsed['motivo'] ?: null,
            );
        }

        $xmlAutorizado = $this->montarXmlAutorizado($nfeXml, $parsed['protocoloXml']);

        return new EmitirNfceResponse(
            autorizada: true,
            chave: $chave,
            protocolo: $parsed['protocolo'],
            xml: $xmlAutorizado,
            qrCodeUrl: $qrCodeUrl,
            statusCodigo: $parsed['codigo'],
            statusMotivo: $parsed['motivo'],
            numero: $numero,
            serie: $serie,
            cNf: $cNf,
        );
    }

    public function prepararContingencia(EmitirNfceRequest $request): EmitirNfceResponse
    {
        $prepared = $this->prepararAssinada($request);

        return new EmitirNfceResponse(
            autorizada: false,
            chave: $prepared['chave'],
            protocolo: '',
            xml: $prepared['nfeXml'],
            qrCodeUrl: $prepared['qrUrl'],
            statusCodigo: 'OFFLINE',
            statusMotivo: 'NFC-e emitida em contingência offline.',
            numero: $request->ide->numero,
            serie: $request->ide->serie,
            cNf: $request->ide->cNf,
        );
    }

    /**
     * @return array{autorizada: bool, codigo: string, motivo: string, protocolo: string, protocoloXml: string}
     */
    private function parseAutorizacaoResponse(string $soapResponse): array
    {
        $xml = $this->extractReturnXml($soapResponse);

        if ($xml === '') {
            throw new FiscalEngineException('Resposta vazia da SEFAZ.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new FiscalEngineException('Resposta inválida da SEFAZ.');
        }

        $cStatLote = $this->nodeValue($dom, 'cStat');
        $xMotivoLote = $this->nodeValue($dom, 'xMotivo');

        $protNFe = $dom->getElementsByTagName('protNFe')->item(0);
        $protocoloXml = $protNFe ? ($dom->saveXML($protNFe) ?: '') : '';

        if ($protNFe instanceof \DOMElement) {
            $cStat = $this->childValue($protNFe, 'cStat') ?: $cStatLote;
            $motivo = $this->childValue($protNFe, 'xMotivo') ?: $xMotivoLote;
            $protocolo = $this->childValue($protNFe, 'nProt');

            return [
                'autorizada' => $cStat === '100',
                'codigo' => $cStat,
                'motivo' => $motivo,
                'protocolo' => $protocolo,
                'protocoloXml' => $protocoloXml,
            ];
        }

        return [
            'autorizada' => in_array($cStatLote, ['100', '104'], true) && $protNFe instanceof \DOMElement,
            'codigo' => $cStatLote,
            'motivo' => $xMotivoLote,
            'protocolo' => '',
            'protocoloXml' => '',
        ];
    }

    private function extractReturnXml(string $soapResponse): string
    {
        if (preg_match('/<(?:\w+:)?nfeResultMsg[^>]*>(.*)<\/(?:\w+:)?nfeResultMsg>/s', $soapResponse, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        if (preg_match('/<retEnviNFe[\s\S]*<\/retEnviNFe>/', $soapResponse, $matches)) {
            return $matches[0];
        }

        if (preg_match('/<retConsReciNFe[\s\S]*<\/retConsReciNFe>/', $soapResponse, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function montarXmlAutorizado(string $nfeXml, string $protocoloXml): string
    {
        if ($protocoloXml === '') {
            return '<?xml version="1.0" encoding="UTF-8"?>' . $nfeXml;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<nfeProc xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . $nfeXml
            . $protocoloXml
            . '</nfeProc>';
    }

    private function nodeValue(DOMDocument $dom, string $tag): string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }

    private function childValue(\DOMElement $parent, string $tag): string
    {
        $nodes = $parent->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }
}
