<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Dados de demonstração (empresa, produtos, vendas…).
 * Uso: php artisan db:seed --class=DemoDatabaseSeeder
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        UnitecInitialSeeder::seedDemoEmpresa();

        $this->call([
            ProductAuxiliarySeeder::class,
            NcmSeeder::class,
            ProductSeeder::class,
            PersonSeeder::class,
            VendedorSeeder::class,
            EntregadorSeeder::class,
            ContadorSeeder::class,
            OrcamentoSeeder::class,
            VendaSeeder::class,
            CaixaSeeder::class,
            CompraSeeder::class,
            NotaFornecedorSeeder::class,
            NfeSeeder::class,
            ContaReceberSeeder::class,
            ContaPagarSeeder::class,
            AjusteEstoqueSeeder::class,
            ProductSerialSeeder::class,
            ProductCardexItemSeeder::class,
        ]);
    }
}
