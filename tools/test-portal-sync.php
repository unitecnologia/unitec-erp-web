<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVendaNfce;
use App\Support\ContadorCloud\ContadorCloudDocumentPayloadBuilder;
use App\Support\ContadorCloud\ContadorCloudPortalHookService;
use Illuminate\Support\Facades\Http;

$empresa = Empresa::query()->find(1);
$config = \App\Support\ContadorCloud\ContadorCloudConfig::fromEmpresa($empresa);

echo 'health: ' . $config->healthUrl() . PHP_EOL;
echo 'sync: ' . $config->syncUrl() . PHP_EOL;

try {
    $health = Http::timeout(15)->withToken($config->token)->get($config->healthUrl());
    echo 'health status=' . $health->status() . ' body=' . substr($health->body(), 0, 200) . PHP_EOL;
} catch (Throwable $e) {
    echo 'health error: ' . $e->getMessage() . PHP_EOL;
}

$nfe = Nfe::query()->orderByDesc('id')->first();
$nfce = PdvVendaNfce::query()->orderByDesc('id')->first();

if ($nfe && $empresa) {
    echo PHP_EOL . 'Reenviando NF-e #' . $nfe->id . ' chave=' . $nfe->chave . PHP_EOL;
    (new ContadorCloudPortalHookService())->onNfeAutorizada($nfe, $empresa);
}

if ($nfce && $empresa) {
    echo PHP_EOL . 'Reenviando NFC-e #' . $nfce->id . ' chave=' . $nfce->chave . PHP_EOL;
    (new ContadorCloudPortalHookService())->onNfceAutorizada($nfce, $empresa);
}

$last = \App\Models\ContadorCloudSyncLog::query()->orderByDesc('id')->first();
if ($last) {
    echo PHP_EOL . 'Ultimo log: status=' . $last->status . ' http=' . $last->http_status . PHP_EOL;
    echo 'response: ' . substr((string) $last->response_body, 0, 400) . PHP_EOL;
}
