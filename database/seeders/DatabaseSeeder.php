<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Instalação padrão: usuário USUARIO + perfis + tabelas fiscais oficiais
     * + empresa Unitec pré-preenchida (snapshot em database/data/instalador).
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
