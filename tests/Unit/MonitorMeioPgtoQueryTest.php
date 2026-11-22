<?php

namespace Tests\Unit;

use App\Filament\Resources\ForcaVendasMonitorResource\Pages\ListForcaVendasMonitor;
use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Pedido;
use App\Models\Venda;
use App\Support\Erp\ErpSchema;
use App\Support\ForcaVendas\MonitorMeioPgtoQuery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Filtro Meio Pgto: aliases + SQL em SQLite mínimo (sem migrate:fresh).
 */
class MonitorMeioPgtoQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->criarSchemaMinimo();
        ErpSchema::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('pdv_venda_pagamentos');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('forca_vendas_orders');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('vendas');
        parent::tearDown();
    }

    public function test_forma_corresponde_aliases_scv_e_nao_crediario(): void
    {
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('Crédito', 'CARTAO CREDITO'));
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('Débito', 'CARTAO DEBITO'));
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('CARTAO CREDITO', 'CARTAO CREDITO'));
        $this->assertFalse(MonitorMeioPgtoQuery::formaCorresponde('DINHEIRO', 'CARTAO CREDITO'));
        $this->assertFalse(MonitorMeioPgtoQuery::formaCorresponde('CREDIARIO', 'CARTAO CREDITO'));
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('Dinheiro + Crédito', 'CARTAO CREDITO'));
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('Dinheiro + Crédito', 'DINHEIRO'));
        $this->assertTrue(MonitorMeioPgtoQuery::formaCorresponde('PIX', 'PIX'));
    }

    public function test_filtro_dinheiro_credito_debito_pix_misto_e_multi(): void
    {
        $dinheiro = $this->seedComPdv('DINHEIRO', [['forma' => 'DINHEIRO', 'valor' => 50]]);
        $credito = $this->seedComPdv('CARTAO CREDITO', [['forma' => 'CARTAO CREDITO', 'valor' => 80]]);
        $debito = $this->seedComPdv('CARTAO DEBITO', [['forma' => 'CARTAO DEBITO', 'valor' => 40]]);
        $pix = $this->seedComPdv('PIX', [['forma' => 'PIX', 'valor' => 25]]);
        $misto = $this->seedSoPayload('DINHEIRO / CARTAO CREDITO', [
            ['descricao' => 'DINHEIRO', 'forma' => 'DINHEIRO', 'valor' => 30],
            ['descricao' => 'CARTAO CREDITO', 'forma' => 'CARTAO CREDITO', 'valor' => 70],
        ]);

        $this->assertSameIds(['DINHEIRO'], [$dinheiro, $misto]);
        $this->assertSameIds(['CARTAO CREDITO'], [$credito, $misto]);
        $this->assertSameIds(['CARTAO DEBITO'], [$debito]);
        $this->assertSameIds(['PIX'], [$pix]);
        $this->assertSameIds(['CARTAO CREDITO', 'CARTAO DEBITO'], [$credito, $debito, $misto]);

        $multi = $this->idsFiltrados(['CARTAO CREDITO', 'CARTAO DEBITO']);
        $this->assertNotContains($dinheiro, $multi);
        $this->assertNotContains($pix, $multi);
    }

    public function test_filtro_scv_cabecalho_sem_pdv(): void
    {
        $scvCredito = $this->seedScvCabecalho('Crédito');
        $scvDebito = $this->seedScvCabecalho('Débito');
        $scvDinheiro = $this->seedScvCabecalho('Dinheiro');
        $scvMisto = $this->seedScvCabecalho('Dinheiro + Crédito');

        $this->assertSameIds(['CARTAO CREDITO'], [$scvCredito, $scvMisto]);
        $this->assertSameIds(['CARTAO DEBITO'], [$scvDebito]);
        $this->assertSameIds(['DINHEIRO'], [$scvDinheiro, $scvMisto]);
        $this->assertSameIds(['CARTAO CREDITO', 'CARTAO DEBITO'], [$scvCredito, $scvDebito, $scvMisto]);

        $this->assertNotContains($scvDinheiro, $this->idsFiltrados(['CARTAO CREDITO', 'CARTAO DEBITO']));
    }

    public function test_pdv_real_prioriza_sobre_cabecalho_divergente(): void
    {
        // Cabeçalho "Crédito" + PDV CARTAO CREDITO (padrão SCV importado).
        $id = $this->seedComPdv('Crédito', [['forma' => 'CARTAO CREDITO', 'valor' => 99]], origem: 'scv');

        $this->assertSameIds(['CARTAO CREDITO'], [$id]);
        $this->assertNotContains($id, $this->idsFiltrados(['DINHEIRO']));
    }

    public function test_parse_url_multi_preserva_pipe(): void
    {
        $valor = 'CARTAO CREDITO|CARTAO DEBITO';
        $this->assertSame(
            ['CARTAO CREDITO', 'CARTAO DEBITO'],
            ListForcaVendasMonitor::parseFiltroMeioPgtoValor($valor),
        );
        $this->assertSame($valor, ListForcaVendasMonitor::encodeFiltroMeioPgtoValor([
            'CARTAO CREDITO',
            'CARTAO DEBITO',
        ]));
    }

    /**
     * @param  list<string>  $valores
     * @param  list<int>  $esperados
     */
    private function assertSameIds(array $valores, array $esperados): void
    {
        $ids = $this->idsFiltrados($valores);
        sort($ids);
        $esperados = array_map('intval', $esperados);
        sort($esperados);
        $this->assertSame($esperados, $ids, 'filtro='.implode('|', $valores));
    }

    /**
     * @param  list<string>  $valores
     * @return list<int>
     */
    private function idsFiltrados(array $valores): array
    {
        $q = ForcaVendasOrder::query()->where('tipo', 'pedido');
        MonitorMeioPgtoQuery::apply($q, $valores);

        return $q->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @param  list<array{forma: string, valor: float|int}>  $pagamentos
     */
    private function seedComPdv(string $cabecalho, array $pagamentos, string $origem = 'monitor_tela_venda'): int
    {
        $vendaId = (int) DB::table('vendas')->insertGetId([
            'forma_pagamento' => $cabecalho,
            'total' => 100,
        ]);

        $pdvId = (int) DB::table('pdv_vendas')->insertGetId([
            'venda_id' => $vendaId,
            'forma_pagamento' => $cabecalho,
            'origem' => null,
            'total' => 100,
        ]);

        foreach ($pagamentos as $pag) {
            DB::table('pdv_venda_pagamentos')->insert([
                'pdv_venda_id' => $pdvId,
                'forma' => $pag['forma'],
                'valor' => $pag['valor'],
            ]);
        }

        $payloadPags = array_map(static fn (array $p): array => [
            'descricao' => $p['forma'],
            'forma_pagamento' => $p['forma'],
            'forma' => $p['forma'],
            'valor' => $p['valor'],
            'tipo' => 'dinheiro',
        ], $pagamentos);

        return (int) DB::table('forca_vendas_orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tipo' => 'pedido',
            'venda_id' => $vendaId,
            'pedido_id' => null,
            'total' => 100,
            'payload' => json_encode([
                'origem' => $origem,
                'forma_pagamento' => $cabecalho,
                'pagamentos' => $payloadPags,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @param  list<array{descricao: string, forma: string, valor: float|int}>  $pagamentos
     */
    private function seedSoPayload(string $cabecalho, array $pagamentos): int
    {
        $vendaId = (int) DB::table('vendas')->insertGetId([
            'forma_pagamento' => $cabecalho,
            'total' => 100,
        ]);

        return (int) DB::table('forca_vendas_orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tipo' => 'pedido',
            'venda_id' => $vendaId,
            'pedido_id' => null,
            'total' => 100,
            'payload' => json_encode([
                'origem' => 'monitor_tela_venda',
                'forma_pagamento' => $cabecalho,
                'pagamentos' => $pagamentos,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function seedScvCabecalho(string $cabecalho): int
    {
        $pedidoId = (int) DB::table('pedidos')->insertGetId([
            'forma_pagamento' => $cabecalho,
            'total' => 100,
        ]);
        $vendaId = (int) DB::table('vendas')->insertGetId([
            'forma_pagamento' => 'DIVERSOS',
            'total' => 100,
        ]);

        return (int) DB::table('forca_vendas_orders')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'tipo' => 'pedido',
            'venda_id' => $vendaId,
            'pedido_id' => $pedidoId,
            'total' => 100,
            'payload' => json_encode([
                'origem' => 'scv',
                'forma_pagamento' => $cabecalho,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function criarSchemaMinimo(): void
    {
        Schema::dropIfExists('pdv_venda_pagamentos');
        Schema::dropIfExists('pdv_vendas');
        Schema::dropIfExists('forca_vendas_orders');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('vendas');

        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->string('forma_pagamento')->nullable();
            $table->decimal('total', 12, 2)->default(0);
        });

        Schema::create('pedidos', function (Blueprint $table): void {
            $table->id();
            $table->string('forma_pagamento')->nullable();
            $table->decimal('total', 12, 2)->default(0);
        });

        Schema::create('forca_vendas_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->nullable();
            $table->string('tipo', 20)->default('pedido');
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('pedido_id')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->json('payload')->nullable();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->string('origem')->nullable();
            $table->decimal('total', 12, 2)->default(0);
        });

        Schema::create('pdv_venda_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('forma', 60);
            $table->decimal('valor', 12, 2);
        });

        // Garante models apontando para as tabelas criadas.
        $this->assertSame('vendas', (new Venda)->getTable());
        $this->assertSame('pedidos', (new Pedido)->getTable());
        $this->assertSame('forca_vendas_orders', (new ForcaVendasOrder)->getTable());
        $this->assertSame('pdv_vendas', (new PdvVenda)->getTable());
        $this->assertSame('pdv_venda_pagamentos', (new PdvVendaPagamento)->getTable());
    }
}
