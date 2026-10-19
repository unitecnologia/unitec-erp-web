<?php

namespace Unitec\FiscalEngine\Nfce;

/**
 * Endereços oficiais da NFC-e no Paraná.
 *
 * Consulta pública (QR / urlChave): mesma URL em produção e homologação.
 * Web services 4.00: SEFAZ-PR própria (não SVRS).
 *
 * @see https://sped.fazenda.pr.gov.br/NFCe/Pagina/QR-Code
 * @see https://sped.fazenda.pr.gov.br/NFCe/Pagina/Web-Services-NFC-e
 */
final class PrNfceEndpoints
{
    /**
     * Base da URL do QR Code (ZX02). Sem "?p=" — o builder acrescenta.
     */
    public static function consultaQrCode(int $tpAmb): string
    {
        unset($tpAmb);

        return 'http://www.fazenda.pr.gov.br/nfce/qrcode';
    }

    /**
     * URL de consulta por chave de acesso impressa no DANFE (ZX03 urlChave).
     */
    public static function urlChave(int $tpAmb): string
    {
        unset($tpAmb);

        return 'http://www.fazenda.pr.gov.br/nfce/consulta';
    }

    public static function autorizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.sefa.pr.gov.br/nfce/NFeAutorizacao4'
            : 'https://homologacao.nfce.sefa.pr.gov.br/nfce/NFeAutorizacao4';
    }

    public static function consultaProtocolo(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.sefa.pr.gov.br/nfce/NFeConsultaProtocolo4'
            : 'https://homologacao.nfce.sefa.pr.gov.br/nfce/NFeConsultaProtocolo4';
    }

    public static function recepcaoEvento(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.sefa.pr.gov.br/nfce/NFeRecepcaoEvento4'
            : 'https://homologacao.nfce.sefa.pr.gov.br/nfce/NFeRecepcaoEvento4';
    }

    public static function inutilizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.sefa.pr.gov.br/nfce/NFeInutilizacao4'
            : 'https://homologacao.nfce.sefa.pr.gov.br/nfce/NFeInutilizacao4';
    }
}
