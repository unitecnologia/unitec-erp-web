<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use Illuminate\Support\Facades\Http;

$empresa = Empresa::query()->find(1);
$token = $empresa->param_portal_contador_token;
$base = rtrim((string) $empresa->param_portal_contador_url, '/');

$urls = [
    $base,
    $base . '/api/v1/health',
    $base . '/api/v1/sync/documentos',
    preg_replace('#/API/PORTAL/DOCUMENTOS$#i', '/api/v1/sync/documentos', $base),
    preg_replace('#/api/portal/documentos$#i', '/api/v1/sync/documentos', strtolower($base)),
    'https://unitecnologiasc.com.br/api/v1/health',
    'https://unitecnologiasc.com.br/api/v1/sync/documentos',
];

foreach ($urls as $url) {
    if (! $url) {
        continue;
    }

    echo PHP_EOL . 'GET ' . $url . PHP_EOL;

    try {
        $r = Http::timeout(12)->withToken($token)->acceptJson()->get($url);
        $body = $r->body();
        $ctype = $r->header('Content-Type');
        echo 'status=' . $r->status() . ' type=' . ($ctype[0] ?? '-') . PHP_EOL;
        echo 'body=' . substr($body, 0, 120) . PHP_EOL;
    } catch (Throwable $e) {
        echo 'error=' . $e->getMessage() . PHP_EOL;
    }
}
