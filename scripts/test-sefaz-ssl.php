<?php

$url = 'https://nfce-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx';

function probe(string $label, array $opts): void
{
    $ch = curl_init($GLOBALS['url']);
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    curl_exec($ch);
    $err = curl_error($ch) ?: 'none';
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "{$label}: HTTP {$code} | {$err}\n";
}

probe('no-verify', [CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0]);

$ca = __DIR__ . '/../packages/unitec-fiscal-engine/resources/cacert.pem';
probe('cainfo-path', [
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CAINFO => $ca,
]);

if (defined('CURLOPT_CAINFO_BLOB')) {
    probe('cainfo-blob', [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO_BLOB => (string) file_get_contents($ca),
    ]);
}

if (defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
    probe('native-ca', [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
    ]);
}

if (defined('CURLOPT_SSL_OPTIONS') && defined('CURLSSLOPT_NATIVE_CA')) {
    probe('native+ca-blob', [
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
        CURLOPT_CAINFO_BLOB => (string) file_get_contents($ca),
    ]);
}
