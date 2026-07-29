<?php

namespace Unitec\FiscalEngine\Xml;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfceInutilizacaoXmlBuilder
{
    private const VERSAO = '4.00';

    /**
     * @return array{dom: DOMDocument, infInutId: string}
     */
    public function build(InutilizarNfceRequest $request): array
    {
        $cnpj = preg_replace('/\D/', '', $request->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ do emitente inválido para inutilização.');
        }

        if ($request->numeroInicial < 1 || $request->numeroFinal < $request->numeroInicial) {
            throw new FiscalEngineException('Faixa de numeração inválida para inutilização.');
        }

        $justificativa = XmlHelper::sanitizeText($request->justificativa, 255);

        if (mb_strlen($justificativa, 'UTF-8') < 15) {
            throw new FiscalEngineException('Justificativa da inutilização deve ter no mínimo 15 caracteres.');
        }

        $modelo = str_pad(preg_replace('/\D/', '', $request->modelo) ?? '65', 2, '0', STR_PAD_LEFT);
        $serie = str_pad((string) $request->serie, 3, '0', STR_PAD_LEFT);
        $nNfIni = str_pad((string) $request->numeroInicial, 9, '0', STR_PAD_LEFT);
        $nNfFin = str_pad((string) $request->numeroFinal, 9, '0', STR_PAD_LEFT);
        $ano = $this->resolveAno($request->dataEvento);
        $cUf = '42';
        $infInutId = 'ID' . $cUf . $ano . $cnpj . $modelo . $serie . $nNfIni . $nNfFin;

        $dom = XmlHelper::createDocument('inutNFe', self::VERSAO);
        $inutNFe = $dom->documentElement;

        if (! $inutNFe instanceof DOMElement) {
            throw new FiscalEngineException('Não foi possível montar o XML de inutilização.');
        }

        $infInut = $dom->createElementNS(XmlHelper::NFE_NS, 'infInut');
        $infInut->setAttribute('Id', $infInutId);
        $inutNFe->appendChild($infInut);

        XmlHelper::append($infInut, 'tpAmb', (string) $request->tpAmb);
        XmlHelper::append($infInut, 'xServ', 'INUTILIZAR');
        XmlHelper::append($infInut, 'cUF', $cUf);
        XmlHelper::append($infInut, 'ano', $ano);
        XmlHelper::append($infInut, 'CNPJ', $cnpj);
        XmlHelper::append($infInut, 'mod', $modelo);
        XmlHelper::append($infInut, 'serie', (string) $request->serie);
        XmlHelper::append($infInut, 'nNFIni', (string) $request->numeroInicial);
        XmlHelper::append($infInut, 'nNFFin', (string) $request->numeroFinal);
        XmlHelper::append($infInut, 'xJust', $justificativa);

        return [
            'dom' => $dom,
            'infInutId' => $infInutId,
        ];
    }

    public function finalizeInutNfe(DOMDocument $dom): string
    {
        $xml = $dom->saveXML($dom->documentElement) ?: '';

        return XmlHelper::compact($xml);
    }

    private function resolveAno(?\DateTimeInterface $dataEvento): string
    {
        $data = $dataEvento
            ? \DateTimeImmutable::createFromInterface($dataEvento)
            : new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));

        return $data->format('y');
    }
}
