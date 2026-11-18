<?php

namespace Tests\Unit;

use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\Reports\FaturamentoMensalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaturamentoMensalServiceTest extends TestCase
{
    private FaturamentoMensalService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->svc = app(FaturamentoMensalService::class);
    }

    public function test_agrupa_por_mes_sem_duplicar_pdv_espelhado(): void
    {
        $emp = 1;
        $this->venda($emp, 100.00, '2026-01-10');
        $this->venda($emp, 50.00, '2026-02-05');
        $this->venda($emp, 200.00, '2026-02-20');

        $sessao = $this->sessao($emp);
        $espelhada = $this->venda($emp, 80.00, '2026-03-01', Venda::PLATAFORMA_PDV);
        $this->pdv($sessao, 80.00, $espelhada->id, '2026-03-01 10:00:00');
        $this->pdv($sessao, 45.50, null, '2026-03-15 12:00:00');

        // NF-e “fantasma” não deve entrar no total.
        DB::table('nfes')->insert([
            'empresa_id' => $emp,
            'venda_id' => $espelhada->id,
            'total' => 9999.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $r = $this->svc->calcular($emp, 2026, null);

        $this->assertSame(12, count($r['bars']));
        $this->assertSame(100.00, $r['bars'][0]['total']);
        $this->assertSame(250.00, $r['bars'][1]['total']);
        $this->assertSame(125.50, $r['bars'][2]['total']); // 80 venda + 45,50 PDV órfão
        $this->assertSame(475.50, $r['resumo']['faturamento_total']);
        $this->assertSame(12, $r['resumo']['qtd_meses']);
        $this->assertSame('Fev/26', $r['resumo']['melhor_mes']);
        $this->assertSame(250.00, $r['resumo']['melhor_mes_total']);
        $this->assertSame(158.50, $r['resumo']['media_mensal']); // 475.50 / 3 meses com valor
        $this->assertSame(5, $r['resumo']['qtd_vendas']);
    }

    public function test_filtro_mes_unico_e_ignora_cancelada(): void
    {
        $emp = 1;
        $this->venda($emp, 10.00, '2026-05-01');
        $this->venda($emp, 90.00, '2026-05-10', Venda::PLATAFORMA_ERP, Venda::STATUS_CANCELADO);
        $this->venda($emp, 30.00, '2026-06-01');

        $r = $this->svc->calcular($emp, 2026, 5);

        $this->assertSame(1, count($r['bars']));
        $this->assertSame(10.00, $r['bars'][0]['total']);
        $this->assertSame(10.00, $r['resumo']['faturamento_total']);
        $this->assertSame(10.00, $r['resumo']['media_mensal']);
        $this->assertSame(1, $r['resumo']['qtd_vendas']);
    }

    public function test_nao_soma_regularizacao_fiscal_orfao(): void
    {
        $emp = 1;
        $sessao = $this->sessao($emp);
        $this->pdv($sessao, 70.00, null, '2026-04-02 09:00:00', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
        $this->pdv($sessao, 20.00, null, '2026-04-03 09:00:00');

        $r = $this->svc->calcular($emp, 2026, 4);

        $this->assertSame(20.00, $r['resumo']['faturamento_total']);
        $this->assertSame(1, $r['resumo']['qtd_vendas']);
    }

    private function venda(
        int $empresaId,
        float $total,
        string $data,
        string $plataforma = Venda::PLATAFORMA_ERP,
        string $status = Venda::STATUS_FECHADO,
    ): Venda {
        return Venda::query()->create([
            'empresa_id' => $empresaId,
            'numero' => 'V'.uniqid(),
            'data' => $data,
            'cliente_id' => 1,
            'total' => $total,
            'status' => $status,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $plataforma,
        ]);
    }

    private function sessao(int $empresaId): int
    {
        return (int) DB::table('pdv_caixa_sessoes')->insertGetId([
            'user_id' => 1,
            'empresa_id' => $empresaId,
            'valor_abertura' => 0,
            'aberto_em' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pdv(
        int $sessaoId,
        float $total,
        ?int $vendaId,
        string $fechadoEm,
        ?string $origem = null,
    ): void {
        DB::table('pdv_vendas')->insert([
            'pdv_caixa_sessao_id' => $sessaoId,
            'venda_id' => $vendaId,
            'numero' => random_int(1, 99999),
            'subtotal' => $total,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => $total,
            'situacao' => 'F',
            'fechado_em' => $fechadoEm,
            'origem' => $origem,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach (['nfes', 'pdv_vendas', 'pdv_caixa_sessoes', 'vendas'] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40);
            $table->date('data');
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->string('status', 20);
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->decimal('valor_abertura', 15, 2)->default(0);
            $table->timestamp('aberto_em')->nullable();
            $table->timestamps();
        });
        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedInteger('numero')->default(1);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('acrescimo', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->string('situacao', 4)->default('F');
            $table->timestamp('fechado_em')->nullable();
            $table->string('origem', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('nfes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
