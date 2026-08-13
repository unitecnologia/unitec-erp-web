<?php

namespace Unitec\FiscalEngine\Nfe;

/**
 * Webservice nacional de Distribuição de DF-e (Ambiente Nacional).
 *
 * @see https://www.nfe.fazenda.gov.br/portal/principal.aspx
 */
final class AnDistribuicaoDfeEndpoints
{
    public static function distribuicao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://www1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx'
            : 'https://hom1.nfe.fazenda.gov.br/NFeDistribuicaoDFe/NFeDistribuicaoDFe.asmx';
    }

    public static function soapAction(): string
    {
        return 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeDistribuicaoDFe/nfeDistDFeInteresse';
    }
}
