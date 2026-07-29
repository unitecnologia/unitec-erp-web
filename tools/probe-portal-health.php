<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

$empresa = Empresa::query()->find(1);
$token = $empresa->param_portal_contador_token;
$base = 'https://unitecnologiasc.com.br';

$urls = [
    $base . '/api/portal/documentos',
    $base . '/api/portal/health',
    $base . '/API/PORTAL/DOCUMENTOS',
    $base . '/API/PORTAL/HEALTH',
    $base . '/api/v1/portal/documentos',
    $base . '/api/v1/portal/health',
];

foreach ($urls as $url) {
    echo PHP_EOL . 'GET ' . $url . PHP_EOL;

    try {
        $r = Http::timeout(12)->withToken($token)->acceptJson()->get($url);
        $body = $r->body();
        $ctype = $r->header('Content-Type');
        echo 'status=' . $r->status() . ' type=' . (is_array($ctype) ? ($ctype[0] ?? '-') : $ctype) . PHP_EOL;
        echo 'body=' . substr($body, 0, 150) . PHP_EOL;
    } catch (Throwable $e) {
        echo 'error=' . $e->getMessage() . PHP_EOL;
    }
}
