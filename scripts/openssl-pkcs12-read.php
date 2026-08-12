<?php

/**
 * Lê PKCS#12 em processo isolado com OPENSSL_CONF / OPENSSL_MODULES já definidos.
 * Uso: php openssl-pkcs12-read.php input.pfx output.json
 * Senha: env UNITEC_PFX_PASSWORD
 */

declare(strict_types=1);

$pfxPath = $argv[1] ?? '';
$outPath = $argv[2] ?? '';
$password = (string) (getenv('UNITEC_PFX_PASSWORD') ?: '');

$fail = static function (string $message) use ($outPath): void {
    $payload = json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    if (is_string($outPath) && $outPath !== '') {
        @file_put_contents($outPath, $payload);
    }
    fwrite(STDERR, $message.PHP_EOL);
    exit(2);
};

if ($pfxPath === '' || ! is_file($pfxPath)) {
    $fail('Arquivo .pfx ausente.');
}

if ($outPath === '') {
    $fail('Caminho de saída JSON ausente.');
}

$content = @file_get_contents($pfxPath);
if ($content === false || $content === '') {
    $fail('Não foi possível ler o .pfx.');
}

$certs = [];
while (openssl_error_string() !== false) {
}

if (! @openssl_pkcs12_read($content, $certs, $password)) {
    $err = '';
    while (($e = openssl_error_string()) !== false) {
        $err = $e;
    }
    $fail($err !== '' ? $err : 'Senha inválida ou .pfx inválido.');
}

$payload = [
    'ok' => true,
    'cert' => (string) ($certs['cert'] ?? ''),
    'pkey' => (string) ($certs['pkey'] ?? ''),
    'extracerts' => $certs['extracerts'] ?? null,
];

if (@file_put_contents($outPath, json_encode($payload, JSON_UNESCAPED_UNICODE)) === false) {
    $fail('Falha ao gravar JSON de saída.');
}

exit(0);
