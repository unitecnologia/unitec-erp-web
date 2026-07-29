<?php

namespace Unitec\FiscalEngine\Nfe;

use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\EmitirNfeRequest;
use Unitec\FiscalEngine\Dto\EmitirNfeResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Nfce\ScNfceSoapClient;
use Unitec\FiscalEngine\Xml\NfeXmlBuilder;
use Unitec\FiscalEngine\Xml\XmlSigner;

final class NfeEmitter
{
    public function __construct(
        private readonly NfeXmlBuilder $xmlBuilder = new NfeXmlBuilder(),
        private readonly XmlSigner $signer = new XmlSigner(),
        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),
        private readonly NfeAutorizacaoParser $parser = new NfeAutorizacaoParser(),
    ) {}

    public function emitir(EmitirNfeRequest $request): EmitirNfeResponse
    {
        $prepared = $this->prepararAssinada($request);

        return $this->autorizarNfeAssinada(
            nfeXml: $prepared['nfeXml'],
            certificate: $request->certificate,
            tpAmb: $request->ide->tpAmb,
            chave: $prepared['chave'],
            numero: $request->ide->numero,
            serie: $request->ide->serie,
            cNf: $request->ide->cNf,
        );
    }

    /**
     * @return array{nfeXml: string, chave: string, enviNfe: string}
     */
    public function prepararAssinada(EmitirNfeRequest $request): array
    {
        $built = $this->xmlBuilder->build($request);
        $dom = $built['dom'];
        $chave = $built['chave'];

        $this->signer->signInfNFe($dom, $request->certificate);
        $nfeXml = $this->xmlBuilder->finalizeNfeXml($dom);

        return [
            'nfeXml' => $nfeXml,
            'chave' => $chave,
            'enviNfe' => $this->xmlBuilder->wrapEnviNfe($nfeXml),
        ];
    }

    public function autorizarNfeAssinada(
        string $nfeXml,
        Certificate $certificate,
        int $tpAmb,
        string $chave,
        int $numero,
        int $serie,
        int $cNf,
    ): EmitirNfeResponse {
        $enviNfe = $this->xmlBuilder->wrapEnviNfe($nfeXml);
        $endpoint = ScNfeEndpoints::autorizacao($tpAmb);
        $cUf = substr($chave, 0, 2);
        $soapResponse = $this->soapClient->autorizar($endpoint, $enviNfe, $certificate, $cUf);
        $parsed = $this->parser->parse($soapResponse);

        if (! $parsed['autorizada']) {
            $motivo = $parsed['motivo'] ?: 'NF-e rejeitada pela SEFAZ.';
            $codigo = $parsed['codigo'] !== '' ? " [cStat {$parsed['codigo']}]" : '';

            throw new FiscalEngineException(
                $motivo . $codigo,
                $parsed['codigo'] !== '' ? $parsed['codigo'] : null,
                $parsed['motivo'] ?: null,
            );
        }

        $xmlAutorizado = $this->parser->montarXmlAutorizado($nfeXml, $parsed['protocoloXml']);

        return new EmitirNfeResponse(
            autorizada: true,
            chave: $chave,
            protocolo: $parsed['protocolo'],
            xml: $xmlAutorizado,
            statusCodigo: $parsed['codigo'],
            statusMotivo: $parsed['motivo'],
            numero: $numero,
            serie: $serie,
            cNf: $cNf,
        );
    }
}
