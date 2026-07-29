<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\NotaFornecedor;
use App\Models\VendasParametro;
use DOMDocument;
use Unitec\FiscalEngine\Certificate\Certificate;
use Unitec\FiscalEngine\Dto\ConsultarDistribuicaoDfeRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;
use Unitec\FiscalEngine\Nfce\ScNfceSoapClient;
use Unitec\FiscalEngine\Util\XmlHelper;
use Unitec\FiscalEngine\Xml\XmlSigner;

/**
 * Garante XML completo (nfeProc) da NF-e de entrada para DANFE com itens.
 *
 * Fluxo SEFAZ: resumo DF-e → Ciência da Operação (210210) → DistDFe devolve procNFe.
 */
final class NotaFornecedorXmlDownloadService
{
    private const TP_EVENTO_CIENCIA = '210210';

    private const CODIGOS_CIENCIA_OK = ['135', '136', '573', '650'];

    public function __construct(
        private readonly FiscalEngine $engine = new FiscalEngine(),
        private readonly XmlSigner $signer = new XmlSigner(),
        private readonly ScNfceSoapClient $soapClient = new ScNfceSoapClient(),
    ) {}

    public function ensureProcNfe(NotaFornecedor $nota, Empresa $empresa): NotaFornecedor
    {
        $nota->refresh();

        if ($this->hasItens($nota->xml)) {
            return $nota;
        }

        $parametros = VendasParametro::forEmpresa((int) $empresa->id);
        DistribuicaoDfeConfig::validarCertificado($parametros, $empresa);

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);
        $cUfAutor = DistribuicaoDfeConfig::cUfAutor($empresa, $parametros);
        $chave = preg_replace('/\D/', '', (string) $nota->chave) ?? '';

        if (strlen($chave) !== 44) {
            throw new FiscalEngineException('Nota sem chave de acesso válida para baixar o XML.');
        }

        $xml = $this->consultarXmlPorChave($empresa, $certificate, $tpAmb, $cUfAutor, $chave);

        if ($this->hasItens($xml)) {
            return $this->persistXml($nota, $xml);
        }

        if (! $this->cienciaAindaNoPrazo($nota)) {
            throw new FiscalEngineException(
                'O prazo de 10 dias da Ciência da Operação já expirou para esta NF-e. '
                .'A SEFAZ não libera mais o XML completo por este caminho. '
                .'O DANFE será aberto com o resumo disponível. '
                .'Para ver os itens detalhados, solicite o XML (procNFe) ao fornecedor.',
                '596',
            );
        }

        try {
            $this->enviarCiencia($empresa, $certificate, $tpAmb, $chave);
        } catch (FiscalEngineException $exception) {
            if ($this->isPrazoCienciaExpirado($exception)) {
                throw new FiscalEngineException(
                    'O prazo de 10 dias da Ciência da Operação já expirou para esta NF-e. '
                    .'A SEFAZ não libera mais o XML completo por este caminho. '
                    .'O DANFE será aberto com o resumo disponível. '
                    .'Para ver os itens detalhados, solicite o XML (procNFe) ao fornecedor.',
                    '596',
                    $exception->sefazMotivo,
                    $exception,
                );
            }

            throw $exception;
        }

        $xml = $this->consultarXmlPorChave($empresa, $certificate, $tpAmb, $cUfAutor, $chave);

        if ($this->hasItens($xml)) {
            return $this->persistXml($nota, $xml);
        }

        $xml = $this->buscarProcNfeNoLote($empresa, $parametros, $certificate, $tpAmb, $cUfAutor, $chave);

        if ($this->hasItens($xml)) {
            return $this->persistXml($nota, $xml);
        }

        throw new FiscalEngineException(
            'A SEFAZ ainda não liberou o XML completo desta NF-e. '
            .'Aguarde alguns minutos e tente novamente, ou use Consulta por Chave (F2).',
        );
    }

    private function cienciaAindaNoPrazo(NotaFornecedor $nota): bool
    {
        $referencia = $nota->data_emissao ?? $nota->data_entrada;

        if (! $referencia) {
            return true;
        }

        return $referencia->copy()->startOfDay()->gte(now()->subDays(10)->startOfDay());
    }

    private function isPrazoCienciaExpirado(FiscalEngineException $exception): bool
    {
        if ($exception->sefazCodigo === '596') {
            return true;
        }

        $mensagem = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($mensagem, 'cstat 596')
            || str_contains($mensagem, '[596]')
            || str_contains($mensagem, 'prazo permitido');
    }

    private function consultarXmlPorChave(
        Empresa $empresa,
        Certificate $certificate,
        int $tpAmb,
        string $cUfAutor,
        string $chave,
    ): ?string {
        $response = $this->engine->consultarDistribuicaoDfe(new ConsultarDistribuicaoDfeRequest(
            certificate: $certificate,
            cnpj: (string) $empresa->cnpj,
            cUfAutor: $cUfAutor,
            tpAmb: $tpAmb,
            chave: $chave,
        ));

        foreach ($response->documentos as $documento) {
            if ($documento->chave === $chave && $this->hasItens($documento->xml)) {
                return $documento->xml;
            }
        }

        foreach ($response->documentos as $documento) {
            if ($documento->chave === $chave && filled($documento->xml)) {
                return $documento->xml;
            }
        }

        return $response->documentos[0]->xml ?? null;
    }

    private function buscarProcNfeNoLote(
        Empresa $empresa,
        VendasParametro $parametros,
        Certificate $certificate,
        int $tpAmb,
        string $cUfAutor,
        string $chave,
    ): ?string {
        $ultNsu = DistribuicaoDfeConfig::ultimoNsu($parametros);

        for ($i = 0; $i < 3; $i++) {
            $response = $this->engine->consultarDistribuicaoDfe(new ConsultarDistribuicaoDfeRequest(
                certificate: $certificate,
                cnpj: (string) $empresa->cnpj,
                cUfAutor: $cUfAutor,
                tpAmb: $tpAmb,
                ultNsu: $ultNsu,
            ));

            $ultNsu = $response->ultNsu;
            $parametros->update(['dfe_ultimo_nsu' => $ultNsu]);

            foreach ($response->documentos as $documento) {
                if ($documento->chave === $chave && $this->hasItens($documento->xml)) {
                    return $documento->xml;
                }
            }

            if ($response->statusCodigo === DistribuicaoDfeMensagens::CSTAT_SEM_DOCUMENTOS
                || ! $response->possuiMaisDocumentos()) {
                break;
            }
        }

        return null;
    }

    private function enviarCiencia(
        Empresa $empresa,
        Certificate $certificate,
        int $tpAmb,
        string $chave,
    ): void {
        $cnpj = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ da empresa inválido para Ciência da Operação.');
        }

        $nSeqEvento = '1';
        $eventoId = 'ID'.self::TP_EVENTO_CIENCIA.$chave.str_pad($nSeqEvento, 2, '0', STR_PAD_LEFT);
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        $agora = new \DateTimeImmutable('now', $timezone);
        $idLote = $agora->format('YmdHis').random_int(0, 9);

        $dom = XmlHelper::createDocument('envEvento', '1.00');
        $envEvento = $dom->documentElement;

        if (! $envEvento instanceof \DOMElement) {
            throw new FiscalEngineException('Não foi possível montar o XML de Ciência da Operação.');
        }

        XmlHelper::append($envEvento, 'idLote', $idLote);

        $evento = $dom->createElementNS(XmlHelper::NFE_NS, 'evento');
        $evento->setAttribute('versao', '1.00');
        $envEvento->appendChild($evento);

        $infEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'infEvento');
        $infEvento->setAttribute('Id', $eventoId);
        $evento->appendChild($infEvento);

        // Manifestação do destinatário: órgão 91 (Ambiente Nacional).
        XmlHelper::append($infEvento, 'cOrgao', '91');
        XmlHelper::append($infEvento, 'tpAmb', (string) $tpAmb);
        XmlHelper::append($infEvento, 'CNPJ', $cnpj);
        XmlHelper::append($infEvento, 'chNFe', $chave);
        XmlHelper::append($infEvento, 'dhEvento', $agora->format('Y-m-d\TH:i:sP'));
        XmlHelper::append($infEvento, 'tpEvento', self::TP_EVENTO_CIENCIA);
        XmlHelper::append($infEvento, 'nSeqEvento', $nSeqEvento);
        XmlHelper::append($infEvento, 'verEvento', '1.00');

        $detEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'detEvento');
        $detEvento->setAttribute('versao', '1.00');
        $infEvento->appendChild($detEvento);
        XmlHelper::append($detEvento, 'descEvento', 'Ciencia da Operacao');

        $this->signer->signInfEvento($dom, $certificate);
        $xmlEnvEvento = XmlHelper::compact($dom->saveXML($dom->documentElement) ?: '');

        // Manifestação do destinatário deve ir ao Ambiente Nacional (cOrgao 91).
        $endpoints = [
            $tpAmb === 1
                ? 'https://www.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx'
                : 'https://hom1.nfe.fazenda.gov.br/NFeRecepcaoEvento4/NFeRecepcaoEvento4.asmx',
        ];

        $ultimoErro = null;
        $parsed = ['codigo' => '', 'motivo' => ''];

        foreach ($endpoints as $endpoint) {
            try {
                $soapResponse = $this->soapClient->recepcaoEvento(
                    $endpoint,
                    $xmlEnvEvento,
                    $certificate,
                    '91',
                );
                $parsed = $this->parseRecepcaoEvento($soapResponse);

                if (in_array($parsed['codigo'], self::CODIGOS_CIENCIA_OK, true)) {
                    return;
                }

                $ultimoErro = ($parsed['motivo'] !== '' ? $parsed['motivo'] : 'Ciência rejeitada')
                    .($parsed['codigo'] !== '' ? " [cStat {$parsed['codigo']}]" : '');
            } catch (FiscalEngineException $exception) {
                $ultimoErro = $exception->getMessage();
                $parsed = ['codigo' => '', 'motivo' => ''];
            }
        }

        throw new FiscalEngineException(
            $ultimoErro ?: 'Ciência da Operação rejeitada pela SEFAZ.',
            $parsed['codigo'] !== '' ? $parsed['codigo'] : null,
        );
    }

    /**
     * @return array{codigo: string, motivo: string}
     */
    private function parseRecepcaoEvento(string $soapResponse): array
    {
        if (preg_match('/<(?:\w+:)?retEnvEvento[\s\S]*?<\/(?:\w+:)?retEnvEvento>/', $soapResponse, $m) !== 1
            && preg_match('/<(?:\w+:)?retEvento[\s\S]*?<\/(?:\w+:)?retEvento>/', $soapResponse, $m) !== 1) {
            if (preg_match('/<(?:\w+:)?nfeRecepcaoEventoResult[^>]*>([\s\S]*?)<\/(?:\w+:)?nfeRecepcaoEventoResult>/', $soapResponse, $inner) === 1) {
                $soapResponse = html_entity_decode($inner[1], ENT_QUOTES | ENT_XML1);
            }
        }

        $dom = new DOMDocument();

        if (! @$dom->loadXML($soapResponse) && isset($m[0])) {
            @$dom->loadXML($m[0]);
        }

        $cStat = '';
        $xMotivo = '';

        foreach (['infEvento', 'retEvento', 'retEnvEvento'] as $tag) {
            $node = $dom->getElementsByTagName($tag)->item(0);

            if (! $node instanceof \DOMElement) {
                continue;
            }

            $cStatNode = $node->getElementsByTagName('cStat')->item(0);
            $xMotivoNode = $node->getElementsByTagName('xMotivo')->item(0);

            if ($cStatNode) {
                $cStat = trim((string) $cStatNode->textContent);
            }

            if ($xMotivoNode) {
                $xMotivo = trim((string) $xMotivoNode->textContent);
            }

            if ($cStat !== '') {
                break;
            }
        }

        if ($cStat === '') {
            $cStatNode = $dom->getElementsByTagName('cStat')->item(0);
            $xMotivoNode = $dom->getElementsByTagName('xMotivo')->item(0);
            $cStat = $cStatNode ? trim((string) $cStatNode->textContent) : '';
            $xMotivo = $xMotivoNode ? trim((string) $xMotivoNode->textContent) : '';
        }

        return ['codigo' => $cStat, 'motivo' => $xMotivo];
    }

    private function persistXml(NotaFornecedor $nota, string $xml): NotaFornecedor
    {
        $nota->forceFill(['xml' => $xml])->save();

        return $nota->fresh() ?? $nota;
    }

    private function hasItens(?string $xml): bool
    {
        if ($xml === null || $xml === '') {
            return false;
        }

        return str_contains($xml, '<det') && (str_contains($xml, '<nfeProc') || str_contains($xml, '<infNFe'));
    }
}
