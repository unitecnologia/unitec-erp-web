<?php

namespace Unitec\FiscalEngine\Nfce;

use DOMDocument;
use Unitec\FiscalEngine\Dto\ConsultarNfceRequest;
use Unitec\FiscalEngine\Dto\ConsultarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfceConsultor
{
    public function __construct(
        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),
    ) {}

    public function consultar(ConsultarNfceRequest $request): ConsultarNfceResponse
    {
        $chave = preg_replace('/\D/', '', $request->chave) ?? '';

        if (strlen($chave) !== 44) {
            throw new FiscalEngineException('Chave de acesso inválida para consulta da NFC-e.');
        }

        $xml = $this->buildConsSitNfe($request->tpAmb, $chave);
        $cUf = substr($chave, 0, 2);
        $endpoint = ScNfceEndpoints::consultaProtocolo($request->tpAmb);

        $soapResponse = $this->soapClient->consultaProtocolo(
            $endpoint,
            $xml,
            $request->certificate,
            $cUf,
        );

        $parsed = $this->parseConsultaResponse($soapResponse, $chave);

        return new ConsultarNfceResponse(
            chave: $chave,
            statusCodigo: $parsed['codigo'],
            statusMotivo: $parsed['motivo'],
            protocolo: $parsed['protocolo'],
            xml: $parsed['xml'],
            autorizada: $parsed['autorizada'],
            cancelada: $parsed['cancelada'],
            denegada: $parsed['denegada'],
        );
    }

    private function buildConsSitNfe(int $tpAmb, string $chave): string
    {
        $dom = XmlHelper::createDocument('consSitNFe', '4.00');
        $root = $dom->documentElement;

        if (! $root instanceof \DOMElement) {
            throw new FiscalEngineException('Não foi possível montar a consulta da NFC-e.');
        }

        XmlHelper::append($root, 'tpAmb', (string) $tpAmb);
        XmlHelper::append($root, 'xServ', 'CONSULTAR');
        XmlHelper::append($root, 'chNFe', $chave);

        return XmlHelper::compact($dom->saveXML($root) ?: '');
    }

    /**
     * @return array{codigo: string, motivo: string, protocolo: string, xml: string, autorizada: bool, cancelada: bool, denegada: bool}
     */
    private function parseConsultaResponse(string $soapResponse, string $chave): array
    {
        $xml = $this->extractReturnXml($soapResponse);

        if ($xml === '') {
            throw new FiscalEngineException('Resposta vazia da SEFAZ na consulta.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new FiscalEngineException('Resposta inválida da SEFAZ na consulta.');
        }

        $cStat = $this->nodeValue($dom, 'cStat');
        $motivo = $this->nodeValue($dom, 'xMotivo');
        $protocolo = '';

        $protNFe = $dom->getElementsByTagName('protNFe')->item(0);

        if ($protNFe instanceof \DOMElement) {
            $cStat = $this->childValue($protNFe, 'cStat') ?: $cStat;
            $motivo = $this->childValue($protNFe, 'xMotivo') ?: $motivo;
            $protocolo = $this->childValue($protNFe, 'nProt');
        }

        return [
            'codigo' => $cStat,
            'motivo' => $motivo,
            'protocolo' => $protocolo,
            'xml' => $xml,
            'autorizada' => $cStat === '100',
            'cancelada' => in_array($cStat, ['101', '151', '155'], true),
            'denegada' => in_array($cStat, ['110', '301', '302', '303'], true),
        ];
    }

    private function extractReturnXml(string $soapResponse): string
    {
        if (preg_match('/<(?:\w+:)?nfeResultMsg[^>]*>(.*)<\/(?:\w+:)?nfeResultMsg>/s', $soapResponse, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        if (preg_match('/<retConsSitNFe[\s\S]*<\/retConsSitNFe>/', $soapResponse, $matches)) {
            return $matches[0];
        }

        return '';
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
