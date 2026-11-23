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
use App\Support\Erp\Financeiro\CaixaAbertoGuard;
use App\Support\Erp\Os\OrdemServicoFinanceiroVinculo;
use App\Support\Erp\Os\OsFaturamentoService;
use App\Support\Erp\Os\OsReabrirService;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Validação final DEV: corrida real de faturamento + isolamento dos memos por empresa.
 *
 * Sem DatabaseTransactions no teste de corrida (workers precisam ver dados commitados).
 * Cleanup explícito no finally.
 *
 * Rodar:
 *   tools\php\php.exe vendor\bin\phpunit -c phpunit.dev-mysql.xml --filter=OsFaturamentoConcorrenciaDevTest
 */
class OsFaturamentoConcorrenciaDevTest extends TestCase
{
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
                'OsFaturamentoConcorrenciaDevTest exige MySQL DEV. Driver: '.DB::getDriverName()
            );
        }
    }

    public function test_duas_requisicoes_simultaneas_faturam_uma_unica_vez(): void
    {
        $ctx = $this->criarOsCommitada(total: 100.00, estoquePeca: 10.0, qtdPeca: 2.0);
        $os = $ctx['os'];
        $peca = $ctx['peca'];
        $forma = $ctx['forma'];
        $empresa = $ctx['empresa'];
        $user = $ctx['user'];
        $estoqueAntes = (float) $peca->estoque;
        $doc = OrdemServicoFinanceiroVinculo::documentoBase($os);

        $readyFile = storage_path('framework/os-fat-race-ready-'.getmypid().'.flag');
        @unlink($readyFile);

        $php = PHP_BINARY;
        $worker = base_path('tests/Support/os-faturar-race-worker.php');
        $args = [
            $php,
            $worker,
            '--os='.(int) $os->id,
            '--forma='.(int) $forma->id,
            '--valor=100.00',
            '--empresa='.(int) $empresa->id,
            '--user='.(int) $user->id,
            '--ready='.$readyFile,
        ];

        try {
            $pool = Process::pool(function (Pool $pool) use ($args): void {
                $pool->path(base_path())->timeout(45)->command($args);
                $pool->path(base_path())->timeout(45)->command($args);
            })->start();

            // Barreira: os dois workers já devem estar vivos esperando o arquivo.
            usleep(250_000);
            file_put_contents($readyFile, '1');

            $pending = $pool->wait();

            $results = [];
            foreach ($pending->collect() as $result) {
                $this->assertTrue(
                    $result->successful() || $result->exitCode() === 1,
                    'stderr='.$result->errorOutput().' stdout='.$result->output()
                );
                $line = trim((string) $result->output());
                $json = json_decode($line, true);
                $this->assertIsArray($json, 'Worker sem JSON: '.$line.' stderr='.$result->errorOutput());
                $results[] = $json;
            }

            $this->assertCount(2, $results);
            $oks = array_values(array_filter($results, static fn (array $r): bool => ($r['ok'] ?? false) === true));
            $fails = array_values(array_filter($results, static fn (array $r): bool => ($r['ok'] ?? false) !== true));

            $this->assertCount(1, $oks, 'Esperado exatamente 1 faturamento OK. Resultados: '.json_encode($results));
            $this->assertCount(1, $fails, 'Esperado exatamente 1 falha. Resultados: '.json_encode($results));
            $this->assertStringContainsString('não pode ser faturada', (string) ($fails[0]['error'] ?? ''));

            $os->refresh();
            $this->assertSame(OrdemServico::SITUACAO_FINALIZADA, $os->situacao);

            $caixaCount = CaixaLancamento::query()->where('documento', $doc)->count();
            $caixaSoma = (float) CaixaLancamento::query()->where('documento', $doc)->sum('entrada');
            $crCount = ContaReceber::query()->where('documento', 'like', $doc.'%')->count();
            $movCount = EstoqueMovimentacao::query()
                ->where('origem_tipo', 'ordem_servico')
                ->where('origem_id', $os->id)
                ->where('tipo', EstoqueMovimentacao::TIPO_VENDA)
                ->count();

            $this->assertSame(1, $caixaCount, 'Caixa duplicado na corrida.');
            $this->assertEqualsWithDelta(100.0, $caixaSoma, 0.001);
            $this->assertSame(0, $crCount);
            $this->assertSame(1, $movCount, 'Estoque/movimentação duplicada na corrida.');
            $this->assertEqualsWithDelta($estoqueAntes - 2.0, (float) $peca->fresh()->estoque, 0.001);
        } finally {
            @unlink($readyFile);
            $this->limparCtx($ctx);
        }
    }

    public function test_memo_caixa_geral_respeita_caixa_aberto_por_empresa(): void
    {
        DB::beginTransaction();

        try {
            $suffix = (string) random_int(500000, 599999);
            $geral = CaixaConta::ensureCaixaGeral();
            // Aquece o memo estático propositalmente.
            $this->assertSame((int) $geral->id, (int) CaixaConta::ensureCaixaGeral()->id);

            $empresaA = Empresa::query()->create([
                'codigo' => $suffix,
                'nome' => 'EMP MEMO A '.$suffix,
                'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
            ]);
            $empresaB = Empresa::query()->create([
                'codigo' => (string) ((int) $suffix + 1),
                'nome' => 'EMP MEMO B '.$suffix,
                'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
            ]);

            $userA = User::factory()->create([
                'empresa_id' => $empresaA->id,
                'is_admin' => true,
                'ativo' => true,
            ]);
            $userB = User::factory()->create([
                'empresa_id' => $empresaB->id,
                'is_admin' => true,
                'ativo' => true,
            ]);

            CaixaLivroMovimento::query()->create([
                'empresa_id' => $empresaA->id,
                'caixa_conta_id' => $geral->id,
                'status' => CaixaLivroMovimento::STATUS_ABERTO,
                'aberto_em' => now(),
                'aberto_por' => $userA->id,
                'saldo_abertura' => 0,
            ]);
            // Empresa B: SEM movimento aberto.

            $guard = app(CaixaAbertoGuard::class);
            // Schema memo aquecido.
            $this->assertTrue($guard->tabelaPronta());
            $this->assertTrue($guard->tabelaPronta());

            $this->assertTrue($guard->caixaGeralAberto((int) $empresaA->id));
            $this->assertFalse($guard->caixaGeralAberto((int) $empresaB->id));

            // Mesmo após memo do CAIXA GERAL, B continua bloqueada.
            session(['erp_empresa_id' => $empresaB->id]);
            $this->actingAs($userB);
            ErpContext::clearMemo();

            try {
                $guard->assertPodeFinalizarVenda((int) $empresaB->id);
                $this->fail('Empresa B sem caixa aberto deveria falhar.');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('CAIXA FECHADO', $e->getMessage());
            }

            // A continua operacional com o mesmo memo de CAIXA GERAL.
            $guard->assertPodeFinalizarVenda((int) $empresaA->id);

            // Faturar OS da B deve falhar (não reutiliza “caixa aberto” da A).
            [$osB] = $this->montarOsBasica($empresaB, $userB, total: 50.00, abrirCaixa: false);
            $forma = $this->criarForma('DIN MEMO '.$suffix, 'dinheiro', 'caixa');

            try {
                app(OsFaturamentoService::class)->faturar($osB, [
                    ['id' => $forma->id, 'valor' => '50.00'],
                ]);
                $this->fail('Faturamento da empresa B sem caixa não deveria passar.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('CAIXA FECHADO', $e->getMessage());
            }

            $this->assertSame(OrdemServico::SITUACAO_ABERTA, $osB->fresh()->situacao);
            $this->assertSame(0, CaixaLancamento::query()
                ->where('documento', OrdemServicoFinanceiroVinculo::documentoBase($osB))
                ->count());
        } finally {
            DB::rollBack();
        }
    }

    public function test_lock_for_update_em_duas_conexoes_serializa_a_os(): void
    {
        $ctx = $this->criarOsCommitada(total: 80.00, estoquePeca: 5.0, qtdPeca: 1.0);
        $osId = (int) $ctx['os']->id;

        $raceName = 'mysql_os_race_'.getmypid();
        config(["database.connections.{$raceName}" => config('database.connections.mysql')]);
        DB::purge($raceName);

        try {
            DB::beginTransaction();
            $locked = OrdemServico::query()->whereKey($osId)->lockForUpdate()->first();
            $this->assertInstanceOf(OrdemServico::class, $locked);

            DB::connection($raceName)->statement('SET SESSION innodb_lock_wait_timeout = 1');

            $blocked = false;
            try {
                DB::connection($raceName)->transaction(function () use ($raceName, $osId): void {
                    DB::connection($raceName)
                        ->table('ordens_servico')
                        ->where('id', $osId)
                        ->lockForUpdate()
                        ->first();
                });
            } catch (\Throwable $e) {
                $blocked = str_contains(mb_strtolower($e->getMessage()), 'lock wait timeout')
                    || str_contains(mb_strtolower($e->getMessage()), '1205');
            }

            DB::rollBack();
            $this->assertTrue($blocked, 'Segunda conexão deveria aguardar/estourar lock wait timeout.');
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge($raceName);
            $this->limparCtx($ctx);
        }
    }

    /**
     * @return array{
     *   os: OrdemServico,
     *   peca: Product,
     *   forma: FormaPagamento,
     *   empresa: Empresa,
     *   user: User,
     *   cliente: Person,
     *   movimentoId: int
     * }
     */
    private function criarOsCommitada(float $total, float $estoquePeca, float $qtdPeca): array
    {
        $suffix = (string) random_int(510000, 519999);
        $empresa = Empresa::query()->create([
            'codigo' => $suffix,
            'nome' => 'EMP OS RACE '.$suffix,
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        $geral = CaixaConta::ensureCaixaGeral();
        $movimento = CaixaLivroMovimento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $geral->id,
            'status' => CaixaLivroMovimento::STATUS_ABERTO,
            'aberto_em' => now(),
            'aberto_por' => $user->id,
            'saldo_abertura' => 0,
        ]);

        $cliente = Person::query()->create([
            'codigo' => 'CR'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS RACE '.$suffix,
            'is_cliente' => true,
            'ativo' => true,
            'cpf_cnpj' => '529.982.247-25',
        ]);

        $peca = Product::query()->create([
            'codigo' => 'PR'.$suffix,
            'descricao' => 'PECA OS RACE '.$suffix,
            'unidade' => 'UN',
            'preco_venda' => 20,
            'estoque' => $estoquePeca,
            'ativo' => true,
        ]);

        $totalProdutos = round(20 * $qtdPeca, 2);
        $totalServicos = round($total - $totalProdutos, 2);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => (string) (820000 + random_int(1, 99999)),
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
            'preco' => 20,
            'total' => $totalProdutos,
        ])->save();

        $itemServ = new OrdemServicoItem();
        $itemServ->forceFill([
            'ordem_servico_id' => $os->id,
            'empresa_id' => $empresa->id,
            'tipo' => 'S',
            'qtd' => 1,
            'preco' => $totalServicos,
            'total' => $totalServicos,
        ])->save();

        $forma = $this->criarForma('DIN RACE '.$suffix, 'dinheiro', 'caixa');

        return [
            'os' => $os->fresh(['itens.product', 'cliente']),
            'peca' => $peca,
            'forma' => $forma,
            'empresa' => $empresa,
            'user' => $user,
            'cliente' => $cliente,
            'movimentoId' => (int) $movimento->id,
        ];
    }

    /**
     * @return array{0: OrdemServico}
     */
    private function montarOsBasica(Empresa $empresa, User $user, float $total, bool $abrirCaixa): array
    {
        $suffix = (string) random_int(520000, 529999);

        if ($abrirCaixa) {
            CaixaLivroMovimento::query()->create([
                'empresa_id' => $empresa->id,
                'caixa_conta_id' => CaixaConta::ensureCaixaGeral()->id,
                'status' => CaixaLivroMovimento::STATUS_ABERTO,
                'aberto_em' => now(),
                'aberto_por' => $user->id,
                'saldo_abertura' => 0,
            ]);
        }

        $cliente = Person::query()->create([
            'codigo' => 'CB'.$suffix,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE B '.$suffix,
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => (string) (830000 + random_int(1, 99999)),
            'situacao' => OrdemServico::SITUACAO_ABERTA,
            'cliente_id' => $cliente->id,
            'total_geral' => $total,
            'total_produtos' => 0,
            'total_servicos' => $total,
        ])->save();

        $item = new OrdemServicoItem();
        $item->forceFill([
            'ordem_servico_id' => $os->id,
            'empresa_id' => $empresa->id,
            'tipo' => 'S',
            'qtd' => 1,
            'preco' => $total,
            'total' => $total,
        ])->save();

        return [$os->fresh(['itens', 'cliente'])];
    }

    private function criarForma(string $descricao, string $tipo, string $movimento): FormaPagamento
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

    /**
     * @param  array{
     *   os: OrdemServico,
     *   peca: Product,
     *   forma: FormaPagamento,
     *   empresa: Empresa,
     *   user: User,
     *   cliente: Person,
     *   movimentoId: int
     * }  $ctx
     */
    private function limparCtx(array $ctx): void
    {
        try {
            $os = OrdemServico::query()->find($ctx['os']->id);
            if ($os instanceof OrdemServico && in_array($os->situacao, [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ], true)) {
                session(['erp_empresa_id' => $ctx['empresa']->id]);
                $this->actingAs($ctx['user']);
                ErpContext::clearMemo();
                app(OsReabrirService::class)->reabrir($os->fresh());
                $os->refresh();
            }

            if ($os instanceof OrdemServico) {
                if (Schema::hasColumn($os->getTable(), 'venda_id') && $os->venda_id) {
                    $vendaId = (int) $os->venda_id;
                    DB::table('venda_itens')->where('venda_id', $vendaId)->delete();
                    Venda::query()->whereKey($vendaId)->delete();
                }

                OrdemServicoItem::query()->where('ordem_servico_id', $os->id)->delete();
                $os->delete();
            }

            FormaPagamento::query()->whereKey($ctx['forma']->id)->delete();
            Product::query()->whereKey($ctx['peca']->id)->delete();
            Person::query()->whereKey($ctx['cliente']->id)->delete();
            CaixaLivroMovimento::query()->whereKey($ctx['movimentoId'])->delete();
            User::query()->whereKey($ctx['user']->id)->delete();
            Empresa::query()->whereKey($ctx['empresa']->id)->delete();
        } catch (\Throwable $e) {
            fwrite(STDERR, '[cleanup] '.$e->getMessage().PHP_EOL);
        }
    }
}
