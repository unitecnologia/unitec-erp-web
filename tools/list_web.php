<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables = Illuminate\Support\Facades\Schema::getTableListing();
sort($tables);
file_put_contents(sys_get_temp_dir().'/web_tables.txt', implode(PHP_EOL, $tables));
echo 'web_tables='.count($tables).PHP_EOL;
$models = glob(__DIR__.'/../app/Models/*.php');
echo 'models='.count($models).PHP_EOL;
foreach ($models as $m) echo basename($m, '.php').PHP_EOL;
