<?php

namespace Tests\Unit;

use App\Models\ForcaVendasOrder;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Reports\ComissaoVendedoresReport;
use App\Support\ForcaVendas\ForcaVendasComissaoService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class ForcaVendasComissaoServiceTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_is_a_prazo_segue_regra_do_relatorio_erp(): void
    {
        $this->assertTrue(ComissaoVendedoresReport::isAPrazo('BOLETO'));
        $this->assertTrue(ComissaoVendedoresReport::isAPrazo('CREDIARIO'));
        $this->assertTrue(ComissaoVendedoresReport::isAPrazo('POS CREDITO'));
        $this->assertFalse(ComissaoVendedoresReport::isAPrazo('DINHEIRO'));
        $this->assertFalse(ComissaoVendedoresReport::isAPrazo('PIX'));
        $this->assertFalse(ComissaoVendedoresReport::isAPrazo(null));
    }

    public function test_build_usa_somente_vendas_fechadas_como_relatorio_erp(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'T1',
            'nome' => 'Vendedor Teste',
            'ativo' => true,
            'comissao_av' => 1,
            'comissao_ap' => 2,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-COM-1',
            'nome_razao' => 'Cliente Teste',
            'pessoa_tipo' => Person::PESSOA_FISICA,
        ]);

        $user = User::factory()->create([
            'vendedor_id' => $vendedor->id,
        ]);

        Venda::query()->create([
            'numero' => '1001',
            'data' => '2026-09-10',
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'total' => 100.00,
            'forma_pagamento' => 'DINHEIRO',
            'status' => Venda::STATUS_FECHADO,
        ]);

        Venda::query()->create([
            'numero' => '1002',
            'data' => '2026-09-11',
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'total' => 200.00,
            'forma_pagamento' => 'BOLETO',
            'status' => Venda::STATUS_FECHADO,
        ]);

        Venda::query()->create([
            'numero' => '1003',
            'data' => '2026-09-12',
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'total' => 999.00,
            'forma_pagamento' => 'PIX',
            'status' => Venda::STATUS_CANCELADO,
        ]);

        Venda::query()->create([
            'numero' => '1004',
            'data' => '2026-08-31',
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'total' => 50.00,
            'forma_pagamento' => 'PIX',
            'status' => Venda::STATUS_FECHADO,
        ]);

        ForcaVendasOrder::query()->create([
            'uuid' => (string) Str::uuid(),
            'device_uuid' => 'test-device',
            'user_id' => $user->id,
            'tipo' => ForcaVendasOrder::TIPO_PEDIDO,
            'cliente_id' => $cliente->id,
            'vendedor_id' => $vendedor->id,
            'total' => 77.00,
            'status' => ForcaVendasOrder::STATUS_IMPORTADO,
            'situacao' => ForcaVendasOrder::SITUACAO_PENDENTE,
            'payload' => ['forma_pagamento' => 'DINHEIRO'],
            'received_at' => '2026-09-15 12:00:00',
        ]);

        $de = Carbon::parse('2026-09-01')->startOfDay();
        $ate = Carbon::parse('2026-09-23')->startOfDay();

        $app = app(ForcaVendasComissaoService::class)->build($user, $de, $ate);

        $vendasErp = Venda::query()
            ->with('vendedor:id,nome,comissao_av,comissao_ap')
            ->where('vendedor_id', $vendedor->id)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->where('status', Venda::STATUS_FECHADO)
            ->get();

        $erp = ComissaoVendedoresReport::build($vendasErp);
        $linha = $erp['linhas'][0];

        $this->assertSame(2, $app['qtd']);
        $this->assertSame(100.0, $app['total_avista']);
        $this->assertSame(200.0, $app['total_aprazo']);
        $this->assertSame(300.0, $app['total_geral']);
        $this->assertSame(1.0, $app['comissao_avista']);
        $this->assertSame(4.0, $app['comissao_aprazo']);
        $this->assertSame(5.0, $app['comissao_total']);

        $this->assertSame($linha['qtd'], $app['qtd']);
        $this->assertEquals($linha['total_avista'], $app['total_avista']);
        $this->assertEquals($linha['total_aprazo'], $app['total_aprazo']);
        $this->assertEquals($linha['total_geral'], $app['total_geral']);
        $this->assertEquals($linha['comissao_avista'], $app['comissao_avista']);
        $this->assertEquals($linha['comissao_aprazo'], $app['comissao_aprazo']);
        $this->assertEquals($linha['comissao_total'], $app['comissao_total']);

        $this->assertCount(2, $app['itens']);
        $this->assertTrue(collect($app['itens'])->every(fn (array $i) => $i['origem'] === 'venda'));

        $somaBase = round(array_sum(array_column($app['itens'], 'total')), 2);
        $this->assertSame($app['total_geral'], $somaBase);

        $somaCom = round(array_sum(array_map(fn (array $i) => (float) $i['comissao'], $app['itens'])), 2);
        $this->assertSame($app['comissao_total'], $somaCom);
    }

    public function test_soma_comissao_linhas_bate_com_cabecalho_apos_rateio(): void
    {
        $vendedor = Vendedor::query()->create([
            'codigo' => 'T2',
            'nome' => 'Vendedor Rateio',
            'ativo' => true,
            'comissao_av' => 1,
            'comissao_ap' => 1,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'C-COM-2',
            'nome_razao' => 'Cliente Rateio',
            'pessoa_tipo' => Person::PESSOA_FISICA,
        ]);

        $user = User::factory()->create([
            'vendedor_id' => $vendedor->id,
        ]);

        // Totais com restos que geram +1 centavo se arredondar linha a linha.
        $valoresAv = [15.15, 22.44, 13.80, 16.50, 27.80, 41.00, 9.00];
        $valoresAp = [55.20, 16.00, 16.00];

        $n = 2000;
        foreach ($valoresAv as $total) {
            Venda::query()->create([
                'numero' => (string) $n++,
                'data' => '2026-09-15',
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'total' => $total,
                'forma_pagamento' => 'DINHEIRO',
                'status' => Venda::STATUS_FECHADO,
            ]);
        }
        foreach ($valoresAp as $total) {
            Venda::query()->create([
                'numero' => (string) $n++,
                'data' => '2026-09-16',
                'cliente_id' => $cliente->id,
                'vendedor_id' => $vendedor->id,
                'total' => $total,
                'forma_pagamento' => 'BOLETO',
                'status' => Venda::STATUS_FECHADO,
            ]);
        }

        $de = Carbon::parse('2026-09-01')->startOfDay();
        $ate = Carbon::parse('2026-09-23')->startOfDay();
        $app = app(ForcaVendasComissaoService::class)->build($user, $de, $ate);

        $baseAv = array_sum($valoresAv);
        $baseAp = array_sum($valoresAp);
        $esperadoAv = round($baseAv * 1 / 100, 2);
        $esperadoAp = round($baseAp * 1 / 100, 2);
        $esperadoTot = round($esperadoAv + $esperadoAp, 2);

        $this->assertSame($esperadoAv, $app['comissao_avista']);
        $this->assertSame($esperadoAp, $app['comissao_aprazo']);
        $this->assertSame($esperadoTot, $app['comissao_total']);

        $somaLinhas = round(array_sum(array_map(fn (array $i) => (float) $i['comissao'], $app['itens'])), 2);
        $this->assertSame($app['comissao_total'], $somaLinhas);

        $somaAv = round(array_sum(array_map(
            fn (array $i) => ($i['tipo'] ?? '') === 'avista' ? (float) $i['comissao'] : 0.0,
            $app['itens']
        )), 2);
        $somaAp = round(array_sum(array_map(
            fn (array $i) => ($i['tipo'] ?? '') === 'aprazo' ? (float) $i['comissao'] : 0.0,
            $app['itens']
        )), 2);

        $this->assertSame($app['comissao_avista'], $somaAv);
        $this->assertSame($app['comissao_aprazo'], $somaAp);

        $vendasErp = Venda::query()
            ->with('vendedor:id,nome,comissao_av,comissao_ap')
            ->where('vendedor_id', $vendedor->id)
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->where('status', Venda::STATUS_FECHADO)
            ->get();
        $erp = ComissaoVendedoresReport::build($vendasErp)['linhas'][0];
        $this->assertEquals($erp['comissao_total'], $app['comissao_total']);
    }
}