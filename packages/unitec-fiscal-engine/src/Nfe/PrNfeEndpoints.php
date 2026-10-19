<?php

namespace Unitec\FiscalEngine\Nfe;

/**
 * Endereços oficiais da NF-e (modelo 55) no Paraná — SEFAZ-PR própria (não SVRS).
 *
 * @see https://sped.fazenda.pr.gov.br/NFe/Pagina/Enderecos-dos-ambientes-de-homologacao-e-producao-Versao-400
 */
final class PrNfeEndpoints
{
    public static function autorizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfe.sefa.pr.gov.br/nfe/NFeAutorizacao4'
            : 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeAutorizacao4';
    }

    public static function consultaProtocolo(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfe.sefa.pr.gov.br/nfe/NFeConsultaProtocolo4'
            : 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeConsultaProtocolo4';
    }

    public static function recepcaoEvento(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfe.sefa.pr.gov.br/nfe/NFeRecepcaoEvento4'
            : 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeRecepcaoEvento4';
    }

    public static function inutilizacao(int $tpAmb): string
    {
        return $tpAmb === 1
            ? 'https://nfe.sefa.pr.gov.br/nfe/NFeInutilizacao4'
            : 'https://homologacao.nfe.sefa.pr.gov.br/nfe/NFeInutilizacao4';
    }
}
