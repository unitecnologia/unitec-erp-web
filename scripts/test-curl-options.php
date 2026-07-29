<?php

require __DIR__ . '/../vendor/autoload.php';

$opts = [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
] + Unitec\FiscalEngine\Util\SslTransportOptions::curlOptions();

$ch = curl_init('https://nfce-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx');

try {
    curl_setopt_array($ch, $opts);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_exec($ch);
    echo curl_error($ch) ?: 'OK HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE);
} catch (ValueError $e) {
    echo 'ValueError: ' . $e->getMessage();
}
