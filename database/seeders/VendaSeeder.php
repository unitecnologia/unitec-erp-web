<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\Venda;
use Illuminate\Database\Seeder;

class VendaSeeder extends Seeder
{
    public function run(): void
    {
        $clientes = Person::query()->where('is_cliente', true)->get()->keyBy('codigo');
        $vendedores = Person::query()->where('is_funcionario', true)->get()->keyBy('codigo');

        $clientePadrao = $clientes->first();
        $vendedorPadrao = $vendedores->first();

        if (! $clientePadrao) {
            return;
        }

        $samples = [
            ['numero' => '000101', 'data' => now()->subDays(6), 'hora' => '09:15:00', 'cliente' => '4', 'vendedor' => '13', 'total' => 1580.00, 'status' => Venda::STATUS_ABERTO, 'tipo' => Venda::TIPO_PEDIDO],
            ['numero' => '000102', 'data' => now()->subDays(4), 'hora' => '10:42:00', 'cliente' => '30', 'vendedor' => '13', 'total' => 320.50, 'status' => Venda::STATUS_GRAVADO, 'tipo' => Venda::TIPO_PEDIDO],
            ['numero' => '000103', 'data' => now()->subDays(3), 'hora' => '14:08:00', 'cliente' => '33', 'vendedor' => '32', 'total' => 2890.75, 'status' => Venda::STATUS_FECHADO, 'tipo' => Venda::TIPO_PEDIDO],
            ['numero' => '000104', 'data' => now()->subDay(), 'hora' => '11:30:00', 'cliente' => '11', 'vendedor' => '32', 'total' => 45.90, 'status' => Venda::STATUS_FECHADO, 'tipo' => Venda::TIPO_CUPOM],
            ['numero' => '000105', 'data' => now(), 'hora' => '16:55:00', 'cliente' => '4', 'vendedor' => '13', 'total' => 760.00, 'status' => Venda::STATUS_CANCELADO, 'tipo' => Venda::TIPO_PEDIDO],
            ['numero' => '000106', 'data' => now(), 'hora' => '08:20:00', 'cliente' => '30', 'vendedor' => '13', 'total' => 128.00, 'status' => Venda::STATUS_ABERTO, 'tipo' => Venda::TIPO_CUPOM],
        ];

        foreach ($samples as $sample) {
            $cliente = $clientes->get($sample['cliente']) ?? $clientePadrao;
            $vendedor = $vendedores->get($sample['vendedor'] ?? '') ?? $vendedorPadrao;

            Venda::query()->updateOrCreate(
                ['numero' => $sample['numero']],
                [
                    'data' => $sample['data'],
                    'hora' => $sample['hora'],
                    'cliente_id' => $cliente->id,
                    'vendedor_id' => $vendedor?->id,
                    'total' => $sample['total'],
                    'status' => $sample['status'],
                    'tipo' => $sample['tipo'],
                ],
            );
        }
    }
}
