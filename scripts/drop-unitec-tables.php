<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$prefix = Illuminate\Support\Facades\DB::connection()->getTablePrefix();
$rows = Illuminate\Support\Facades\DB::select("SHOW TABLES LIKE '{$prefix}%'");
$tables = array_map(fn ($row) => array_values((array) $row)[0], $rows);

if ($tables === []) {
    echo "Nenhuma tabela {$prefix}* encontrada.\n";
    exit(0);
}

Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

foreach ($tables as $table) {
    Illuminate\Support\Facades\DB::statement('DROP TABLE `' . str_replace('`', '``', $table) . '`');
    echo "Dropped {$table}\n";
}

Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

echo "OK\n";
