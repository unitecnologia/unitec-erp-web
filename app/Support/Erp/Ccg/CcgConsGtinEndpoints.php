<?php

namespace App\Support\Erp\Ccg;

final class CcgConsGtinEndpoints
{
    public const URL = 'https://dfe-servico.svrs.rs.gov.br/ws/ccgConsGTIN/ccgConsGTIN.asmx';

    public const WSDL_NS = 'http://www.portalfiscal.inf.br/nfe/wsdl/ccgConsGtin';

    public const NFE_NS = 'http://www.portalfiscal.inf.br/nfe';

    public const VERSAO = '1.00';

    public const SOAP_ACTION = 'http://www.portalfiscal.inf.br/nfe/wsdl/ccgConsGtin/ccgConsGTIN';
}
