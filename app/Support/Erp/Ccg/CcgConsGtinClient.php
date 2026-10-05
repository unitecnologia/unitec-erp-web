<?php

namespace App\Support\Erp\Ccg;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Certificate\Certificate;

final class CcgConsGtinClient
{
    public function __construct(
        private readonly ?CcgSoapTransport $transport = null,
    ) {}

    public function consultar(string $gtin, Certificate $certificate, string $url, int $timeoutSeconds): CcgConsGtinResult
    {
        $gtin = Gtin::digits($gtin);

        if (! Gtin::isAcceptedLength($gtin)) {
            throw new CcgConsultaException(
                'Informe um GTIN de 8, 12, 13 ou 14 dígitos. A consulta não foi enviada à SVRS.',
                CcgConsultaException::REJEICAO,
            );
        }

        if (! Gtin::hasValidCheckDigit($gtin)) {
            throw new CcgConsultaException(
                '9491 – Rejeição: GTIN com dígito verificador inválido. A consulta não foi enviada à SVRS.',
                CcgConsultaException::REJEICAO,
            );
        }

        $url = trim($url);

        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new CcgConsultaException(
                'A URL da consulta GTIN precisa ser HTTPS.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        $xml = $this->transport()->post(
            $url,
            $this->buildEnvelope($gtin),
            $certificate,
            $timeoutSeconds,
        );

        $result = $this->parseResponse($xml);

        if (! $result->sucesso()) {
            $tipo = $result->cStat === '656'
                ? CcgConsultaException::INDISPONIVEL
                : CcgConsultaException::REJEICAO;

            throw new CcgConsultaException($result->mensagem(), $tipo);
        }

        return $result;
    }

    public function buildEnvelope(string $gtin): string
    {
        $gtin = Gtin::digits($gtin);

        return '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<ccgConsGTIN xmlns="' . CcgConsGtinEndpoints::WSDL_NS . '">'
            . '<nfeDadosMsg>'
            . '<consGTIN xmlns="' . CcgConsGtinEndpoints::NFE_NS . '" versao="' . CcgConsGtinEndpoints::VERSAO . '">'
            . '<GTIN>' . $gtin . '</GTIN>'
            . '</consGTIN>'
            . '</nfeDadosMsg>'
            . '</ccgConsGTIN>'
            . '</soap12:Body>'
            . '</soap12:Envelope>';
    }

    public function parseResponse(string $xml): CcgConsGtinResult
    {
        $document = new DOMDocument();
        $loaded = @$document->loadXML($xml);

        if ($loaded !== true) {
            throw new CcgConsultaException(
                'Serviço de consulta GTIN da SVRS indisponível. A resposta não é um XML válido.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        $cStat = $this->text($document, 'cStat');

        if ($cStat === '') {
            $fault = self::soapFaultText($document);

            if ($fault !== null) {
                throw new CcgConsultaException($fault, CcgConsultaException::INDISPONIVEL);
            }

            throw new CcgConsultaException(
                'Serviço de consulta GTIN da SVRS indisponível. A resposta não trouxe cStat.',
                CcgConsultaException::INDISPONIVEL,
            );
        }

        $xMotivo = $this->text($document, 'xMotivo');

        if ($xMotivo === '') {
            $xMotivo = CcgConsGtinResult::motivosOficiais()[$cStat] ?? '';
        }

        $ncm = preg_replace('/\D/', '', $this->text($document, 'NCM')) ?? '';
        $cests = [];

        foreach ($document->getElementsByTagName('CEST') as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $cest = preg_replace('/\D/', '', trim($node->textContent)) ?? '';

            if (strlen($cest) >= 7) {
                $cests[] = substr($cest, 0, 7);
            }
        }

        $gtin = Gtin::digits($this->text($document, 'GTIN'));
        $tpGtin = preg_replace('/\D/', '', $this->text($document, 'tpGTIN')) ?? '';
        $xProd = trim(preg_replace('/\s+/', ' ', $this->text($document, 'xProd')) ?? '');

        return new CcgConsGtinResult(
            cStat: $cStat,
            xMotivo: $xMotivo,
            gtin: $gtin !== '' ? $gtin : null,
            tpGtin: $tpGtin !== '' ? $tpGtin : null,
            xProd: $xProd !== '' ? $xProd : null,
            ncm: strlen($ncm) === 8 ? $ncm : null,
            cests: array_values(array_unique($cests)),
        );
    }

    private function transport(): CcgSoapTransport
    {
        return $this->transport ?? new CcgSoapCurlTransport();
    }

    public static function soapFaultText(DOMDocument $document): ?string
    {
        if ($document->getElementsByTagName('Fault')->length === 0) {
            return null;
        }

        foreach (['Text', 'faultstring', 'Reason', 'Detail'] as $tag) {
            $nodes = $document->getElementsByTagName($tag);
            $text = trim($nodes->item(0)?->textContent ?? '');

            if ($text !== '') {
                return preg_replace('/\s+/', ' ', $text) ?? $text;
            }
        }

        $fault = trim($document->getElementsByTagName('Fault')->item(0)?->textContent ?? '');

        return $fault !== '' ? (preg_replace('/\s+/', ' ', $fault) ?? $fault) : null;
    }

    private function text(DOMDocument $document, string $tag): string
    {
        $nodes = $document->getElementsByTagName($tag);

        if ($nodes->length === 0) {
            return '';
        }

        return trim($nodes->item(0)?->textContent ?? '');
    }
}
