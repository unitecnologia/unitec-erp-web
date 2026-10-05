<?php

namespace Tests\Feature;

use App\Models\AjusteEstoque;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\EstoqueMovimentacao;
use App\Models\InventarioContagem;
use App\Models\InventarioContagemItem;
use App\Models\InventarioEtapa;
use App\Models\Product;
use App\Models\ProductEstoqueSaldo;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Inventario\InventarioAcesso;
use App\Support\Inventario\InventarioContagemService;
use App\Support\Inventario\InventarioException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class InventarioContagemTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_salvamento_imediato_aplica_a_diferenca_e_nao_zera_o_resto(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $contado = $this->produto('INV-TOTAL', 12, $estoque->id);
        $fora = $this->produto('INV-FORA', 3, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);

        $resultado = $this->contar($service, $user, (int) $contado->id, '10');

        $this->assertTrue($resultado['aplicado']);
        $this->assertFalse($resultado['repetido']);
        $this->assertEquals(10, $this->saldo($contado->id, $estoque->id));
        $this->assertEquals(3, $this->saldo($fora->id, $estoque->id));
        $this->assertSame(0, InventarioContagemItem::query()->where('product_id', $fora->id)->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)->count());

        $item = InventarioContagemItem::query()->where('product_id', $contado->id)->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)->first();
        $this->assertEquals(12, (float) $item->saldo_referencia);
        $this->assertEquals(10, (float) $item->quantidade_contada);
        $this->assertEquals(-2, (float) $item->diferenca);
        $this->assertEquals(12, (float) $item->saldo_antes);
        $this->assertEquals(10, (float) $item->saldo_depois);
        $this->assertNotNull($item->contado_em);
        $this->assertSame($user->id, (int) $item->user_id);
        $ajuste = AjusteEstoque::query()->find($item->ajuste_estoque_id);
        $this->assertEquals(-2, (float) $ajuste->qtd_ajust);
        $this->assertSame('Corredor A', $item->etapa()->value('nome'));
    }

    public function test_venda_e_entrada_posteriores_permanecem_ao_fechar(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-VENDA', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $saldos = app(ProductEstoqueSaldoService::class);
        $this->abrirTrabalho($service, $user);

        $this->contar($service, $user, (int) $product->id, '10');
        $this->assertEquals(10, $this->saldo($product->id, $estoque->id));

        $saldos->incrementar(
            (int) $product->id,
            4,
            (int) $estoque->id,
            $empresa,
            EstoqueMovimentacaoContext::make(EstoqueMovimentacao::TIPO_ENTRADA_COMPRA),
        );
        $saldos->decrementar(
            (int) $product->id,
            3,
            (int) $estoque->id,
            $empresa,
            EstoqueMovimentacaoContext::make(EstoqueMovimentacao::TIPO_VENDA),
        );
        $this->assertEquals(11, $this->saldo($product->id, $estoque->id));

        $service->fecharEtapa($user);
        $service->encerrarBalanco($user);

        $this->assertEquals(11, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
        $this->assertSame(InventarioContagem::STATUS_ENCERRADO, InventarioContagem::query()->where('user_id', $user->id)->value('status'));
        $this->assertSame(InventarioEtapa::STATUS_FECHADA, InventarioEtapa::query()->where('user_id', $user->id)->value('status'));
    }

    public function test_movimentacao_durante_a_contagem_nao_aplica(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-JANELA', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);
        $leitura = $service->abrirProduto($user, (int) $product->id);

        app(ProductEstoqueSaldoService::class)->decrementar(
            (int) $product->id,
            3,
            (int) $estoque->id,
            $empresa,
            EstoqueMovimentacaoContext::make(EstoqueMovimentacao::TIPO_VENDA),
        );

        try {
            $service->salvar($user, (int) $product->id, '10', $leitura['token'], $leitura['cursor']);
            $this->fail('Movimento durante a contagem deveria impedir o ajuste.');
        } catch (InventarioException $e) {
            $this->assertStringContainsString('Nenhum ajuste foi aplicado', $e->getMessage());
            $this->assertEquals(9, $this->saldo($product->id, $estoque->id));
            $this->assertSame(0, AjusteEstoque::query()->where('product_id', $product->id)->count());
        }

        $this->contar($service, $user, (int) $product->id, '8');
        $this->assertEquals(8, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
    }

    public function test_repetir_salvamento_nao_duplica_movimento(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-RETRY', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);
        $leitura = $service->abrirProduto($user, (int) $product->id);

        $primeiro = $service->salvar($user, (int) $product->id, '10', $leitura['token'], $leitura['cursor']);
        $segundo = $service->salvar($user, (int) $product->id, '4', $leitura['token'], $leitura['cursor']);

        $this->assertFalse($primeiro['repetido']);
        $this->assertTrue($segundo['repetido']);
        $this->assertSame($primeiro['id'], $segundo['id']);
        $this->assertEquals(10, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
        $this->assertSame(1, InventarioContagemItem::query()->where('product_id', $product->id)->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)->count());
    }

    public function test_retoma_etapa_e_fechamento_nao_reaplica(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-RETOMA', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);
        $leitura = $service->abrirProduto($user, (int) $product->id);
        $service->salvar($user, (int) $product->id, '10', $leitura['token'], $leitura['cursor']);
        $pendente = $this->produto('INV-ABERTO', 5, $estoque->id);
        $leituraPendente = $service->abrirProduto($user, (int) $pendente->id);

        $resumo = $service->resumo($user);
        $this->assertSame('Corredor A', $resumo['etapa_aberta']);
        $this->assertCount(1, $service->itensEtapa($user));
        $this->assertEquals(10, $this->saldo($product->id, $estoque->id));

        $service->fecharEtapa($user);
        $repetido = $service->salvar($user, (int) $product->id, '1', $leitura['token'], $leitura['cursor']);
        $this->assertTrue($repetido['repetido']);

        try {
            $service->salvar($user, (int) $pendente->id, '1', $leituraPendente['token'], $leituraPendente['cursor']);
            $this->fail('Setor fechado não pode gravar um registro novo.');
        } catch (InventarioException $e) {
            $this->assertStringContainsString('Setor fechado', $e->getMessage());
            $this->assertEquals(5, $this->saldo($pendente->id, $estoque->id));
        }

        $service->encerrarBalanco($user);
        $historico = $service->historico($user);
        $linhas = $service->historicoItens($user, (int) $historico[0]['id']);

        $this->assertEquals(10, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
        $this->assertSame('Corredor A', $linhas[0]['setor']);
        $this->assertSame('10,000', $linhas[0]['contada']);
        $this->assertSame('12,000', $linhas[0]['saldo']);
        $this->assertSame('-2,000', $linhas[0]['diferenca']);
        $this->assertSame('12,000', $linhas[0]['saldo_antes']);
        $this->assertSame('10,000', $linhas[0]['saldo_depois']);
        $this->assertNotSame('', $linhas[0]['movimento']);
        $this->assertNotSame('', $linhas[0]['horario']);
    }

    public function test_fechar_etapa_nao_consulta_estoque_nem_reaplica(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-FECHA', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);
        $this->contar($service, $user, (int) $product->id, '10');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $service->fecharEtapa($user);
        $sql = strtolower(implode("\n", array_column(DB::getQueryLog(), 'query')));
        DB::disableQueryLog();

        $this->assertStringNotContainsString('product_estoque_saldos', $sql);
        $this->assertStringNotContainsString('ajustes_estoque', $sql);
        $this->assertStringNotContainsString('estoque_movimentacoes', $sql);
        $this->assertStringNotContainsString('inventario_contagem_itens', $sql);
        $this->assertEquals(10, $this->saldo($product->id, $estoque->id));
        $this->assertSame(1, AjusteEstoque::query()->where('product_id', $product->id)->count());
    }

    public function test_busca_limita_e_nao_dispara_com_termo_curto(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $this->produto('INV-BUSCA', 1, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);

        $this->assertSame([], $service->buscar($user, 'A'));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $lista = $service->buscar($user, 'INV-BUSCA');
        $consultas = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(20, count($lista));
        $produto = implode("\n", $consultas);
        $this->assertStringContainsString('limit 20', strtolower($produto));
        $this->assertStringNotContainsString('product_estoque_saldos', strtolower($produto));
    }

    public function test_nova_contagem_do_mesmo_produto_gera_outro_registro(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        $product = $this->produto('INV-NOVO', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);

        $this->contar($service, $user, (int) $product->id, '10');
        $this->contar($service, $user, (int) $product->id, '9');

        $itens = InventarioContagemItem::query()
            ->where('product_id', $product->id)
            ->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $itens);
        $this->assertEquals(10, (float) $itens[0]->quantidade_contada);
        $this->assertEquals(9, (float) $itens[1]->quantidade_contada);
        $this->assertEquals(9, $this->saldo($product->id, $estoque->id));
        $this->assertSame(2, AjusteEstoque::query()->where('product_id', $product->id)->count());
    }

    public function test_somente_contagem_nao_movimenta_estoque(): void
    {
        [$empresa, $estoque, $user] = $this->cenario();
        $this->conceder($user, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM]);
        $product = $this->produto('INV-SEM', 12, $estoque->id);
        $this->entrar($user, $empresa->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $user);
        $leitura = $service->abrirProduto($user, (int) $product->id);

        try {
            $service->salvar($user, (int) $product->id, '10', $leitura['token'], $leitura['cursor']);
            $this->fail('Contar sem permissão de aplicar não pode ajustar o estoque.');
        } catch (AuthorizationException) {
            $this->assertEquals(12, $this->saldo($product->id, $estoque->id));
            $this->assertSame(0, AjusteEstoque::query()->where('product_id', $product->id)->count());
            $this->assertSame(0, InventarioContagemItem::query()->where('product_id', $product->id)->where('situacao', InventarioContagemItem::SITUACAO_APLICADO)->count());
        }
    }

    public function test_separa_empresas_e_permissao_revogada(): void
    {
        [$empresaA, $estoqueA, $admin] = $this->cenario(admin: true);
        $empresaB = Empresa::query()->create([
            'codigo' => 'B'.random_int(100, 999),
            'nome' => 'EMPRESA B',
            'fantasia' => 'EMPRESA B',
            'ativo' => true,
        ]);
        $estoqueB = Estoque::query()->create([
            'empresa_id' => $empresaB->id,
            'codigo' => '1',
            'nome' => 'DEPOSITO B',
            'ativo' => true,
        ]);
        $product = $this->produto('INV-AB', 6, $estoqueA->id);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueB->id,
            'quantidade' => 6,
        ]);

        $this->entrar($admin, $empresaA->id);
        $service = app(InventarioContagemService::class);
        $this->abrirTrabalho($service, $admin);
        $this->contar($service, $admin, (int) $product->id, '2');
        $this->assertEquals(2, $this->saldo($product->id, $estoqueA->id));

        $this->entrar($admin, $empresaB->id);
        $this->assertFalse($service->resumo($admin)['aberto']);
        $this->assertSame([], $service->itensEtapa($admin));

        try {
            $service->encerrarBalanco($admin);
            $this->fail('Encerrar na empresa B não pode mexer no balanço da empresa A.');
        } catch (InventarioException) {
            $this->assertEquals(2, $this->saldo($product->id, $estoqueA->id));
            $this->assertSame(InventarioContagem::STATUS_ABERTO, InventarioContagem::query()->where('empresa_id', $empresaA->id)->value('status'));
        }

        $contador = User::factory()->create([
            'empresa_id' => $empresaA->id,
            'is_admin' => false,
            'ativo' => true,
            'acesso_app_inventario' => true,
        ]);
        $this->conceder($contador, [InventarioAcesso::CONSULTA, InventarioAcesso::CONTAGEM, InventarioAcesso::FINALIZAR]);
        ErpAccess::storeInSession($contador, $contador->effectivePermissionKeys());
        DB::table('user_permissions')->where('user_id', $contador->id)->where('permission_key', InventarioAcesso::FINALIZAR)->delete();
        $this->entrar($contador, $empresaA->id);
        $leitura = $service->abrirProduto($contador, (int) $product->id);

        try {
            $service->salvar($contador, (int) $product->id, '1', $leitura['token'], $leitura['cursor']);
            $this->fail('Permissão de aplicar revogada no banco deve bloquear o salvamento.');
        } catch (AuthorizationException) {
            $this->assertEquals(2, $this->saldo($product->id, $estoqueA->id));
        }
    }

    private function abrirTrabalho(InventarioContagemService $service, User $user): void
    {
        $service->abrirBalanco($user);
        $service->criarEtapa($user, 'Corredor A');
    }

    /**
     * @return array<string, mixed>
     */
    private function contar(InventarioContagemService $service, User $user, int $productId, mixed $quantidade): array
    {
        $leitura = $service->abrirProduto($user, $productId);

        return $service->salvar($user, $productId, $quantidade, $leitura['token'], $leitura['cursor']);
    }

    /**
     * @param  list<string>  $chaves
     */
    private function conceder(User $user, array $chaves): void
    {
        $now = now();
        DB::table('user_permissions')->insert(array_map(fn (string $chave): array => [
            'user_id' => $user->id,
            'permission_key' => $chave,
            'created_at' => $now,
            'updated_at' => $now,
        ], $chaves));
        ErpAccess::forgetSession();
    }

    /**
     * @return array{0: Empresa, 1: Estoque, 2: User}
     */
    private function cenario(bool $admin = false): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => (string) random_int(1000, 9999),
            'nome' => 'EMPRESA INV',
            'fantasia' => 'EMPRESA INV',
            'ativo' => true,
        ]);
        $estoque = Estoque::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => '1',
            'nome' => 'DEPOSITO',
            'ativo' => true,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => $admin,
            'ativo' => true,
            'acesso_app_inventario' => true,
        ]);

        return [$empresa, $estoque, $user];
    }

    private function produto(string $codigo, float $quantidade, int $estoqueId): Product
    {
        $codigo .= random_int(10, 99);
        $product = Product::query()->create([
            'codigo' => $codigo,
            'descricao' => 'PRODUTO '.$codigo,
            'unidade' => 'UN',
            'estoque' => $quantidade,
            'preco_venda' => 1,
            'ativo' => true,
        ]);
        ProductEstoqueSaldo::query()->create([
            'product_id' => $product->id,
            'estoque_id' => $estoqueId,
            'quantidade' => $quantidade,
        ]);

        return $product;
    }

    private function saldo(int $productId, int $estoqueId): float
    {
        return (float) ProductEstoqueSaldo::query()
            ->where('product_id', $productId)
            ->where('estoque_id', $estoqueId)
            ->value('quantidade');
    }

    private function entrar(User $user, int $empresaId): void
    {
        Auth::login($user);
        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();
    }
}
