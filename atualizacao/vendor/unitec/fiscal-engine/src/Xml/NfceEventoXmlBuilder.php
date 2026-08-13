<?php

namespace Unitec\FiscalEngine\Xml;

use DOMDocument;
use DOMElement;
use Unitec\FiscalEngine\Dto\CancelarNfceRequest;
use Unitec\FiscalEngine\Dto\CartaCorrecaoNfeRequest;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\Util\XmlHelper;

final class NfceEventoXmlBuilder
{
    private const TP_EVENTO_CANCELAMENTO = '110111';

    private const TP_EVENTO_CARTA_CORRECAO = '110110';

    private const VER_EVENTO = '1.00';

    /**
     * @return array{dom: DOMDocument, eventoId: string, idLote: string}
     */
    public function buildCancelamento(CancelarNfceRequest $request): array
    {
        $chave = preg_replace('/\D/', '', $request->chave) ?? '';

        if (strlen($chave) !== 44) {
            throw new FiscalEngineException('Chave de acesso inválida para cancelamento da NFC-e.');
        }

        $justificativa = XmlHelper::sanitizeText($request->justificativa, 255);

        if (mb_strlen($justificativa, 'UTF-8') < 15) {
            throw new FiscalEngineException('Justificativa do cancelamento deve ter no mínimo 15 caracteres.');
        }

        $protocolo = preg_replace('/\D/', '', $request->protocoloAutorizacao) ?? '';

        if ($protocolo === '') {
            throw new FiscalEngineException('Protocolo de autorização não informado para cancelamento.');
        }

        $cnpj = preg_replace('/\D/', '', $request->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ do emitente inválido para cancelamento.');
        }

        $cOrgao = substr($chave, 0, 2);
        $nSeqEvento = '1';
        $eventoId = 'ID' . self::TP_EVENTO_CANCELAMENTO . $chave . '0' . $nSeqEvento;
        $idLote = $this->gerarIdLote($request->dataEvento);

        $dom = XmlHelper::createDocument('envEvento', self::VER_EVENTO);
        $envEvento = $dom->documentElement;

        if (! $envEvento instanceof DOMElement) {
            throw new FiscalEngineException('Não foi possível montar o XML de cancelamento.');
        }

        XmlHelper::append($envEvento, 'idLote', $idLote);

        $evento = $dom->createElementNS(XmlHelper::NFE_NS, 'evento');
        $evento->setAttribute('versao', self::VER_EVENTO);
        $envEvento->appendChild($evento);

        $infEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'infEvento');
        $infEvento->setAttribute('Id', $eventoId);
        $evento->appendChild($infEvento);

        XmlHelper::append($infEvento, 'cOrgao', $cOrgao);
        XmlHelper::append($infEvento, 'tpAmb', (string) $request->tpAmb);
        XmlHelper::append($infEvento, 'CNPJ', $cnpj);
        XmlHelper::append($infEvento, 'chNFe', $chave);
        XmlHelper::append($infEvento, 'dhEvento', $this->formatDhEvento($request->dataEvento));
        XmlHelper::append($infEvento, 'tpEvento', self::TP_EVENTO_CANCELAMENTO);
        XmlHelper::append($infEvento, 'nSeqEvento', $nSeqEvento);
        XmlHelper::append($infEvento, 'verEvento', self::VER_EVENTO);

        $detEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'detEvento');
        $detEvento->setAttribute('versao', self::VER_EVENTO);
        $infEvento->appendChild($detEvento);

        XmlHelper::append($detEvento, 'descEvento', 'Cancelamento');
        XmlHelper::append($detEvento, 'nProt', $protocolo);
        XmlHelper::append($detEvento, 'xJust', $justificativa);

        return [
            'dom' => $dom,
            'eventoId' => $eventoId,
            'idLote' => $idLote,
        ];
    }

    /**
     * @return array{dom: DOMDocument, eventoId: string, idLote: string}
     */
    public function buildCartaCorrecao(CartaCorrecaoNfeRequest $request): array
    {
        $chave = preg_replace('/\D/', '', $request->chave) ?? '';

        if (strlen($chave) !== 44) {
            throw new FiscalEngineException('Chave de acesso inválida para Carta de Correção da NF-e.');
        }

        $correcao = XmlHelper::sanitizeText($request->correcao, 1000);

        if (mb_strlen($correcao, 'UTF-8') < 15) {
            throw new FiscalEngineException('Texto da correção deve ter no mínimo 15 caracteres.');
        }

        $cnpj = preg_replace('/\D/', '', $request->cnpj) ?? '';

        if (strlen($cnpj) !== 14) {
            throw new FiscalEngineException('CNPJ do emitente inválido para Carta de Correção.');
        }

        if ($request->nSeqEvento < 1 || $request->nSeqEvento > 20) {
            throw new FiscalEngineException('Sequência da Carta de Correção deve estar entre 1 e 20.');
        }

        $cOrgao = substr($chave, 0, 2);
        $nSeqEvento = (string) $request->nSeqEvento;
        $eventoId = 'ID' . self::TP_EVENTO_CARTA_CORRECAO . $chave . str_pad($nSeqEvento, 2, '0', STR_PAD_LEFT);
        $idLote = $this->gerarIdLote($request->dataEvento);

        $dom = XmlHelper::createDocument('envEvento', self::VER_EVENTO);
        $envEvento = $dom->documentElement;

        if (! $envEvento instanceof DOMElement) {
            throw new FiscalEngineException('Não foi possível montar o XML da Carta de Correção.');
        }

        XmlHelper::append($envEvento, 'idLote', $idLote);

        $evento = $dom->createElementNS(XmlHelper::NFE_NS, 'evento');
        $evento->setAttribute('versao', self::VER_EVENTO);
        $envEvento->appendChild($evento);

        $infEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'infEvento');
        $infEvento->setAttribute('Id', $eventoId);
        $evento->appendChild($infEvento);

        XmlHelper::append($infEvento, 'cOrgao', $cOrgao);
        XmlHelper::append($infEvento, 'tpAmb', (string) $request->tpAmb);
        XmlHelper::append($infEvento, 'CNPJ', $cnpj);
        XmlHelper::append($infEvento, 'chNFe', $chave);
        XmlHelper::append($infEvento, 'dhEvento', $this->formatDhEvento($request->dataEvento));
        XmlHelper::append($infEvento, 'tpEvento', self::TP_EVENTO_CARTA_CORRECAO);
        XmlHelper::append($infEvento, 'nSeqEvento', $nSeqEvento);
        XmlHelper::append($infEvento, 'verEvento', self::VER_EVENTO);

        $detEvento = $dom->createElementNS(XmlHelper::NFE_NS, 'detEvento');
        $detEvento->setAttribute('versao', self::VER_EVENTO);
        $infEvento->appendChild($detEvento);

        XmlHelper::append($detEvento, 'descEvento', NfeCartaCorrecaoLiterals::DESC_EVENTO);
        XmlHelper::append($detEvento, 'xCorrecao', $correcao);
        XmlHelper::append($detEvento, 'xCondUso', NfeCartaCorrecaoLiterals::X_COND_USO);

        return [
            'dom' => $dom,
            'eventoId' => $eventoId,
            'idLote' => $idLote,
        ];
    }

    public function finalizeEnvEvento(DOMDocument $dom): string
    {
        $xml = $dom->saveXML($dom->documentElement) ?: '';

        return XmlHelper::compact($xml);
    }

    private function gerarIdLote(?\DateTimeInterface $dataEvento): string
    {
        $data = $dataEvento ?? new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo'));

        return $data->format('YmdHis') . random_int(0, 9);
    }

    private function formatDhEvento(?\DateTimeInterface $dataEvento): string
    {
        $timezone = new \DateTimeZone('America/Sao_Paulo');
        $data = $dataEvento
            ? \DateTimeImmutable::createFromInterface($dataEvento)->setTimezone($timezone)
            : new \DateTimeImmutable('now', $timezone);

        return $data->format('Y-m-d\TH:i:sP');
    }
}
