<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            UnitecInitialSeeder::class,
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
            NfeSeeder::class,
            ContaReceberSeeder::class,
            ContaPagarSeeder::class,
            AjusteEstoqueSeeder::class,
            ProductSerialSeeder::class,
            ProductCardexItemSeeder::class,
        ]);
    }
}
