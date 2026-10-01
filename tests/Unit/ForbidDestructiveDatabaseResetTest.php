<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tests\Support\ForbidDestructiveDatabaseReset;

class ForbidDestructiveDatabaseResetTest extends TestCase
{
    public function test_bloqueia_drop_e_truncate_no_mysql_de_desenvolvimento(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não podem limpar o banco de desenvolvimento');

        ForbidDestructiveDatabaseReset::assertSqlAllowed(
            'drop table `unitec_ordens_servico`',
            'mysql',
            'unitec_erp',
        );
    }

    public function test_permite_drop_apenas_em_sqlite_memory(): void
    {
        ForbidDestructiveDatabaseReset::assertSqlAllowed(
            'drop table users',
            'sqlite',
            ':memory:',
        );

        $this->assertTrue(ForbidDestructiveDatabaseReset::isDisposableSqlite('sqlite', ':memory:'));
        $this->assertFalse(ForbidDestructiveDatabaseReset::isDisposableSqlite('mysql', 'unitec_erp'));
        $this->assertFalse(ForbidDestructiveDatabaseReset::isDisposableSqlite('mariadb', ':memory:'));
        $this->assertFalse(ForbidDestructiveDatabaseReset::isDisposableSqlite('sqlite', 'unitec_erp'));
    }
}
