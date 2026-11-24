<?php

namespace Tests\Unit;

use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Reports\Tabular\Definitions\PosicaoEstoqueReport;
use Illuminate\Http\Request;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class PosicaoEstoqueReportTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_percentual_usa_apenas_valor_positivo_e_separa_negativos(): void
    {
        Product::query()->create($this->produto([
            'codigo' => 'PE-A',
            'descricao' => 'PRODUTO A POSICAO',
            'estoque' => 10,
            'preco_custo' => 5,
            'preco_venda' => 12,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-B',
            'descricao' => 'PRODUTO B POSICAO',
            'estoque' => 4,
            'preco_custo' => 0,
            'preco_compra' => 2.5,
            'preco_venda' => 8,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-C',
            'descricao' => 'PRODUTO C SEM CUSTO',
            'estoque' => 8,
            'preco_custo' => 0,
            'preco_compra' => 0,
            'preco_venda' => 0,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-N',
            'descricao' => 'PRODUTO NEGATIVO',
            'estoque' => -3,
            'preco_custo' => 10,
            'preco_venda' => 20,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-SVC',
            'descricao' => 'SERVICO FLAG',
            'is_servico' => true,
            'estoque' => 99,
            'preco_custo' => 100,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-T09',
            'descricao' => 'SERVICO TIPO 09',
            'tipo_produto' => '09',
            'estoque' => 50,
            'preco_custo' => 20,
        ]));

        $built = $this->report()->build($this->request([
            'situacao' => 'todos',
            'ordem' => 'valor',
        ]));

        $byCodigo = collect($built['rows'])->keyBy('codigo');

        $this->assertArrayHasKey('PE-A', $byCodigo);
        $this->assertArrayHasKey('PE-N', $byCodigo);
        $this->assertArrayNotHasKey('PE-SVC', $byCodigo);
        $this->assertArrayNotHasKey('PE-T09', $byCodigo);

        // Positivos valorizados: 50 + 10 = 60 (C sem custo e N negativo fora do denominador).
        $this->assertSame('83,33%', $byCodigo['PE-A']['participacao']);
        $this->assertSame('16,67%', $byCodigo['PE-B']['participacao']);
        $this->assertSame('0,00%', $byCodigo['PE-C']['participacao']);
        $this->assertSame('', $byCodigo['PE-N']['participacao']);
        $this->assertSame('-30,00', $byCodigo['PE-N']['valor']);
        $this->assertSame('12,00', $byCodigo['PE-A']['venda_unitario']);
        $this->assertSame('120,00', $byCodigo['PE-A']['venda_total']);
        $this->assertSame('8,00', $byCodigo['PE-B']['venda_unitario']);
        $this->assertSame('32,00', $byCodigo['PE-B']['venda_total']);
        $this->assertSame('', $byCodigo['PE-C']['venda_unitario']);
        $this->assertSame('0,00', $byCodigo['PE-C']['venda_total']);
        $this->assertSame('20,00', $byCodigo['PE-N']['venda_unitario']);
        $this->assertSame('-60,00', $byCodigo['PE-N']['venda_total']);

        $this->assertSame('CUSTO TOTAL', $this->report()->columns()['valor']);
        $this->assertSame(['codigo', 'descricao', 'quantidade', 'custo_unitario', 'valor', 'venda_unitario', 'venda_total', 'participacao'], $built['columns']);
        $this->assertSame('14,00', $built['totals']['quantidade']);
        $this->assertSame('60,00', $built['totals']['valor']);
        $this->assertSame('152,00', $built['totals']['venda_total']);
        $this->assertSame('100,00%', $built['totals']['participacao']);
        $this->assertSame('', $built['totals']['custo_unitario']);
        $this->assertSame('', $built['totals']['venda_unitario']);
        $this->assertSame('CUSTO EST. POSITIVO', $built['totals']['codigo']);
        $this->assertSame('todos', $built['filters']['situacao']);

        $this->assertTrue(collect($built['summary'])->contains(
            fn (string $linha): bool => str_contains($linha, 'SEM CUSTO CONFIÁVEL') && str_contains($linha, '1')
        ));
        $this->assertTrue(collect($built['summary'])->contains(
            fn (string $linha): bool => str_contains($linha, 'VALORIZAÇÃO PARCIAL')
        ));
        $this->assertFalse(collect($built['summary'])->contains(
            fn (string $linha): bool => str_contains($linha, 'ESTOQUE FÍSICO')
                || str_contains($linha, 'CUSTO TOTAL =')
                || str_contains($linha, 'is_servico')
        ));

        $resumo = $built['extra_sections'][0]['rows'] ?? [];
        $this->assertSame('CUSTO DO ESTOQUE POSITIVO', $resumo[0]['rotulo'] ?? null);
        $this->assertSame('60,00', $resumo[0]['valor'] ?? null);
        $this->assertSame('ESTOQUE NEGATIVO VALORIZADO', $resumo[1]['rotulo'] ?? null);
        $this->assertSame('-30,00', $resumo[1]['valor'] ?? null);
        $this->assertSame('SALDO LÍQUIDO VALORIZADO', $resumo[2]['rotulo'] ?? null);
        $this->assertSame('30,00', $resumo[2]['valor'] ?? null);
    }

    public function test_filtros_grupo_subgrupo_marca_fornecedor(): void
    {
        $fornecedor = Person::query()->create([
            'nome_razao' => 'FORN FILTRO PE',
            'is_fornecedor' => true,
            'ativo' => true,
        ]);

        Product::query()->create($this->produto([
            'codigo' => 'PE-F1',
            'descricao' => 'FILTRO UM',
            'grupo' => 'ALIMENTOS DOCES',
            'marca' => 'XMARK',
            'ult_fornecedor_id' => $fornecedor->id,
            'estoque' => 3,
            'preco_custo' => 10,
        ]));

        Product::query()->create($this->produto([
            'codigo' => 'PE-F2',
            'descricao' => 'FILTRO DOIS',
            'grupo' => 'ALIMENTOS SALGADOS',
            'marca' => 'YMARK',
            'estoque' => 5,
            'preco_custo' => 10,
        ]));

        $built = $this->report()->build($this->request([
            'grupo' => 'ALIMENTOS',
            'subgrupo' => 'DOCES',
            'marca' => 'XMARK',
            'fornecedor' => 'FILTRO PE',
            'situacao' => 'positivo',
        ]));

        $this->assertCount(1, $built['rows']);
        $this->assertSame('PE-F1', $built['rows'][0]['codigo']);
        $this->assertSame('FORN FILTRO PE', $built['rows'][0]['fornecedor']);
        $this->assertSame('ALIMENTOS', $built['filters']['grupo']);
        $this->assertSame('DOCES', $built['filters']['subgrupo']);
    }

    public function test_situacao_padrao_e_todos(): void
    {
        $built = $this->report()->build($this->request([]));

        $this->assertSame('todos', $built['filters']['situacao']);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function produto(array $extra): array
    {
        return array_merge([
            'codigo' => 'PE-X',
            'descricao' => 'PRODUTO',
            'tipo_produto' => '00',
            'grupo' => 'GERAL',
            'marca' => '',
            'unidade' => 'UN',
            'estoque' => 0,
            'estoque_minimo' => 0,
            'preco_custo' => 0,
            'preco_compra' => 0,
            'e_medio' => 0,
            'preco_venda' => 0,
            'promo_preco_venda' => 0,
            'ativo' => true,
            'is_servico' => false,
            'is_composicao' => false,
            'is_grade' => false,
        ], $extra);
    }

    private function report(): PosicaoEstoqueReport
    {
        return new PosicaoEstoqueReport;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(array $query): Request
    {
        return Request::create('/erp/reports/tabular/posicao-estoque', 'GET', $query);
    }
}
