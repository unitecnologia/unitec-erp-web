<?php

/**
 * Wrapper CLI do migrate tolerante (Aplicar Migrations.bat).
 * Preferivel apos update: php artisan unitec:migrate-tolerante
 */

declare(strict_types=1);

use App\Support\Erp\Database\TolerantMigrateRunner;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$result = $app->make(TolerantMigrateRunner::class)->run($root);

exit($result['ok'] ? 0 : 1);
