<?php

namespace Tests\Feature;

use App\Models\DevolucaoVenda;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\Dre\DreReceitasGerenciaisService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DreReceitasGerenciaisD21Test extends TestCase
{
    private DreReceitasGerenciaisService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
        $this->svc = app(DreReceitasGerenciaisService::class);
    }

    public function test_venda_normal_erp(): void
    {
        $emp = $this->empresa(1);
        $this->venda($emp, '100.00', '2026-11-10', Venda::PLATAFORMA_ERP);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('100.00', $r['receita_mercadorias']);
        $this->assertSame('100.00', $r['receita_liquida_operacional']);
        $this->assertSame(1, $r['contagens']['vendas_fechadas']);
    }

    public function test_venda_pdv_espelhada_nao_duplica(): void
    {
        $emp = $this->empresa(1);
        $sessao = $this->sessao($emp);
        $venda = $this->venda($emp, '80.00', '2026-11-11', Venda::PLATAFORMA_PDV);
        $this->pdv($sessao, '80.00', '5.00', $venda->id, '2026-11-11 10:00:00');

        $caixaAntes = DB::table('caixa_lancamentos')->count();
        $crAntes = DB::table('contas_receber')->count();
        $nfeAntes = DB::table('nfes')->count();

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('80.00', $r['receita_mercadorias']);
        $this->assertSame(1, $r['contagens']['vendas_fechadas']);
        $this->assertSame(0, $r['contagens']['pdv_sem_espelho']);
        $this->assertSame('5.00', $r['descontos_embutidos_informativo']);
        // Desconto informativo — não re-subtrai do total já líquido.
        $this->assertSame('80.00', $r['receita_liquida_operacional']);

        $this->assertSame($caixaAntes, DB::table('caixa_lancamentos')->count());
        $this->assertSame($crAntes, DB::table('contas_receber')->count());
        $this->assertSame($nfeAntes, DB::table('nfes')->count());
    }

    public function test_venda_pdv_sem_espelho(): void
    {
        $emp = $this->empresa(1);
        $sessao = $this->sessao($emp);
        $this->pdv($sessao, '45.50', '0', null, '2026-11-12 12:00:00');

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('45.50', $r['receita_mercadorias']);
        $this->assertSame(0, $r['contagens']['vendas_fechadas']);
        $this->assertSame(1, $r['contagens']['pdv_sem_espelho']);
    }

    public function test_forca_vendas_mobile(): void
    {
        $emp = $this->empresa(1);
        $this->venda($emp, '200.00', '2026-11-13', Venda::PLATAFORMA_MOBILE);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('200.00', $r['receita_mercadorias']);
    }

    public function test_os_somente_servicos(): void
    {
        $emp = $this->empresa(1);
        $this->os($emp, '2026-11-14', 150.00, 0);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['receita_mercadorias']);
        $this->assertSame('150.00', $r['receita_servicos']);
        $this->assertSame('0.00', $r['receita_pecas_os']);
        $this->assertSame('150.00', $r['receita_bruta_operacional']);
    }

    public function test_os_pecas_e_servicos_sem_somar_total_geral(): void
    {
        $emp = $this->empresa(1);
        // total_geral propositalmente “errado” alto — serviço deve ignorar.
        $this->os($emp, '2026-11-15', 100.00, 40.00, totalGeral: 999.00);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('100.00', $r['receita_servicos']);
        $this->assertSame('40.00', $r['receita_pecas_os']);
        $this->assertSame('140.00', $r['receita_bruta_operacional']);
    }

    public function test_os_com_venda_vinculada_nao_duplica(): void
    {
        $emp = $this->empresa(1);
        $vendaOs = $this->venda($emp, '140.00', '2026-11-16', Venda::PLATAFORMA_OS);
        $this->os($emp, '2026-11-16', 100.00, 40.00, vendaId: (int) $vendaOs->id);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['receita_mercadorias']);
        $this->assertSame('100.00', $r['receita_servicos']);
        $this->assertSame('40.00', $r['receita_pecas_os']);
        $this->assertSame('140.00', $r['receita_liquida_operacional']);
    }

    public function test_desconto_ja_no_total(): void
    {
        $emp = $this->empresa(1);
        $sessao = $this->sessao($emp);
        // subtotal 100, desconto 10, total líquido 90 espelhado na venda
        $venda = $this->venda($emp, '90.00', '2026-11-17', Venda::PLATAFORMA_PDV);
        $this->pdv($sessao, '90.00', '10.00', $venda->id, '2026-11-17 09:00:00');

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('90.00', $r['receita_mercadorias']);
        $this->assertSame('10.00', $r['descontos_embutidos_informativo']);
        $this->assertSame('90.00', $r['receita_liquida_operacional']);
    }

    public function test_cancelamento_excluido(): void
    {
        $emp = $this->empresa(1);
        $this->venda($emp, '50.00', '2026-11-18', Venda::PLATAFORMA_ERP);
        Venda::query()->create([
            'empresa_id' => $emp,
            'numero' => 'C'.uniqid(),
            'data' => '2026-11-18',
            'cliente_id' => 1,
            'total' => 70,
            'status' => Venda::STATUS_CANCELADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('50.00', $r['receita_mercadorias']);
    }

    public function test_devolucao_finalizada_uma_vez(): void
    {
        $emp = $this->empresa(1);
        $venda = $this->venda($emp, '100.00', '2026-11-19', Venda::PLATAFORMA_ERP);
        DevolucaoVenda::query()->create([
            'empresa_id' => $emp,
            'numero' => 'D1',
            'situacao' => DevolucaoVenda::SITUACAO_FINALIZADA,
            'tipo_devolucao' => DevolucaoVenda::TIPO_PARCIAL,
            'data' => '2026-11-20',
            'venda_id' => $venda->id,
            'total' => 30,
        ]);
        DevolucaoVenda::query()->create([
            'empresa_id' => $emp,
            'numero' => 'D2',
            'situacao' => DevolucaoVenda::SITUACAO_ABERTA,
            'tipo_devolucao' => DevolucaoVenda::TIPO_PARCIAL,
            'data' => '2026-11-20',
            'venda_id' => $venda->id,
            'total' => 20,
        ]);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('100.00', $r['receita_mercadorias']);
        $this->assertSame('30.00', $r['devolucoes']);
        $this->assertSame('70.00', $r['receita_liquida_operacional']);
        $this->assertSame(1, $r['contagens']['devolucoes_finalizadas']);
    }

    public function test_isolamento_empresas_e_periodos(): void
    {
        $e1 = $this->empresa(1);
        $e2 = $this->empresa(2);
        $this->venda($e1, '10.00', '2026-11-05', Venda::PLATAFORMA_ERP);
        $this->venda($e1, '99.00', '2026-10-05', Venda::PLATAFORMA_ERP);
        $this->venda($e2, '500.00', '2026-11-05', Venda::PLATAFORMA_ERP);

        $r = $this->svc->calcular($e1, '2026-11-01', '2026-11-30');
        $this->assertSame('10.00', $r['receita_mercadorias']);

        $r2 = $this->svc->calcular($e2, '2026-11-01', '2026-11-30');
        $this->assertSame('500.00', $r2['receita_mercadorias']);
    }

    public function test_nao_soma_cr_caixa_nfe_tef(): void
    {
        $emp = $this->empresa(1);
        $this->venda($emp, '25.00', '2026-11-21', Venda::PLATAFORMA_ERP);

        DB::table('contas_receber')->insert([
            'empresa_id' => $emp, 'valor' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('caixa_lancamentos')->insert([
            'empresa_id' => $emp, 'entrada' => 25, 'saida' => 0, 'documento' => 'PDV-CX-1',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('nfes')->insert([
            'empresa_id' => $emp, 'total' => 25, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('tef_liquidacao_lotes')->insert([
            'empresa_id' => $emp, 'valor_bruto' => 25, 'valor_liquido' => 24, 'valor_taxas' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('25.00', $r['receita_mercadorias']);
        $this->assertSame('25.00', $r['receita_liquida_operacional']);
        $this->assertArrayHasKey('taxas_tef', $r['ausencias']);
        $this->assertArrayHasKey('cmv', $r['ausencias']);
        $this->assertArrayHasKey('impostos', $r['ausencias']);
    }

    public function test_performance_volume(): void
    {
        $emp = $this->empresa(1);
        $rows = [];
        $now = now();
        for ($i = 0; $i < 500; $i++) {
            $rows[] = [
                'empresa_id' => $emp,
                'numero' => 'V'.$i.uniqid(),
                'data' => '2026-11-'.str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT),
                'cliente_id' => 1,
                'total' => 1.00,
                'status' => Venda::STATUS_FECHADO,
                'tipo' => Venda::TIPO_PEDIDO,
                'plataforma' => Venda::PLATAFORMA_ERP,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('vendas')->insert($chunk);
        }

        $mem0 = memory_get_usage(true);
        $t0 = hrtime(true);
        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $ms = (hrtime(true) - $t0) / 1e6;
        $memDelta = memory_get_usage(true) - $mem0;

        $this->assertSame(500, $r['contagens']['vendas_fechadas']);
        $this->assertSame('500.00', $r['receita_mercadorias']);
        $this->assertLessThan(800, $ms, "calcular {$ms}ms");
        $this->assertLessThan(16 * 1024 * 1024, $memDelta);
    }

    public function test_pdv_origem_nao_comercial_ignorada(): void
    {
        $emp = $this->empresa(1);
        $sessao = $this->sessao($emp);
        $this->pdv($sessao, '60.00', '0', null, '2026-11-22 10:00:00', PdvVenda::ORIGEM_OS_NFCE_PECAS);

        $r = $this->svc->calcular($emp, '2026-11-01', '2026-11-30');
        $this->assertSame('0.00', $r['receita_mercadorias']);
    }

    private function empresa(int $id): int
    {
        DB::table('empresas')->insertOrIgnore([
            'id' => $id,
            'razao_social' => 'E'.$id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
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

    private function venda(int $empresaId, string $total, string $data, string $plataforma): Venda
    {
        return Venda::query()->create([
            'empresa_id' => $empresaId,
            'numero' => 'N'.uniqid(),
            'data' => $data,
            'cliente_id' => 1,
            'total' => $total,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => $plataforma,
        ]);
    }

    private function pdv(
        int $sessaoId,
        string $total,
        string $desconto,
        ?int $vendaId,
        string $fechadoEm,
        ?string $origem = null,
    ): void {
        DB::table('pdv_vendas')->insert([
            'pdv_caixa_sessao_id' => $sessaoId,
            'venda_id' => $vendaId,
            'numero' => random_int(1, 99999),
            'subtotal' => (float) $total + (float) $desconto,
            'desconto' => $desconto,
            'acrescimo' => 0,
            'total' => $total,
            'situacao' => 'F',
            'fechado_em' => $fechadoEm,
            'origem' => $origem,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function os(
        int $empresaId,
        string $dataTermino,
        float $servicos,
        float $pecas,
        float $totalGeral = 0,
        ?int $vendaId = null,
    ): void {
        OrdemServico::query()->create([
            'empresa_id' => $empresaId,
            'numero' => 'OS'.uniqid(),
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'data_termino' => $dataTermino,
            'cliente_id' => 1,
            'venda_id' => $vendaId,
            'total_servicos' => $servicos,
            'total_produtos' => $pecas,
            'total_geral' => $totalGeral > 0 ? $totalGeral : ($servicos + $pecas),
            'subtotal' => $servicos + $pecas,
            'subtotal_servicos' => $servicos,
            'subtotal_pecas' => $pecas,
            'vl_desc_pecas' => 0,
            'vl_desc_servicos' => 0,
        ]);
    }

    private function createMinimalSchema(): void
    {
        foreach ([
            'tef_liquidacao_lotes', 'nfes', 'contas_receber', 'caixa_lancamentos',
            'devolucoes_venda', 'ordens_servico', 'pdv_vendas', 'pdv_caixa_sessoes',
            'vendas', 'empresas',
        ] as $t) {
            Schema::dropIfExists($t);
        }

        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('razao_social')->nullable();
            $table->timestamps();
        });
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
        Schema::create('ordens_servico', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->string('situacao', 20);
            $table->date('data_termino')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('subtotal_pecas', 15, 2)->default(0);
            $table->decimal('subtotal_servicos', 15, 2)->default(0);
            $table->decimal('vl_desc_pecas', 15, 2)->default(0);
            $table->decimal('vl_desc_servicos', 15, 2)->default(0);
            $table->decimal('total_servicos', 15, 2)->default(0);
            $table->decimal('total_produtos', 15, 2)->default(0);
            $table->decimal('total_geral', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('devolucoes_venda', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 40)->nullable();
            $table->string('situacao', 20);
            $table->string('tipo_devolucao', 20)->nullable();
            $table->date('data')->nullable();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('caixa_lancamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->decimal('entrada', 15, 2)->default(0);
            $table->decimal('saida', 15, 2)->default(0);
            $table->string('documento', 40)->nullable();
            $table->timestamps();
        });
        Schema::create('contas_receber', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->decimal('valor', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('nfes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('tef_liquidacao_lotes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->decimal('valor_bruto', 15, 2)->default(0);
            $table->decimal('valor_taxas', 15, 2)->default(0);
            $table->decimal('valor_liquido', 15, 2)->default(0);
            $table->timestamps();
        });
    }
}
