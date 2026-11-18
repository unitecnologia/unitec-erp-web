<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Fallback de instalação: usuário USUARIO + perfis + tabelas fiscais oficiais.
     * A base oficial do instalador vem do dump (unitec-erp-padrao.sql); o snapshot
     * de empresa só é aplicado se existir e com FKs de plano saneadas.
     *
     * Demo completo (produtos/vendas): php artisan db:seed --class=DemoDatabaseSeeder
     */
    public function run(): void
    {
        $this->call([
            UnitecInitialSeeder::class,
        ]);
    }
}
