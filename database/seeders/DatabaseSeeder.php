<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Instalação padrão: usuário USUARIO + perfis + tabelas fiscais oficiais.
     * Sem empresa — o primeiro login abre o cadastro de empresa.
     *
     * Demo completo: php artisan db:seed --class=DemoDatabaseSeeder
     */
    public function run(): void
    {
        $this->call([
            UnitecInitialSeeder::class,
        ]);
    }
}
