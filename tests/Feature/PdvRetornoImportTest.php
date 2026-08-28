<?php

namespace Tests\Feature;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PdvRetornoImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('pdv_carga.default_empresa_id', 1);

        $empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA TESTE',
            'ativo' => true,
        ]);

        Terminal::query()->create([
            'empresa_id' => $empresa->id,
            'nome' => 'CAIXA-1',
            'numero_logico_terminal' => 1,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
        ]);
    }

    private function payload(string $uuid, int $productId): array
    {
        return [
            'empresa_id' => 1,
            'terminal' => 'CAIXA-1',
            'vendas' => [[
                'uuid' => $uuid,
                'numero' => 1,
                'serie' => '1',
                'subtotal' => 20.0,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => 20.0,
                'cliente_documento' => '12345678909',
                'itens' => [[
                    'product_central_id' => $productId,
                    'codigo' => 'P1',
                    'descricao' => 'PRODUTO 1',
                    'unidade' => 'UN',
                    'quantidade' => 2,
                    'preco_unitario' => 10.0,
                    'total' => 20.0,
                ]],
                'pagamentos' => [
                    ['forma' => 'DINHEIRO', 'valor' => 20.0],
                ],
                'nfce' => [
                    'operacao' => 'VENDA',
                    'serie' => '1',
                    'numero' => 10,
                    'cnf' => '12345678',
                    'chave' => str_repeat('1', 44),
                    'status' => 'contingencia',
                    'ambiente' => 1,
                    'tipo_emissao' => '9',
                    'qr_code_conteudo' => 'https://qr',
                    'xml' => '<nfe/>',
                ],
            ]],
        ];
    }

    private function enviar(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/pdv/retorno', $payload);
    }

    public function test_importa_venda_baixa_estoque_e_registra_nfce(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $uuid = (string) Str::uuid();

        $res = $this->enviar($this->payload($uuid, $product->id));

        $res->assertOk();
        $res->assertJsonPath('results.0.status', 'importado');

        $venda = PdvVenda::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($venda);
        $this->assertSame('pdv_offline', $venda->origem);
        $this->assertSame('20.00', (string) $venda->total);
        $this->assertSame('F', $venda->situacao);
        $this->assertCount(1, $venda->itens);
        $this->assertCount(1, $venda->pagamentos);

        $this->assertSame(98.0, (float) $product->fresh()->estoque);

        $nfce = PdvVendaNfce::query()->where('pdv_venda_id', $venda->id)->first();
        $this->assertNotNull($nfce);
        $this->assertSame(str_repeat('1', 44), $nfce->chave);
        $this->assertSame('contingencia', $nfce->status);

        $venda->refresh();
        $this->assertNotNull($venda->venda_id);
        $this->assertNotNull(Venda::query()->find($venda->venda_id));
        $this->assertSame(
            1,
            PdvCaixaMovimento::query()->where('pdv_venda_id', $venda->id)->where('tipo', 'venda')->count(),
        );
        $this->assertSame(0, ContaReceber::query()->where('documento', 'like', 'PDV-%')->count());
    }

    public function test_pagamento_a_prazo_gera_conta_a_receber(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $payload = $this->payload((string) Str::uuid(), $product->id);
        $payload['vendas'][0]['pagamentos'] = [['forma' => 'CREDIÁRIO', 'valor' => 20.0]];
        unset($payload['vendas'][0]['nfce']);

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        $this->assertGreaterThanOrEqual(
            1,
            ContaReceber::query()->where('documento', 'like', 'PDV-%')->count(),
        );
    }

    public function test_crediario_sem_acento_e_tipo_gera_conta_a_receber(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $payload = $this->payload((string) Str::uuid(), $product->id);
        $payload['vendas'][0]['pagamentos'] = [
            ['forma' => 'CREDIARIO', 'tipo' => 'crediario', 'valor' => 16.0],
        ];
        unset($payload['vendas'][0]['nfce']);

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        $this->assertSame(1, ContaReceber::query()->where('documento', 'like', 'PDV-%')->count());
    }

    public function test_reenvio_duplicado_gera_conta_se_ainda_nao_existir(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $payload = $this->payload((string) Str::uuid(), $product->id);
        $payload['vendas'][0]['pagamentos'] = [
            ['forma' => 'CREDIARIO', 'tipo' => 'crediario', 'valor' => 16.0],
        ];
        unset($payload['vendas'][0]['nfce']);

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        ContaReceber::query()->delete();
        $this->assertSame(0, ContaReceber::query()->count());

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'duplicado');
        $this->assertSame(1, ContaReceber::query()->where('documento', 'like', 'PDV-%')->count());

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'duplicado');
        $this->assertSame(1, ContaReceber::query()->where('documento', 'like', 'PDV-%')->count());
    }

    public function test_reenvio_e_idempotente_nao_duplica_nem_baixa_estoque_de_novo(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $uuid = (string) Str::uuid();
        $payload = $this->payload($uuid, $product->id);

        $this->enviar($payload)->assertOk();
        $res2 = $this->enviar($payload);

        $res2->assertOk();
        $res2->assertJsonPath('results.0.status', 'duplicado');

        $this->assertSame(1, PdvVenda::query()->where('uuid', $uuid)->count());
        $this->assertSame(98.0, (float) $product->fresh()->estoque);
    }

    public function test_reenvio_atualiza_nfce_quando_status_muda(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $uuid = (string) Str::uuid();
        $payload = $this->payload($uuid, $product->id);
        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        $payload['vendas'][0]['nfce']['status'] = 'autorizada';
        $payload['vendas'][0]['nfce']['protocolo'] = '123456789012345';
        $payload['vendas'][0]['nfce']['tipo_emissao'] = '9';
        $payload['vendas'][0]['nfce']['xml'] = '<nfe autorizada/>';

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'duplicado');

        $venda = PdvVenda::query()->where('uuid', $uuid)->first();
        $nfce = PdvVendaNfce::query()->where('pdv_venda_id', $venda->id)->first();

        $this->assertSame(1, PdvVendaNfce::query()->where('pdv_venda_id', $venda->id)->count());
        $this->assertSame('autorizada', $nfce->status);
        $this->assertSame('123456789012345', $nfce->protocolo);
        $this->assertSame('<nfe autorizada/>', $nfce->xml);
        $this->assertSame(98.0, (float) $product->fresh()->estoque);
    }

    public function test_retorno_estorno_apos_importacao_cancela_e_devolve_estoque(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $uuid = (string) Str::uuid();
        $payload = $this->payload($uuid, $product->id);
        unset($payload['vendas'][0]['nfce']);

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');
        $this->assertSame(98.0, (float) $product->fresh()->estoque);

        $motivo = 'Cliente desistiu da compra apos a entrega da mercadoria';
        $payload['vendas'][0]['situacao'] = 'C';
        $payload['vendas'][0]['status'] = 'cancelada';
        $payload['vendas'][0]['motivo_estorno'] = $motivo;

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        $venda = PdvVenda::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($venda);
        $this->assertSame('C', $venda->situacao);
        $this->assertSame($motivo, $venda->motivo_estorno);
        $this->assertSame(100.0, (float) $product->fresh()->estoque);
        $this->assertSame(
            1,
            PdvCaixaMovimento::query()->where('pdv_venda_id', $venda->id)->where('tipo', 'estorno')->count(),
        );

        $retaguarda = Venda::query()->find($venda->venda_id);
        $this->assertNotNull($retaguarda);
        $this->assertSame(Venda::STATUS_CANCELADO, $retaguarda->status);

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'duplicado');
        $this->assertSame(100.0, (float) $product->fresh()->estoque);
    }

    public function test_retorno_ja_cancelado_na_primeira_carga_fica_cancelado_com_estoque_net_zero(): void
    {
        User::factory()->create();
        $product = Product::query()->create([
            'codigo' => 'P1',
            'descricao' => 'PRODUTO 1',
            'preco_venda' => 10,
            'estoque' => 100,
            'ativo' => true,
        ]);

        $uuid = (string) Str::uuid();
        $payload = $this->payload($uuid, $product->id);
        unset($payload['vendas'][0]['nfce']);
        $payload['vendas'][0]['situacao'] = 'C';
        $payload['vendas'][0]['status'] = 'cancelada';
        $payload['vendas'][0]['motivo_estorno'] = 'Estorno antes do primeiro retorno ao ERP central';

        $this->enviar($payload)->assertOk()->assertJsonPath('results.0.status', 'importado');

        $venda = PdvVenda::query()->where('uuid', $uuid)->first();
        $this->assertNotNull($venda);
        $this->assertSame('C', $venda->situacao);
        $this->assertSame(100.0, (float) $product->fresh()->estoque);
        $this->assertSame(
            1,
            PdvCaixaMovimento::query()->where('pdv_venda_id', $venda->id)->where('tipo', 'venda')->count(),
        );
        $this->assertSame(
            1,
            PdvCaixaMovimento::query()->where('pdv_venda_id', $venda->id)->where('tipo', 'estorno')->count(),
        );
    }

    public function test_exige_terminal_ativo(): void
    {
        Terminal::query()->where('nome', 'CAIXA-1')->update(['ativo' => false]);

        $this->postJson('/api/v1/pdv/retorno', [
            'empresa_id' => 1,
            'terminal' => 'CAIXA-1',
            'vendas' => [],
        ])->assertForbidden();
    }
}
