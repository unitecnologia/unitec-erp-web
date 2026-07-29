<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Empresa;
use App\Support\ContadorCloud\ContadorCloudClient;
use App\Support\ContadorCloud\ContadorCloudConfig;

$empresa = Empresa::query()->find(1);
$result = (new ContadorCloudClient())->testConnection(ContadorCloudConfig::fromEmpresa($empresa));

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
