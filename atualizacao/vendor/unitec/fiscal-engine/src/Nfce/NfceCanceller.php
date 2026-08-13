<?php

namespace Unitec\FiscalEngine\Nfce;

use DOMDocument;
use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Dto\CancelarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;
use Unitec\FiscalEngine\Xml\NfceEventoXmlBuilder;
use Unitec\FiscalEngine\Xml\XmlSigner;

final class NfceCanceller
{
    /** @var list<string> */
    private const CODIGOS_SUCESSO = ['101', '135', '155'];

    public function __construct(
        private readonly NfceEventoXmlBuilder $xmlBuilder = new NfceEventoXmlBuilder(),
        private readonly XmlSigner $signer = new XmlSigner(),
        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),
    ) {}

    public function cancelar(CancelarNfceRequest $request): CancelarNfceResponse
    {
        $built = $this->xmlBuilder->buildCancelamento($request);
        $dom = $built['dom'];

        $this->signer->signInfEvento($dom, $request->certificate);

        $envEvento = $this->xmlBuilder->finalizeEnvEvento($dom);
        $chave = preg_replace('/\D/', '', $request->chave) ?? '';
        $cUf = substr($chave, 0, 2);
        $endpoint = ScNfceEndpoints::recepcaoEvento($request->tpAmb);

        $soapResponse = $this->soapClient->recepcaoEvento(
            $endpoint,
            $envEvento,
            $request->certificate,
            $cUf,
        );

        $parsed = $this->parseRecepcaoEventoResponse($soapResponse);

        if (! $parsed['cancelada']) {
            $motivo = $parsed['motivo'] ?: 'Cancelamento da NFC-e rejeitado pela SEFAZ.';
            $codigo = $parsed['codigo'] !== '' ? " [cStat {$parsed['codigo']}]" : '';

            throw new FiscalEngineException(
                $motivo . $codigo,
                $parsed['codigo'] !== '' ? $parsed['codigo'] : null,
                $parsed['motivo'] ?: null,
            );
        }

        $xmlProtocolado = $this->montarProcEvento($envEvento, $parsed['retEventoXml']);

        return new CancelarNfceResponse(
            cancelada: true,
            chave: $chave,
            protocoloEvento: $parsed['protocolo'],
            xml: $xmlProtocolado,
            statusCodigo: $parsed['codigo'],
            statusMotivo: $parsed['motivo'],
        );
    }

    /**
     * @return array{cancelada: bool, codigo: string, motivo: string, protocolo: string, retEventoXml: string}
     */
    private function parseRecepcaoEventoResponse(string $soapResponse): array
    {
        $xml = $this->extractReturnXml($soapResponse);

        if ($xml === '') {
            throw new FiscalEngineException('Resposta vazia da SEFAZ no cancelamento.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new FiscalEngineException('Resposta inválida da SEFAZ no cancelamento.');
        }

        $cStatLote = $this->nodeValue($dom, 'cStat');
        $xMotivoLote = $this->nodeValue($dom, 'xMotivo');

        if ($cStatLote !== '128') {
            return [
                'cancelada' => false,
                'codigo' => $cStatLote,
                'motivo' => $xMotivoLote,
                'protocolo' => '',
                'retEventoXml' => '',
            ];
        }

        $retEvento = $dom->getElementsByTagName('retEvento')->item(0);
        $retEventoXml = $retEvento ? ($dom->saveXML($retEvento) ?: '') : '';

        if (! $retEvento instanceof \DOMElement) {
            return [
                'cancelada' => false,
                'codigo' => $cStatLote,
                'motivo' => $xMotivoLote ?: 'Lote processado sem retorno de evento.',
                'protocolo' => '',
                'retEventoXml' => '',
            ];
        }

        $infEvento = $retEvento->getElementsByTagName('infEvento')->item(0);
        $cStat = $infEvento instanceof \DOMElement
            ? $this->childValue($infEvento, 'cStat')
            : $this->childValue($retEvento, 'cStat');
        $motivo = $infEvento instanceof \DOMElement
            ? $this->childValue($infEvento, 'xMotivo')
            : $this->childValue($retEvento, 'xMotivo');
        $protocolo = $infEvento instanceof \DOMElement
            ? $this->childValue($infEvento, 'nProt')
            : '';

        return [
            'cancelada' => in_array($cStat, self::CODIGOS_SUCESSO, true) || $cStat === '573',
            'codigo' => $cStat,
            'motivo' => $motivo,
            'protocolo' => $protocolo,
            'retEventoXml' => $retEventoXml,
        ];
    }

    private function extractReturnXml(string $soapResponse): string
    {
        if (preg_match('/<(?:\w+:)?nfeRecepcaoEventoResult[^>]*>(.*)<\/(?:\w+:)?nfeRecepcaoEventoResult>/s', $soapResponse, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        if (preg_match('/<(?:\w+:)?nfeResultMsg[^>]*>(.*)<\/(?:\w+:)?nfeResultMsg>/s', $soapResponse, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        if (preg_match('/<retEnvEvento[\s\S]*<\/retEnvEvento>/', $soapResponse, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function montarProcEvento(string $envEvento, string $retEventoXml): string
    {
        if ($retEventoXml === '') {
            return '<?xml version="1.0" encoding="UTF-8"?>' . $envEvento;
        }

        if (! preg_match('/<evento[\s\S]*<\/evento>/', $envEvento, $matches)) {
            return '<?xml version="1.0" encoding="UTF-8"?>' . $retEventoXml;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<procEventoNFe versao="1.00" xmlns="' . XmlHelper::NFE_NS . '">'
            . $matches[0]
            . $retEventoXml
            . '</procEventoNFe>';
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
