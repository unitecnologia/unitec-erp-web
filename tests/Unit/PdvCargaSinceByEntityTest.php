<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\Person;
use App\Models\Product;
use App\Support\Pdv\PdvCargaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PdvCargaSinceByEntityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_build_pull_respeita_since_customers_distinto_de_products(): void
    {
        $empresa = Empresa::query()->create([
            'nome' => 'EMPRESA SINCE '.random_int(1000, 9999),
            'ativo' => true,
        ]);

        $person = Person::query()->create([
            'codigo' => 'C-'.random_int(100, 999),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE DELTA TESTE',
            'is_cliente' => true,
            'ativo' => true,
            'updated_at' => Carbon::parse('2026-08-20T10:00:00'),
        ]);

        Product::query()->create([
            'codigo' => 'P-'.random_int(100, 999),
            'descricao' => 'PROD DELTA TESTE',
            'preco_venda' => 1,
            'ativo' => true,
            'updated_at' => Carbon::parse('2026-08-26T12:00:00'),
        ]);

        $sinceProducts = Carbon::parse('2026-08-26T11:00:00');
        $sinceCustomers = Carbon::parse('2026-08-01T00:00:00');

        $payload = app(PdvCargaService::class)->buildPull(
            $sinceProducts,
            (int) $empresa->id,
            null,
            0,
            50,
            [
                'products' => $sinceProducts,
                'customers' => $sinceCustomers,
                'formas_pagamento' => $sinceProducts,
                'users' => $sinceProducts,
            ],
        );

        $customerIds = collect($payload['customers'] ?? [])->pluck('id')->all();

        $this->assertContains((int) $person->id, $customerIds);
        $this->assertSame([], $payload['products']);
    }
}
