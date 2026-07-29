<?php



namespace Unitec\FiscalEngine\Nfe;



use DOMDocument;

use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;

use Unitec\FiscalEngine\Dto\InutilizarNfeRequest;

use Unitec\FiscalEngine\Dto\InutilizarNfeResponse;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

use Unitec\FiscalEngine\Nfce\ScNfceSoapClient;

use Unitec\FiscalEngine\Util\XmlHelper;

use Unitec\FiscalEngine\Xml\NfceInutilizacaoXmlBuilder;

use Unitec\FiscalEngine\Xml\XmlSigner;



final class NfeInutilizador

{

    /** @var list<string> */

    private const CODIGOS_SUCESSO = ['102', '563'];



    public function __construct(

        private readonly NfceInutilizacaoXmlBuilder $xmlBuilder = new NfceInutilizacaoXmlBuilder(),

        private readonly XmlSigner $signer = new XmlSigner(),

        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),

    ) {}



    public function inutilizar(InutilizarNfeRequest $request): InutilizarNfeResponse

    {

        $built = $this->xmlBuilder->build($this->toBuilderRequest($request));

        $dom = $built['dom'];



        $this->signer->signInfInut($dom, $request->certificate);



        $inutNfe = $this->xmlBuilder->finalizeInutNfe($dom);

        $endpoint = ScNfeEndpoints::inutilizacao($request->tpAmb);



        $soapResponse = $this->soapClient->inutilizacao(

            $endpoint,

            $inutNfe,

            $request->certificate,

            '42',

        );



        $parsed = $this->parseInutilizacaoResponse($soapResponse);



        if (! $parsed['inutilizada']) {

            $motivo = $parsed['motivo'] ?: 'Inutilização da numeração rejeitada pela SEFAZ.';

            $codigo = $parsed['codigo'] !== '' ? " [cStat {$parsed['codigo']}]" : '';



            throw new FiscalEngineException(

                $motivo . $codigo,

                $parsed['codigo'] !== '' ? $parsed['codigo'] : null,

                $parsed['motivo'] ?: null,

            );

        }



        $xmlProtocolado = $this->montarProcInutNfe($inutNfe, $parsed['retInutNfeXml']);



        return new InutilizarNfeResponse(

            inutilizada: true,

            protocolo: $parsed['protocolo'],

            xml: $xmlProtocolado,

            statusCodigo: $parsed['codigo'],

            statusMotivo: $parsed['motivo'],

            numeroInicial: $request->numeroInicial,

            numeroFinal: $request->numeroFinal,

            serie: $request->serie,

        );

    }



    private function toBuilderRequest(InutilizarNfeRequest $request): InutilizarNfceRequest

    {

        return new InutilizarNfceRequest(

            certificate: $request->certificate,

            cnpj: $request->cnpj,

            tpAmb: $request->tpAmb,

            serie: $request->serie,

            numeroInicial: $request->numeroInicial,

            numeroFinal: $request->numeroFinal,

            justificativa: $request->justificativa,

            modelo: $request->modelo,

            dataEvento: $request->dataEvento,

        );

    }



    /**

     * @return array{inutilizada: bool, codigo: string, motivo: string, protocolo: string, retInutNfeXml: string}

     */

    private function parseInutilizacaoResponse(string $soapResponse): array

    {

        $xml = $this->extractReturnXml($soapResponse);



        if ($xml === '') {

            throw new FiscalEngineException('Resposta vazia da SEFAZ na inutilização.');

        }



        $dom = new DOMDocument();

        $dom->preserveWhiteSpace = false;



        if (! @$dom->loadXML($xml)) {

            throw new FiscalEngineException('Resposta inválida da SEFAZ na inutilização.');

        }



        $infInut = $dom->getElementsByTagName('infInut')->item(0);

        $cStat = $infInut instanceof \DOMElement

            ? $this->childValue($infInut, 'cStat')

            : $this->nodeValue($dom, 'cStat');

        $motivo = $infInut instanceof \DOMElement

            ? $this->childValue($infInut, 'xMotivo')

            : $this->nodeValue($dom, 'xMotivo');

        $protocolo = $infInut instanceof \DOMElement

            ? $this->childValue($infInut, 'nProt')

            : '';

        $retInutNfe = $dom->getElementsByTagName('inutNFe')->item(0);

        $retInutNfeXml = $retInutNfe ? ($dom->saveXML($retInutNfe) ?: '') : '';



        return [

            'inutilizada' => in_array($cStat, self::CODIGOS_SUCESSO, true),

            'codigo' => $cStat,

            'motivo' => $motivo,

            'protocolo' => $protocolo,

            'retInutNfeXml' => $retInutNfeXml,

        ];

    }



    private function extractReturnXml(string $soapResponse): string

    {

        if (preg_match('/<(?:\w+:)?nfeResultMsg[^>]*>(.*)<\/(?:\w+:)?nfeResultMsg>/s', $soapResponse, $matches)) {

            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');

        }



        if (preg_match('/<retInutNFe[\s\S]*<\/retInutNFe>/', $soapResponse, $matches)) {

            return $matches[0];

        }



        return '';

    }



    private function montarProcInutNfe(string $inutNfe, string $retInutNfeXml): string

    {

        if ($retInutNfeXml === '') {

            return '<?xml version="1.0" encoding="UTF-8"?>' . $inutNfe;

        }



        if (! preg_match('/<inutNFe[\s\S]*<\/inutNFe>/', $inutNfe, $matches)) {

            return '<?xml version="1.0" encoding="UTF-8"?>' . $retInutNfeXml;

        }



        return '<?xml version="1.0" encoding="UTF-8"?>'

            . '<procInutNFe versao="4.00" xmlns="' . XmlHelper::NFE_NS . '">'

            . $matches[0]

            . $retInutNfeXml

            . '</procInutNFe>';

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

