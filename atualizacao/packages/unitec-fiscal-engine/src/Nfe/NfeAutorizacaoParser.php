<?php

namespace Unitec\FiscalEngine\Nfe;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

final class NfeAutorizacaoParser
{
    /**
     * @return array{autorizada: bool, codigo: string, motivo: string, protocolo: string, protocoloXml: string}
     */
    public function parse(string $soapResponse): array
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

        if ($protNFe instanceof DOMElement) {
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
            'autorizada' => in_array($cStatLote, ['100', '104'], true) && $protNFe instanceof DOMElement,
            'codigo' => $cStatLote,
            'motivo' => $xMotivoLote,
            'protocolo' => '',
            'protocoloXml' => '',
        ];
    }

    public function montarXmlAutorizado(string $nfeXml, string $protocoloXml): string
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

    private function nodeValue(DOMDocument $dom, string $tag): string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }

    private function childValue(DOMElement $parent, string $tag): string
    {
        $nodes = $parent->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }
}
