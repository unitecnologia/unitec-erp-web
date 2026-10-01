<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

class ForbidDestructiveDatabaseResetTest extends TestCase
{
    public function test_migrate_fresh_em_mysql_falha_antes_de_executar(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'unitec_erp',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('migrate:fresh');

        $this->artisan('migrate:fresh');
    }

    public function test_db_wipe_em_mysql_falha_antes_de_executar(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'unitec_erp',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('db:wipe');

        $this->artisan('db:wipe');
    }
}
