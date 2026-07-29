<?php

require __DIR__ . '/../vendor/autoload.php';

$url = 'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx';
$consStatServ = '<?xml version="1.0" encoding="UTF-8"?>'
    . '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
    . '<tpAmb>2</tpAmb><cUF>42</cUF><xServ>STATUS</xServ></consStatServ>';

$soapAction = 'http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4/nfeStatusServicoNF';

$variants = [
    'soap12' => [
        'headers' => [
            'Content-Type: application/soap+xml; charset=utf-8; action="' . $soapAction . '"',
        ],
        'body' => '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" '
            . 'xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Header>'
            . '<nfeCabecMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">'
            . '<cUF>42</cUF><versaoDados>4.00</versaoDados>'
            . '</nfeCabecMsg></soap12:Header>'
            . '<soap12:Body>'
            . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">'
            . $consStatServ
            . '</nfeDadosMsg></soap12:Body></soap12:Envelope>',
    ],
    'soap11' => [
        'headers' => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $soapAction . '"',
        ],
        'body' => '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" '
            . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
            . 'xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
            . '<soap:Body>'
            . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">'
            . $consStatServ
            . '</nfeDadosMsg></soap:Body></soap:Envelope>',
    ],
    'soap12-no-xml-decl-inner' => [
        'headers' => [
            'Content-Type: application/soap+xml; charset=utf-8; action="' . $soapAction . '"',
        ],
        'body' => '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap12:Envelope xmlns:soap12="http://www.w3.org/2003/05/soap-envelope">'
            . '<soap12:Body>'
            . '<nfeDadosMsg xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NFeStatusServico4">'
            . '<consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">'
            . '<tpAmb>2</tpAmb><cUF>42</cUF><xServ>STATUS</xServ></consStatServ>'
            . '</nfeDadosMsg></soap12:Body></soap12:Envelope>',
    ],
];

foreach ($variants as $name => $variant) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $variant['body'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $variant['headers'],
        CURLOPT_TIMEOUT => 20,
    ] + Unitec\FiscalEngine\Util\SslTransportOptions::curlOptions());

    $response = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    echo "=== {$name} HTTP {$code} ===\n";
    if ($err) {
        echo "curl: {$err}\n";
    }
    echo substr((string) $response, 0, 400) . "\n\n";
}
