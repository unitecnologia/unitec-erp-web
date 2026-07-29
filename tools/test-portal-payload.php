<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use App\Models\Nfe;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\ContadorCloud\ContadorCloudDocumentPayloadBuilder;

$empresa = Empresa::query()->find(1);
$config = ContadorCloudConfig::fromEmpresa($empresa);
$builder = new ContadorCloudDocumentPayloadBuilder();

echo 'syncUrl=' . $config->syncUrl() . PHP_EOL;

$nfe = Nfe::query()->orderByDesc('id')->first();
if ($nfe && $empresa) {
    $documento = $builder->fromNfe($nfe, $empresa, ContadorCloudDocumentPayloadBuilder::EVENTO_AUTORIZADO);
    $payload = $builder->buildEnvelope($config, $documento);
    echo PHP_EOL . 'Payload portal (NF-e):' . PHP_EOL;
    echo json_encode(array_merge($payload, [
        'xmlContent' => isset($payload['xmlContent']) ? '[XML ' . strlen((string) $payload['xmlContent']) . ' bytes]' : null,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
