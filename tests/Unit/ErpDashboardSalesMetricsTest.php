<?php

namespace Tests\Unit;

use App\Models\ContaReceber;
use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\Dashboard\ErpDashboardKpis;
use App\Support\Erp\Dashboard\ErpDashboardSalesMetrics;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ErpDashboardSalesMetricsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_faturamento_dia_soma_vendas_nao_canceladas(): void
    {
        $cliente = Person::query()->create([
            'codigo' => 'DASH01',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE DASHBOARD',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $hoje = Carbon::today()->toDateString();

        Venda::query()->create([
            'numero' => '000001',
            'data' => $hoje,
            'hora' => '10:00:00',
            'cliente_id' => $cliente->id,
            'total' => 150.50,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        Venda::query()->create([
            'numero' => '000002',
            'data' => $hoje,
            'hora' => '11:00:00',
            'cliente_id' => $cliente->id,
            'total' => 49.50,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $this->assertSame(150.50, ErpDashboardSalesMetrics::faturamentoDia(Carbon::today()));
    }

    public function test_kpi_faturamento_hoje_usa_valor_real(): void
    {
        $cliente = Person::query()->create([
            'codigo' => 'DASH02',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE KPI',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        Venda::query()->create([
            'numero' => '000003',
            'data' => Carbon::today()->toDateString(),
            'hora' => '09:30:00',
            'cliente_id' => $cliente->id,
            'total' => 200,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
        ]);

        $kpis = ErpDashboardKpis::build();
        $faturamento = collect($kpis)->firstWhere('key', 'faturamento_hoje');

        $this->assertNotNull($faturamento);
        $this->assertSame('R$ 200,00', $faturamento['value']);
    }

    public function test_kpi_contas_receber_soma_saldos_em_aberto(): void
    {
        $cliente = Person::query()->create([
            'codigo' => 'DASH03',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE RECEBER',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        ContaReceber::query()->create([
            'numero' => '000001',
            'emissao' => Carbon::today(),
            'cliente_id' => $cliente->id,
            'vencimento' => Carbon::today()->addDays(10),
            'valor' => 300,
            'saldo' => 300,
            'forma' => ContaReceber::FORMA_CARTEIRA,
        ]);

        $kpis = ErpDashboardKpis::build();
        $receber = collect($kpis)->firstWhere('key', 'contas_receber');

        $this->assertNotNull($receber);
        $this->assertSame('R$ 300,00', $receber['value']);
        $this->assertSame('1 título em aberto', $receber['hint']);
    }
}
