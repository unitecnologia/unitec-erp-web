<?php

namespace Unitec\FiscalEngine\Nfe;

use DOMDocument;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeRequest;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeResponse;
use Unitec\FiscalEngine\Dto\DfeResumoNfe;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;

final class DfeDistribuidor
{
    public function __construct(
        private readonly DistribuicaoDfeSoapClient $soapClient = new DistribuicaoDfeSoapClient(),
        private readonly DfeDocumentoParser $documentoParser = new DfeDocumentoParser(),
    ) {}

    public function consultar(ConsultarDistribuicaoDfeRequest $request): ConsultarDistribuicaoDfeResponse
    {
        $cnpj = preg_replace('/\D/', '', $request->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ inválido para consulta na Distribuição DF-e.');
        }

        $ultNsu = self::normalizarNsu($request->ultNsu);
        $chave = $request->chave !== null
            ? (preg_replace('/\D/', '', $request->chave) ?? '')
            : null;

        if ($chave !== null && strlen($chave) !== 44) {
            throw new FiscalEngineException('Chave de acesso inválida para consulta na Distribuição DF-e.');
        }

        $xml = $this->buildDistDfeInt($request->tpAmb, $request->cUfAutor, $cnpj, $ultNsu, $chave);
        $endpoint = AnDistribuicaoDfeEndpoints::distribuicao($request->tpAmb);

        $soapResponse = $this->soapClient->distDfeInteresse(
            $endpoint,
            $xml,
            $request->certificate,
        );

        return $this->parseResponse($soapResponse);
    }

    public static function normalizarNsu(string $nsu): string
    {
        $digits = preg_replace('/\D/', '', $nsu) ?? '';

        if ($digits === '') {
            return '000000000000000';
        }

        return str_pad(substr($digits, -15), 15, '0', STR_PAD_LEFT);
    }

    private function buildDistDfeInt(int $tpAmb, string $cUfAutor, string $cnpj, string $ultNsu, ?string $chave = null): string
    {
        $dom = XmlHelper::createDocument('distDFeInt', '1.01');
        $root = $dom->documentElement;

        if (! $root instanceof \DOMElement) {
            throw new FiscalEngineException('Não foi possível montar a consulta de Distribuição DF-e.');
        }

        XmlHelper::append($root, 'tpAmb', (string) $tpAmb);
        XmlHelper::append($root, 'cUFAutor', $cUfAutor);
        XmlHelper::append($root, 'CNPJ', $cnpj);

        if ($chave !== null) {
            $consChNfe = $dom->createElementNS(XmlHelper::NFE_NS, 'consChNFe');
            XmlHelper::append($consChNfe, 'chNFe', $chave);
            $root->appendChild($consChNfe);
        } else {
            $distNsu = $dom->createElementNS(XmlHelper::NFE_NS, 'distNSU');
            XmlHelper::append($distNsu, 'ultNSU', $ultNsu);
            $root->appendChild($distNsu);
        }

        return XmlHelper::compact($dom->saveXML($root) ?: '');
    }

    private function parseResponse(string $soapResponse): ConsultarDistribuicaoDfeResponse
    {
        $xml = $this->extractReturnXml($soapResponse);

        if ($xml === '') {
            throw new FiscalEngineException('Resposta vazia da Distribuição DF-e.');
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            throw new FiscalEngineException('Resposta inválida da Distribuição DF-e.');
        }

        $cStat = $this->nodeValue($dom, 'cStat');
        $motivo = $this->nodeValue($dom, 'xMotivo');
        $ultNsu = self::normalizarNsu($this->nodeValue($dom, 'ultNSU'));
        $maxNsu = self::normalizarNsu($this->nodeValue($dom, 'maxNSU'));

        if ($cStat === '656') {
            throw new FiscalEngineException(
                'Consumo indevido: a SEFAZ exige intervalo de 1 hora entre consultas quando não há novos documentos.',
            );
        }

        if (! in_array($cStat, ['137', '138'], true)) {
            throw new FiscalEngineException(
                $motivo !== ''
                    ? $motivo . ' [cStat ' . $cStat . ']'
                    : 'Distribuição DF-e rejeitada [cStat ' . $cStat . '].',
            );
        }

        /** @var list<DfeResumoNfe> $documentos */
        $documentos = [];

        foreach ($dom->getElementsByTagName('docZip') as $docZip) {
            if (! $docZip instanceof \DOMElement) {
                continue;
            }

            $nsu = self::normalizarNsu((string) $docZip->getAttribute('NSU'));
            $schema = (string) $docZip->getAttribute('schema');
            $conteudo = trim((string) $docZip->textContent);

            if ($conteudo === '') {
                continue;
            }

            $resumo = $this->documentoParser->parseDocZip($nsu, $schema, $conteudo);

            if ($resumo instanceof DfeResumoNfe) {
                $documentos[] = $resumo;
            }
        }

        return new ConsultarDistribuicaoDfeResponse(
            statusCodigo: $cStat,
            statusMotivo: $motivo,
            ultNsu: $ultNsu,
            maxNsu: $maxNsu,
            documentos: $documentos,
            xml: $xml,
        );
    }

    private function extractReturnXml(string $soapResponse): string
    {
        if (preg_match('/<(?:\w+:)?nfeDistDFeInteresseResult[^>]*>(.*)<\/(?:\w+:)?nfeDistDFeInteresseResult>/s', $soapResponse, $matches)) {
            return html_entity_decode($matches[1], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        }

        if (preg_match('/<retDistDFeInt[\s\S]*<\/retDistDFeInt>/', $soapResponse, $matches)) {
            return $matches[0];
        }

        return '';
    }

    private function nodeValue(DOMDocument $dom, string $tag): string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim((string) $nodes->item(0)?->textContent) : '';
    }
}
