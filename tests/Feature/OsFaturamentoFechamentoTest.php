<?php

namespace Tests\Feature;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\CaixaLivroMovimento;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Os\OrdemServicoFinanceiroVinculo;
use App\Support\Erp\Os\OsDescontoFechamentoRateio;
use App\Support\Erp\Os\OsFaturamentoService;
use App\Support\Erp\Os\OsFinPerf;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Auditoria do fechamento/faturamento da OS no MySQL DEV (bkp) com rollback.
 *
 * Rodar:
 *   tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=OsFaturamentoFechamentoTest
 */
class OsFaturamentoFechamentoTest extends TestCase
{
    use DatabaseTransactions;

    /** @var list<array{label: string, ms: float, queries?: int}> */
    private array $perfMarks = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.database' => env('DB_DATABASE', 'unitec_erp'),
            ]);
            DB::purge();
            DB::reconnect('mysql');
        }

        if (DB::getDriverName() !== 'mysql' && DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped(
                'OsFaturamentoFechamentoTest exige MySQL DEV. Driver atual: '.DB::getDriverName()
            );
        }
    }

    protected function tearDown(): void
    {
        if ($this->perfMarks !== []) {
            fwrite(STDOUT, "\n[OsFaturamento PERF]\n");
            foreach ($this->perfMarks as $mark) {
                $extra = isset($mark['queries']) ? ' queries='.$mark['queries'] : '';
                fwrite(STDOUT, sprintf("  %s: %.2f ms%s\n", $mark['label'], $mark['ms'], $extra));
            }
        }

        parent::tearDown();
    }

    public function test_dinheiro_fecha_caixa_estoque_e_espelho_sem_conta_receber(): void
    {
        [$os, $peca, $formas] = $this->osFaturavel(total: 100.00, estoquePeca: 10, qtdPeca: 2);

        $t0 = hrtime(true);
        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '100.00'],
        ]);

        $this->perfMarks[] = [
            'label' => 'faturar_dinheiro_100',
            'ms' => (hrtime(true) - $t0) / 1e6,
            'queries' => $queries,
        ];

        $os->refresh();
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);
        $this->assertEqualsWithDelta(100.0, (float) $os->total_geral, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $peca->fresh()->estoque, 0.001);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os);
        $caixa = CaixaLancamento::query()->where('documento', $doc)->get();
        $this->assertCount(1, $caixa);
        $this->assertEqualsWithDelta(100.0, (float) $caixa->sum('entrada'), 0.001);

        $this->assertSame(0, ContaReceber::query()->where('documento', $doc)->count());
        $this->assertSame(
            1,
            EstoqueMovimentacao::query()
                ->where('origem_tipo', 'ordem_servico')
                ->where('origem_id', $os->id)
                ->where('tipo', EstoqueMovimentacao::TIPO_VENDA)
                ->count()
        );

        if (Schema::hasColumn($os->getTable(), 'venda_id') && $os->venda_id) {
            $venda = Venda::query()->find($os->venda_id);
            $this->assertInstanceOf(Venda::class, $venda);
            $this->assertEqualsWithDelta(100.0, (float) $venda->total, 0.001);
            $this->assertSame(Venda::STATUS_FECHADO, $venda->status);
            $this->assertSame(Venda::PLATAFORMA_OS, $venda->plataforma);
        }
    }

    public function test_pix_entra_no_caixa_como_avista(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 75.50);

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['pix']->id, 'valor' => '75.50'],
        ]);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os->fresh());
        $caixa = CaixaLancamento::query()->where('documento', $doc)->get();
        $this->assertCount(1, $caixa);
        $this->assertEqualsWithDelta(75.50, (float) $caixa->sum('entrada'), 0.001);
        $this->assertSame(0, ContaReceber::query()->where('documento', $doc)->count());
    }

    public function test_cartao_contas_receber_gera_parcela_unica_com_prazo(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 120.00);
        $formas['cartao']->forceFill(['prazo_cartao' => 30])->save();

        $contas = app(OsFaturamentoService::class)->faturar($os, [
            [
                'id' => $formas['cartao']->id,
                'valor' => '120.00',
                'descricao' => 'CARTAO CREDITO OS',
                'tipo' => 'cartao_credito',
                'tipo_movimento' => 'contas_receber',
            ],
        ]);

        $this->assertCount(1, $contas);
        $conta = $contas[0];
        $this->assertEqualsWithDelta(120.0, (float) $conta->valor, 0.001);
        $this->assertSame(
            now()->startOfDay()->addDays(30)->toDateString(),
            \Illuminate\Support\Carbon::parse($conta->vencimento)->toDateString()
        );
        $this->assertSame(0, CaixaLancamento::query()
            ->where('documento', OrdemServicoFinanceiroVinculo::documentoBase($os))
            ->count());
    }

    public function test_prazo_crediario_gera_parcelas_com_valores_e_vencimentos(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 100.00);

        $contas = app(OsFaturamentoService::class)->faturar(
            $os,
            [
                [
                    'id' => $formas['prazo']->id,
                    'valor' => '100.00',
                    'descricao' => 'CREDIARIO OS',
                    'tipo' => 'crediario',
                    'tipo_movimento' => 'contas_receber',
                ],
            ],
            [30, 60, 90],
        );

        $this->assertCount(3, $contas);
        $valores = array_map(fn (ContaReceber $c): float => round((float) $c->valor, 2), $contas);
        $this->assertSame([33.33, 33.33, 33.34], $valores);
        $this->assertEqualsWithDelta(100.0, array_sum($valores), 0.001);

        $docs = array_map(fn (ContaReceber $c): string => (string) $c->documento, $contas);
        $base = OrdemServicoFinanceiroVinculo::documentoBase($os);
        $this->assertSame([$base.'-1', $base.'-2', $base.'-3'], $docs);

        $vencimentos = array_map(
            fn (ContaReceber $c): string => \Illuminate\Support\Carbon::parse($c->vencimento)->toDateString(),
            $contas,
        );
        $hoje = now()->startOfDay();
        $this->assertSame([
            $hoje->copy()->addDays(30)->toDateString(),
            $hoje->copy()->addDays(60)->toDateString(),
            $hoje->copy()->addDays(90)->toDateString(),
        ], $vencimentos);
    }

    public function test_pagamentos_combinados_fecham_exato_com_caixa_e_contas(): void
    {
        [$os, $peca, $formas] = $this->osFaturavel(total: 200.00, estoquePeca: 20, qtdPeca: 4);

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '50.00'],
            ['id' => $formas['pix']->id, 'valor' => '70.00'],
            [
                'id' => $formas['prazo']->id,
                'valor' => '80.00',
                'descricao' => 'CREDIARIO OS',
                'tipo' => 'crediario',
                'tipo_movimento' => 'contas_receber',
            ],
        ], [30]);

        $os->refresh();
        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os);

        $caixa = (float) CaixaLancamento::query()->where('documento', $doc)->sum('entrada');
        $cr = (float) ContaReceber::query()->where('documento', $doc)->sum('valor');

        $this->assertEqualsWithDelta(120.0, $caixa, 0.001);
        $this->assertEqualsWithDelta(80.0, $cr, 0.001);
        $this->assertEqualsWithDelta(200.0, $caixa + $cr, 0.001);
        $this->assertEqualsWithDelta(16.0, (float) $peca->fresh()->estoque, 0.001);
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);
    }

    public function test_troco_em_dinheiro_abate_excesso_e_nao_duplica_pagamento(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 100.00);

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '150.00'],
        ]);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os->fresh());
        $caixa = CaixaLancamento::query()->where('documento', $doc)->get();
        $this->assertCount(1, $caixa);
        $this->assertEqualsWithDelta(100.0, (float) $caixa->sum('entrada'), 0.001);
    }

    public function test_pagamento_menor_que_total_e_rejeitado(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 100.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Valor restante');

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '99.99'],
        ]);
    }

    public function test_excesso_sem_dinheiro_e_rejeitado(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 100.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maior que o total');

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['pix']->id, 'valor' => '110.00'],
        ]);
    }

    public function test_fechamento_duplo_e_bloqueado_apos_finalizar(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 100.00);

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '100.00'],
        ]);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os);
        $caixaAntes = CaixaLancamento::query()->where('documento', $doc)->count();
        $crAntes = ContaReceber::query()->where('documento', 'like', $doc.'%')->count();
        $movAntes = EstoqueMovimentacao::query()
            ->where('origem_tipo', 'ordem_servico')
            ->where('origem_id', $os->id)
            ->count();

        try {
            app(OsFaturamentoService::class)->faturar($os->fresh(), [
                ['id' => $formas['dinheiro']->id, 'valor' => '100.00'],
            ]);
            $this->fail('Segundo faturamento deveria falhar.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não pode ser faturada', $e->getMessage());
        }

        $this->assertSame($caixaAntes, CaixaLancamento::query()->where('documento', $doc)->count());
        $this->assertSame($crAntes, ContaReceber::query()->where('documento', 'like', $doc.'%')->count());
        $this->assertSame($movAntes, EstoqueMovimentacao::query()
            ->where('origem_tipo', 'ordem_servico')
            ->where('origem_id', $os->id)
            ->count());
    }

    public function test_acrescimo_no_total_nao_duplica_no_caixa(): void
    {
        // Espelha F4: acréscimo só no total_geral (não rateado nos itens).
        [$os, , $formas] = $this->osFaturavel(
            total: 110.00,
            totalProdutos: 40.00,
            totalServicos: 60.00,
            acrescimoCabecalho: 10.00,
        );

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '110.00'],
        ]);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os->fresh());
        $caixa = (float) CaixaLancamento::query()->where('documento', $doc)->sum('entrada');
        $this->assertEqualsWithDelta(110.0, $caixa, 0.001);
        $this->assertEqualsWithDelta(110.0, (float) $os->fresh()->total_geral, 0.001);

        $somaItens = (float) OrdemServicoItem::query()
            ->where('ordem_servico_id', $os->id)
            ->sum('total');
        $this->assertEqualsWithDelta(100.0, $somaItens, 0.001);
        $this->assertEqualsWithDelta(10.0, (float) $os->fresh()->total_geral - $somaItens, 0.001);
    }

    public function test_desconto_f4_rateado_antes_do_faturamento_fecha_pelo_liquido(): void
    {
        [$os, $peca, $formas] = $this->osFaturavel(total: 100.00, estoquePeca: 10, qtdPeca: 2);

        $itensState = [];
        foreach ($os->itens as $item) {
            $itensState[] = [
                'tipo' => $item->tipo,
                'qtd' => number_format((float) $item->qtd, 3, ',', ''),
                'preco' => number_format((float) $item->preco, 2, ',', ''),
                'acrescimo' => '0,00',
                'desconto' => '0,00',
                'total' => number_format((float) $item->total, 2, ',', ''),
                '_id' => $item->id,
            ];
        }

        $rateio = OsDescontoFechamentoRateio::aplicar($itensState, 15.00);
        $this->assertSame(15.0, $rateio['desconto_aplicado']);

        $soma = 0.0;
        foreach ($rateio['itens'] as $row) {
            $total = ErpMoney::parseBr($row['total']);
            $desconto = ErpMoney::parseBr($row['desconto']);
            $soma += $total;
            OrdemServicoItem::query()->whereKey($row['_id'])->update([
                'desconto' => $desconto,
                'total' => $total,
            ]);
        }
        $soma = round($soma, 2);
        $os->forceFill(['total_geral' => $soma])->save();

        app(OsFaturamentoService::class)->faturar($os->fresh(['itens.product', 'cliente']), [
            ['id' => $formas['dinheiro']->id, 'valor' => number_format($soma, 2, '.', '')],
        ]);

        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os);
        $this->assertEqualsWithDelta(85.0, (float) CaixaLancamento::query()->where('documento', $doc)->sum('entrada'), 0.001);
        $this->assertEqualsWithDelta(85.0, (float) $os->fresh()->total_geral, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $peca->fresh()->estoque, 0.001);
    }

    public function test_total_zero_apos_desconto_100_nao_fatura(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 0.00);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('não tem valor para faturar');

        app(OsFaturamentoService::class)->faturar($os, [
            ['id' => $formas['dinheiro']->id, 'valor' => '0.01'],
        ]);
    }

    public function test_performance_faturamento_combinado_fica_abaixo_de_limite_dev(): void
    {
        [$os, , $formas] = $this->osFaturavel(total: 250.00, estoquePeca: 50, qtdPeca: 5);

        putenv('ERP_OS_FIN_PERF=1');
        $_ENV['ERP_OS_FIN_PERF'] = '1';
        OsFinPerf::reset();
        OsFinPerf::listenQueries();

        $t0 = hrtime(true);
        $queryCount = 0;
        DB::listen(static function () use (&$queryCount): void {
            $queryCount++;
        });

        app(OsFaturamentoService::class)->faturar(
            $os,
            [
                ['id' => $formas['dinheiro']->id, 'valor' => '50.00'],
                ['id' => $formas['pix']->id, 'valor' => '50.00'],
                [
                    'id' => $formas['cartao']->id,
                    'valor' => '50.00',
                    'descricao' => 'CARTAO CREDITO OS',
                    'tipo' => 'cartao_credito',
                    'tipo_movimento' => 'contas_receber',
                ],
                [
                    'id' => $formas['prazo']->id,
                    'valor' => '100.00',
                    'descricao' => 'CREDIARIO OS',
                    'tipo' => 'crediario',
                    'tipo_movimento' => 'contas_receber',
                ],
            ],
            [30, 60],
        );

        $ms = (hrtime(true) - $t0) / 1e6;
        $snap = OsFinPerf::snapshot();

        $this->perfMarks[] = [
            'label' => 'faturar_combinado_4_formas',
            'ms' => $ms,
            'queries' => $queryCount,
        ];
        $this->perfMarks[] = [
            'label' => 'os_fin_perf_total',
            'ms' => (float) ($snap['total_ms'] ?? 0),
            'queries' => (int) ($snap['query_count'] ?? 0),
        ];

        // Baseline DEV pós-cache schema (~40–70 ms / ~60–80 queries). Folga para Celeron.
        $this->assertLessThan(3000.0, $ms, 'Faturamento combinado acima de 3s no DEV.');
        $this->assertLessThan(150, $queryCount, 'Faturamento combinado com demasiadas queries.');
        $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->fresh()->situacao);
    }

    /**
     * @return array{0: OrdemServico, 1: Product, 2: array{dinheiro: FormaPagamento, pix: FormaPagamento, cartao: FormaPagamento, prazo: FormaPagamento}}
     */
    private function osFaturavel(
        float $total = 100.00,
        float $estoquePeca = 10.0,
        float $qtdPeca = 2.0,
        float $totalProdutos = 40.00,
        float $totalServicos = 60.00,
        float $acrescimoCabecalho = 0.0,
    ): array {
        $suffix = (string) random_int(700000, 799999);

        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMPRESA OS FAT '.$suffix,
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $geral = CaixaConta::ensureCaixaGeral();
        CaixaLivroMovimento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $geral->id,
            'status' => CaixaLivroMovimento::STATUS_ABERTO,
            'aberto_em' => now(),
            'aberto_por' => $user->id,
            'saldo_abertura' => 0,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'CF'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS FAT '.$suffix,
            'is_cliente' => true,
            'ativo' => true,
            'cpf_cnpj' => '529.982.247-25',
        ]);

        $peca = Product::query()->create([
            'codigo' => 'PF'.$suffix,
            'descricao' => 'PECA OS FAT '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => 20,
            'estoque' => $estoquePeca,
            'ativo' => true,
        ]);

        $precoPeca = $qtdPeca > 0 ? round($totalProdutos / $qtdPeca, 2) : 20.0;

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => (string) (800000 + random_int(1, 99999)),
            'situacao' => OrdemServico::SITUACAO_ABERTA,
            'cliente_id' => $cliente->id,
            'total_geral' => $total,
            'total_produtos' => $totalProdutos,
            'total_servicos' => $totalServicos,
        ])->save();

        $itemPeca = new OrdemServicoItem();
        $itemPeca->forceFill([
            'ordem_servico_id' => $os->id,
            'empresa_id' => $empresa->id,
            'tipo' => 'P',
            'product_id' => $peca->id,
            'qtd' => $qtdPeca,
            'preco' => $precoPeca,
            'acrescimo' => 0,
            'desconto' => 0,
            'total' => $totalProdutos,
        ])->save();

        $itemServ = new OrdemServicoItem();
        $itemServ->forceFill([
            'ordem_servico_id' => $os->id,
            'empresa_id' => $empresa->id,
            'tipo' => 'S',
            'product_id' => null,
            'qtd' => 1,
            'preco' => $totalServicos,
            'acrescimo' => 0,
            'desconto' => 0,
            'total' => $totalServicos,
        ])->save();

        if ($acrescimoCabecalho > 0) {
            // Itens somam produtos+serviços; total_geral já inclui o F4 acréscimo.
            $this->assertEqualsWithDelta(
                $totalProdutos + $totalServicos + $acrescimoCabecalho,
                $total,
                0.001,
            );
        }

        $formas = [
            'dinheiro' => $this->forma('DINHEIRO OS FAT '.$suffix, 'dinheiro', 'caixa'),
            'pix' => $this->forma('PIX OS FAT '.$suffix, 'pix', 'caixa'),
            'cartao' => $this->forma('CARTAO CREDITO OS FAT '.$suffix, 'cartao_credito', 'contas_receber'),
            'prazo' => $this->forma('CREDIARIO OS FAT '.$suffix, 'crediario', 'contas_receber'),
        ];

        return [$os->fresh(['itens.product', 'cliente']), $peca, $formas];
    }

    private function forma(string $descricao, string $tipo, string $movimento): FormaPagamento
    {
        $forma = new FormaPagamento();
        $forma->forceFill([
            'codigo' => (int) FormaPagamento::query()->max('codigo') + 1,
            'descricao' => $descricao,
            'tipo' => $tipo,
            'tipo_movimento' => $movimento,
            'ativo' => true,
        ])->save();

        return $forma;
    }
}
