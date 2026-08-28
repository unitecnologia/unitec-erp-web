<?php

namespace Tests\Unit;

use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\User;
use App\Models\Venda;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VendasPorFormaPagamentoAggregatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('vendas')) {
            $this->criarSchemaSqlite();
        }
    }

    public function test_forma_base_remove_sufixo_de_parcela(): void
    {
        $this->assertSame('PIX', VendasPorFormaPagamentoAggregator::formaBase('PIX (10x)'));
        $this->assertSame('DINHEIRO', VendasPorFormaPagamentoAggregator::formaBase('DINHEIRO'));
    }

    public function test_forma_mista_detecta_combinacao(): void
    {
        $this->assertTrue(VendasPorFormaPagamentoAggregator::isFormaMista('DINHEIRO / PIX'));
        $this->assertFalse(VendasPorFormaPagamentoAggregator::isFormaMista('PIX'));
    }

    public function test_venda_mista_soma_nas_formas_reais_sem_linha_combinada(): void
    {
        $data = Carbon::parse('2099-08-26');
        $this->criarVendaPdvMista($data, 10.00, 7.07);

        $this->criarVendaCabecalho($data, 'PIX', 100.00);
        $this->criarVendaCabecalho($data, 'DINHEIRO / PIX', 99.00);

        $rows = VendasPorFormaPagamentoAggregator::aggregate($data, $data);
        $porForma = $this->indexarPorForma($rows);

        $this->assertArrayNotHasKey('DINHEIRO / PIX', $porForma);
        $this->assertSame(10.00, round((float) $porForma['DINHEIRO']['total'], 2));
        $this->assertSame(107.07, round((float) $porForma['PIX']['total'], 2));
        $this->assertSame(1.0, (float) $porForma['DINHEIRO']['qtd']);
        $this->assertSame(2.0, (float) $porForma['PIX']['qtd']);
    }

    public function test_filtro_pix_inclui_parte_pix_da_venda_mista(): void
    {
        $data = Carbon::parse('2099-08-27');
        $this->criarVendaPdvMista($data, 10.00, 7.07);

        $rows = VendasPorFormaPagamentoAggregator::aggregate($data, $data, formaFiltro: 'PIX');
        $porForma = $this->indexarPorForma($rows);

        $this->assertArrayHasKey('PIX', $porForma);
        $this->assertArrayNotHasKey('DINHEIRO', $porForma);
        $this->assertArrayNotHasKey('DINHEIRO / PIX', $porForma);
        $this->assertSame(7.07, round((float) $porForma['PIX']['total'], 2));
    }

    /**
     * @param  list<array{forma: string, qtd: float, total: float}>  $rows
     * @return array<string, array{forma: string, qtd: float, total: float}>
     */
    private function indexarPorForma(array $rows): array
    {
        $indexed = [];

        foreach ($rows as $row) {
            $indexed[mb_strtoupper((string) $row['forma'], 'UTF-8')] = $row;
        }

        return $indexed;
    }

    private function criarVendaPdvMista(Carbon $data, float $dinheiro, float $pix): void
    {
        $cliente = $this->criarCliente();
        $user = User::factory()->create();
        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $user->id,
            'valor_abertura' => 0,
            'aberto_em' => $data->copy()->setTime(10, 0, 0),
        ]);

        $total = round($dinheiro + $pix, 2);
        $venda = Venda::query()->create([
            'numero' => $this->numeroUnico(),
            'data' => $data->toDateString(),
            'hora' => '10:00:00',
            'cliente_id' => $cliente->id,
            'total' => $total,
            'forma_pagamento' => 'DINHEIRO / PIX',
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_CUPOM,
            'plataforma' => Venda::PLATAFORMA_PDV,
        ]);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => $sessao->id,
            'user_id' => $user->id,
            'venda_id' => $venda->id,
            'person_id' => $cliente->id,
            'numero' => 1,
            'subtotal' => $total,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => $total,
            'forma_pagamento' => 'DINHEIRO / PIX',
            'situacao' => 'F',
        ]);

        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'forma' => 'DINHEIRO',
            'valor' => $dinheiro,
        ]);
        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'forma' => 'PIX',
            'valor' => $pix,
        ]);
    }

    private function criarVendaCabecalho(Carbon $data, string $forma, float $total): void
    {
        Venda::query()->create([
            'numero' => $this->numeroUnico(),
            'data' => $data->toDateString(),
            'hora' => '11:00:00',
            'cliente_id' => $this->criarCliente()->id,
            'total' => $total,
            'forma_pagamento' => $forma,
            'status' => Venda::STATUS_FECHADO,
            'tipo' => Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_ERP,
        ]);
    }

    private function criarCliente(): Person
    {
        return Person::query()->create([
            'codigo' => $this->codigoUnico(),
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE FORMA PGTO TESTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }

    private function criarSchemaSqlite(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('ativo')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('pessoa_tipo', 20);
            $table->string('nome_razao');
            $table->boolean('is_cliente')->default(false);
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('numero', 20)->unique();
            $table->date('data');
            $table->time('hora')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->decimal('total', 15, 2)->default(0);
            $table->string('forma_pagamento')->nullable();
            $table->string('status', 20)->default('aberto');
            $table->string('tipo', 20)->default('pedido');
            $table->string('plataforma', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('pdv_caixa_sessoes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('valor_abertura', 12, 2)->default(0);
            $table->timestamp('aberto_em');
            $table->timestamps();
        });

        Schema::create('pdv_vendas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_caixa_sessao_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('venda_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedInteger('numero');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('desconto', 12, 2)->default(0);
            $table->decimal('acrescimo', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('forma_pagamento');
            $table->string('situacao', 1)->default('F');
            $table->timestamps();
        });

        Schema::create('pdv_venda_pagamentos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pdv_venda_id');
            $table->string('forma', 30);
            $table->decimal('valor', 12, 2);
            $table->timestamps();
        });
    }

    private function numeroUnico(): string
    {
        return 'FP'.bin2hex(random_bytes(6));
    }

    private function codigoUnico(): string
    {
        return 'C'.bin2hex(random_bytes(5));
    }
}
