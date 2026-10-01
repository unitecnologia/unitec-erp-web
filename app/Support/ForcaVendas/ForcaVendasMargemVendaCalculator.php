<?php

namespace App\Support\ForcaVendas;

use Illuminate\Support\Facades\DB;

/**
 * Margem estimada da venda (Tela de Venda / Monitor).
 * Custo atual do cadastro — sem snapshot histórico.
 */
final class ForcaVendasMargemVendaCalculator
{
    /**
     * Residual de cabeçalho: diferença entre soma dos líquidos das linhas e o total real do pedido.
     * Se o desconto/acréscimo já estiver nas linhas, retorna zeros.
     *
     * @return array{desconto: float, acrescimo: float}
     */
    public static function residualCabecalho(float $somaItensLiquidos, float $totalPedidoLiquido): array
    {
        $soma = round($somaItensLiquidos, 2);
        $alvo = round($totalPedidoLiquido, 2);

        if ($alvo <= 0 || abs($soma - $alvo) < 0.009) {
            return ['desconto' => 0.0, 'acrescimo' => 0.0];
        }

        $diff = round($soma - $alvo, 2);

        return [
            'desconto' => $diff > 0 ? $diff : 0.0,
            'acrescimo' => $diff < 0 ? abs($diff) : 0.0,
        ];
    }

    /**
     * Rateio proporcional ao total de cada linha (sobra no último).
     * Mesma regra de ForcaVendasTelaVendaPage::ratearValorNosItens.
     *
     * @param  list<array{total?: float|int|string}>  $itens
     * @return array<int, float>
     */
    public static function ratearValorNosItens(float $valor, float $base, array $itens): array
    {
        $n = count($itens);
        $rateio = array_fill(0, $n, 0.0);

        if ($valor <= 0 || $base <= 0 || $n === 0) {
            return $rateio;
        }

        $ultimo = $n - 1;
        $acumulado = 0.0;

        foreach ($itens as $index => $item) {
            if ($index === $ultimo) {
                $rateio[$index] = round($valor - $acumulado, 2);
                break;
            }

            $parte = round($valor * ((float) ($item['total'] ?? 0) / $base), 2);
            $rateio[$index] = $parte;
            $acumulado = round($acumulado + $parte, 2);
        }

        return $rateio;
    }

    /**
     * Custo unitário em lote: pep.preco_custo → products.preco_custo → preco_compra → 0.
     *
     * @param  list<int>  $productIds
     * @return array<int, float> product_id => custo_unitario
     */
    public static function custosUnitariosPorProduto(array $productIds, ?int $empresaId = null): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $productIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $empresaId = (int) ($empresaId ?? 0);

        $query = DB::table('products as p')
            ->whereIn('p.id', $ids)
            ->select(['p.id', 'p.preco_custo', 'p.preco_compra']);

        if ($empresaId > 0) {
            $query
                ->leftJoin('product_empresa_precos as pep', function ($join) use ($empresaId): void {
                    $join->on('pep.product_id', '=', 'p.id')
                        ->where('pep.empresa_id', '=', $empresaId);
                })
                ->addSelect([
                    'pep.preco_custo as pep_custo',
                    'pep.preco_compra as pep_compra',
                ]);
        }

        $map = [];

        foreach ($query->get() as $row) {
            $custo = 0.0;

            if ($empresaId > 0) {
                $pepCusto = (float) ($row->pep_custo ?? 0);
                if ($pepCusto > 0) {
                    $custo = $pepCusto;
                }
            }

            if ($custo <= 0) {
                $custo = (float) ($row->preco_custo ?? 0);
            }

            if ($custo <= 0) {
                $pepCompra = $empresaId > 0 ? (float) ($row->pep_compra ?? 0) : 0.0;
                $custo = $pepCompra > 0
                    ? $pepCompra
                    : (float) ($row->preco_compra ?? 0);
            }

            $map[(int) $row->id] = round(max(0, $custo), 2);
        }

        return $map;
    }

    /**
     * @param  list<array{
     *   product_id?: int,
     *   codigo?: string,
     *   descricao?: string,
     *   quantidade?: float|int|string,
     *   preco_unitario?: float|int|string,
     *   desconto?: float|int|string,
     *   acrescimo?: float|int|string,
     *   total?: float|int|string
     * }>  $itens
     * @param  array<int, float>  $custosPorProductId
     * @return array{
     *   linhas: list<array{
     *     codigo: string,
     *     produto: string,
     *     qtd: float,
     *     valor_vendido: float,
     *     desc_acr: float,
     *     liquido: float,
     *     custo: float,
     *     lucro: float,
     *     margem: float
     *   }>,
     *   totais: array{
     *     venda_liquida: float,
     *     custo_total: float,
     *     lucro_estimado: float,
     *     margem: float
     *   }
     * }
     */
    public static function calcular(
        array $itens,
        float $descontoCabecalho,
        float $acrescimoCabecalho,
        array $custosPorProductId,
    ): array {
        $base = round(array_sum(array_map(
            static fn (array $i): float => (float) ($i['total'] ?? 0),
            $itens,
        )), 2);

        $descontoCabecalho = round(min(max(0, $descontoCabecalho), max(0, $base)), 2);
        $acrescimoCabecalho = round(max(0, $acrescimoCabecalho), 2);

        $descontoRateio = self::ratearValorNosItens($descontoCabecalho, $base, $itens);
        $acrescimoRateio = self::ratearValorNosItens($acrescimoCabecalho, $base, $itens);

        $linhas = [];
        $vendaLiquida = 0.0;
        $custoTotal = 0.0;

        foreach ($itens as $index => $item) {
            $qtd = (float) ($item['quantidade'] ?? 0);
            $preco = (float) ($item['preco_unitario'] ?? 0);
            $totalItem = (float) ($item['total'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);

            $liquido = round(
                $totalItem
                + ($acrescimoRateio[$index] ?? 0)
                - ($descontoRateio[$index] ?? 0),
                2,
            );

            $valorVendido = round($qtd * $preco, 2);
            $descAcr = round($liquido - $valorVendido, 2);

            $custoUnit = (float) ($custosPorProductId[$productId] ?? 0);
            $custo = round($qtd * $custoUnit, 2);
            $lucro = round($liquido - $custo, 2);
            $margem = $liquido > 0 ? round(($lucro / $liquido) * 100, 2) : 0.0;

            $codigo = trim((string) ($item['codigo'] ?? ''));
            $descricao = trim((string) ($item['descricao'] ?? ''));
            $produto = $descricao !== ''
                ? $descricao
                : ($codigo !== '' ? $codigo : 'Produto #'.$productId);

            $linhas[] = [
                'codigo' => $codigo,
                'produto' => $produto,
                'qtd' => $qtd,
                'valor_vendido' => $valorVendido,
                'desc_acr' => $descAcr,
                'liquido' => $liquido,
                'custo' => $custo,
                'lucro' => $lucro,
                'margem' => $margem,
            ];

            $vendaLiquida = round($vendaLiquida + $liquido, 2);
            $custoTotal = round($custoTotal + $custo, 2);
        }

        $lucroTotal = round($vendaLiquida - $custoTotal, 2);
        $margemTotal = $vendaLiquida > 0
            ? round(($lucroTotal / $vendaLiquida) * 100, 2)
            : 0.0;

        return [
            'linhas' => $linhas,
            'totais' => [
                'venda_liquida' => $vendaLiquida,
                'custo_total' => $custoTotal,
                'lucro_estimado' => $lucroTotal,
                'margem' => $margemTotal,
            ],
        ];
    }
}
