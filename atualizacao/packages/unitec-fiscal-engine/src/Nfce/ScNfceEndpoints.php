<?php

namespace Unitec\FiscalEngine\Nfce;

/**
 * Endpoints oficiais da NFC-e em Santa Catarina.
 *
 * A autorização é processada pela SVRS (Sefaz Virtual RS).
 * QR Code e consulta pública usam o portal SAT/SEF-SC.
 *
 * @see https://www.sef.sc.gov.br/api-portal/Documento/ver/1398
 */
final class ScNfceEndpoints
{
    public static function autorizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.svrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx'
            : 'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeAutorizacao/NFeAutorizacao4.asmx';
    }

    public static function consultaQrCode(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://sat.sef.sc.gov.br/nfce/consulta'
            : 'https://hom.sat.sef.sc.gov.br/nfce/consulta';
    }

    public static function recepcaoEvento(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx'
            : 'https://nfce-homologacao.svrs.rs.gov.br/ws/recepcaoevento/recepcaoevento4.asmx';
    }

    public static function consultaProtocolo(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NFeConsultaProtocolo4.asmx'
            : 'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeConsultaProtocolo/NFeConsultaProtocolo4.asmx';
    }

    public static function inutilizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfce.svrs.rs.gov.br/ws/NfeInutilizacao/NFeInutilizacao4.asmx'
            : 'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeInutilizacao/NFeInutilizacao4.asmx';
    }

    public static function soapActionAutorizacao(): string
    {
        return 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeAutorizacao4/nfeAutorizacaoLote';
    }

    public static function soapActionRecepcaoEvento(): string
    {
        return 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeRecepcaoEvento4/nfeRecepcaoEvento';
    }

    public static function soapActionConsultaProtocolo(): string
    {
        return 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeConsultaProtocolo4/nfeConsultaNF';
    }

    public static function soapActionInutilizacao(): string
    {
        return 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeInutilizacao4/nfeInutilizacaoNF';
    }
}
