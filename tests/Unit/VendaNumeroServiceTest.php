<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\VendaNumeroService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VendaNumeroServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_proximo_numero_e_sequencial_e_formatado(): void
    {
        DB::table('venda_numero_sequencias')->updateOrInsert(
            ['chave' => 'global'],
            ['ultimo_numero' => 150, 'created_at' => now(), 'updated_at' => now()],
        );

        $service = new VendaNumeroService();

        $this->assertSame('000151', $service->proximo());
        $this->assertSame('000152', $service->proximo());
    }

    public function test_proximo_numero_respeita_maximo_existente_na_vendas(): void
    {
        DB::table('venda_numero_sequencias')->where('chave', 'global')->delete();

        $cliente = Person::query()->create([
            'codigo' => '9002',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE SEQUENCIA TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        Venda::query()->create([
            'numero' => '000099',
            'data' => now()->toDateString(),
            'cliente_id' => $cliente->id,
            'total' => 10,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $service = new VendaNumeroService();

        $this->assertSame('000100', $service->proximo());
    }
}
