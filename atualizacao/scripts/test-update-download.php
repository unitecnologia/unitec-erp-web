<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = trim((string) config('unitec.update_download_url', ''));

if ($url === '') {
    $url = rtrim((string) config('unitec.pagamento_url'), '/')
        .'/updates/'
        .urlencode((string) config('unitec.update_zip_name', 'Unitec-ERP-Update.zip'));
}

echo 'URL configurada: '.$url.PHP_EOL;

$destination = storage_path('app/private/test-update-download.zip');
$dir = dirname($destination);
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

if (is_file($destination)) {
    unlink($destination);
}

echo 'Baixando (pode demorar alguns minutos)...'.PHP_EOL;

$started = microtime(true);

try {
    $verify = base_path('vendor/guzzlehttp/guzzle/src/cacert.pem');
    if (! is_file($verify)) {
        $verify = true;
    }

    $response = Illuminate\Support\Facades\Http::timeout(900)
        ->withOptions([
            'sink' => $destination,
            'verify' => $verify,
        ])
        ->get($url);
} catch (Throwable $exception) {
    echo 'ERRO: '.$exception->getMessage().PHP_EOL;
    exit(1);
}

$elapsed = round(microtime(true) - $started, 1);

if (! $response->successful()) {
    echo 'ERRO HTTP: '.$response->status().PHP_EOL;
    exit(1);
}

if (! is_file($destination)) {
    echo 'ERRO: arquivo nao foi gravado.'.PHP_EOL;
    exit(1);
}

$size = filesize($destination);
$magic = (string) file_get_contents($destination, false, null, 0, 4);

echo 'HTTP: '.$response->status().PHP_EOL;
echo 'Tempo: '.$elapsed.' s'.PHP_EOL;
echo 'Tamanho: '.number_format($size / 1024 / 1024, 2, ',', '.').' MB'.PHP_EOL;

if ($magic !== "PK\x03\x04") {
    echo 'ERRO: conteudo nao parece ZIP (magic: '.bin2hex($magic).')'.PHP_EOL;
    exit(1);
}

$zip = new ZipArchive;
$opened = $zip->open($destination);

if ($opened !== true) {
    echo 'ERRO: ZipArchive nao abriu o arquivo.'.PHP_EOL;
    exit(1);
}

$hasArtisan = false;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    if (str_ends_with($name, 'artisan') || $name === 'artisan') {
        $hasArtisan = true;
        break;
    }
}

$zip->close();
unlink($destination);

if (! $hasArtisan) {
    echo 'ERRO: ZIP sem artisan.'.PHP_EOL;
    exit(1);
}

echo 'OK: download direto valido e ZIP contem artisan.'.PHP_EOL;
exit(0);
