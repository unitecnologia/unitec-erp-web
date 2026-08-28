<?php

namespace App\Support\Erp\Compras;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\ErpContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AnaliseComprasService
{
    private const TIMEZONE = 'America/Sao_Paulo';

    private const ROW_LIMIT = 1000;

    /**
     * @param  array{
     *   data_ini: string,
     *   data_fim: string,
     *   produto: string,
     *   product_id: int|null,
     *   grupo: string,
     *   marca: string,
     *   fornecedor_id: int|null,
     *   so_estoque_minimo: bool,
     *   ultima_compra_todas_empresas: bool,
     * }  $filtros
     * @return list<array<string, mixed>>
     */
    public function filtrar(array $filtros): array
    {
        $de = Carbon::parse((string) $filtros['data_ini'], self::TIMEZONE)->startOfDay();
        $ate = Carbon::parse((string) $filtros['data_fim'], self::TIMEZONE)->endOfDay();

        if ($ate->lt($de)) {
            throw new InvalidArgumentException('A data final do período deve ser maior ou igual à inicial.');
        }

        $diasPeriodo = max(1, (int) $de->copy()->startOfDay()->diffInDays($ate->copy()->startOfDay()) + 1);
        $hoje = Carbon::now(self::TIMEZONE)->startOfDay();

        $produtoQ = trim((string) ($filtros['produto'] ?? ''));
        $productId = filled($filtros['product_id'] ?? null) ? (int) $filtros['product_id'] : null;
        $grupo = trim((string) ($filtros['grupo'] ?? ''));
        $marca = trim((string) ($filtros['marca'] ?? ''));
        $fornecedorId = filled($filtros['fornecedor_id'] ?? null) ? (int) $filtros['fornecedor_id'] : null;
        $soMinimo = (bool) ($filtros['so_estoque_minimo'] ?? false);
        $compraIndependenteEmpresa = (bool) ($filtros['ultima_compra_todas_empresas'] ?? false);
        $empresaId = ErpContext::currentEmpresaId();

        $salesByProduct = $this->salesByProduct($de, $ate);
        $lastSaleByProduct = $this->lastSaleByProduct();
        $lastPurchaseByProduct = $this->lastPurchaseByProduct($empresaId, $compraIndependenteEmpresa);

        $query = Product::query()
            ->where('ativo', true)
            ->when($productId, fn ($q) => $q->whereKey($productId))
            ->when($productId === null && $produtoQ !== '', function ($q) use ($produtoQ): void {
                $like = '%'.$produtoQ.'%';
                $q->where(function ($inner) use ($like, $produtoQ): void {
                    $inner->where('codigo', 'like', $like)
                        ->orWhere('descricao', 'like', $like)
                        ->orWhere('codigo_barras', 'like', $like)
                        ->orWhere('referencia', 'like', $like);

                    if (ctype_digit($produtoQ)) {
                        $inner->orWhere('id', (int) $produtoQ);
                    }
                });
            })
            ->when($grupo !== '', fn ($q) => $q->where('grupo', 'like', '%'.$grupo.'%'))
            ->when($marca !== '', fn ($q) => $q->where('marca', 'like', '%'.$marca.'%'))
            ->when($soMinimo, function ($q): void {
                $q->where('estoque_minimo', '>', 0)
                    ->whereColumn('estoque', '<', 'estoque_minimo');
            })
            ->when($fornecedorId, function ($q) use ($fornecedorId, $empresaId, $compraIndependenteEmpresa): void {
                $q->where(function ($inner) use ($fornecedorId, $empresaId, $compraIndependenteEmpresa): void {
                    $inner->where('ult_fornecedor_id', $fornecedorId)
                        ->orWhereIn('id', function ($sub) use ($fornecedorId, $empresaId, $compraIndependenteEmpresa): void {
                            $sub->select('compra_itens.product_id')
                                ->from('compra_itens')
                                ->join('compras', 'compras.id', '=', 'compra_itens.compra_id')
                                ->where('compras.status', Compra::STATUS_FECHADA)
                                ->where('compras.fornecedor_id', $fornecedorId)
                                ->when(
                                    ! $compraIndependenteEmpresa && $empresaId,
                                    fn ($qq) => $qq->where('compras.empresa_id', $empresaId)
                                );
                        });
                });
            })
            ->orderBy('descricao')
            ->limit(self::ROW_LIMIT);

        $products = $query->get([
            'id', 'codigo', 'codigo_barras', 'descricao', 'grupo', 'marca',
            'estoque', 'estoque_minimo', 'preco_compra', 'preco_custo', 'ult_fornecedor_id',
        ]);

        $fornecedorIds = $products->pluck('ult_fornecedor_id')
            ->merge($lastPurchaseByProduct->pluck('fornecedor_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $fornecedores = $fornecedorIds === []
            ? collect()
            : Person::query()->whereIn('id', $fornecedorIds)->pluck('nome_razao', 'id');

        $rows = [];
        foreach ($products as $product) {
            $productId = (int) $product->id;
            $vendaPeriodo = (float) ($salesByProduct[$productId] ?? 0);
            $mediaDiaria = $vendaPeriodo / $diasPeriodo;
            $estoque = (float) $product->estoque;
            $estoqueMinimo = (float) $product->estoque_minimo;
            $faltaMinimo = max(0, $estoqueMinimo - $estoque);
            $duracao = $mediaDiaria > 0 ? $estoque / $mediaDiaria : null;

            $ultimaVenda = $lastSaleByProduct[$productId] ?? null;
            $diasSemVenda = $ultimaVenda
                ? max(0, (int) Carbon::parse($ultimaVenda, self::TIMEZONE)->startOfDay()->diffInDays($hoje))
                : null;

            $compra = $lastPurchaseByProduct->get($productId);
            $ultFornecedorId = $compra['fornecedor_id'] ?? ($product->ult_fornecedor_id ? (int) $product->ult_fornecedor_id : null);

            $rows[] = [
                'product_id' => $productId,
                'codigo' => (string) ($product->codigo ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'marca' => (string) ($product->marca ?? ''),
                'venda_periodo' => round($vendaPeriodo, 3),
                'media_diaria' => round($mediaDiaria, 4),
                'data_ultima_venda' => $ultimaVenda,
                'dias_sem_venda' => $diasSemVenda,
                'estoque' => round($estoque, 3),
                'estoque_minimo' => round($estoqueMinimo, 3),
                'falta_minimo' => round($faltaMinimo, 3),
                'duracao_estoque' => $duracao !== null ? round($duracao, 1) : null,
                'ultima_compra_data' => $compra['data'] ?? null,
                'ultimo_custo_cadastro' => round((float) ($product->preco_custo ?: $product->preco_compra ?: 0), 4),
                'valor_ultima_compra' => isset($compra['valor_unitario'])
                    ? round((float) $compra['valor_unitario'], 4)
                    : round((float) ($product->preco_compra ?: 0), 4),
                'ult_fornecedor_id' => $ultFornecedorId,
                'ult_fornecedor' => $ultFornecedorId
                    ? (string) ($fornecedores[$ultFornecedorId] ?? '')
                    : '',
                'sugestao' => null,
                'urgencia' => null,
                'selected' => false,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function gerarSugestao(
        array $rows,
        string $suprirAte,
        int $aprovisionamentoDias = 0,
        bool $usarEstoqueMinimo = false,
    ): array {
        $ate = Carbon::parse($suprirAte, self::TIMEZONE)->startOfDay();
        $hoje = Carbon::now(self::TIMEZONE)->startOfDay();
        $cobertura = max(0, (int) $hoje->diffInDays($ate, false)) + max(0, $aprovisionamentoDias);

        foreach ($rows as &$row) {
            $media = (float) ($row['media_diaria'] ?? 0);
            $estoque = (float) ($row['estoque'] ?? 0);
            $necessidade = $media * $cobertura;
            $sugestao = max(0, $necessidade - $estoque);

            if ($usarEstoqueMinimo) {
                $sugestao = max($sugestao, (float) ($row['falta_minimo'] ?? 0));
            }

            $sugestao = (float) ceil($sugestao * 1000) / 1000;
            $row['sugestao'] = round($sugestao, 3);
            $row['urgencia'] = $this->urgencia(
                $estoque,
                $row['duracao_estoque'] ?? null,
                (float) ($row['falta_minimo'] ?? 0),
                $sugestao,
            );
        }
        unset($row);

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $productIds
     * @return array{compra: Compra, itens: int}
     */
    public function gerarPedidoCompra(array $rows, array $productIds, ?int $fornecedorIdForce = null): array
    {
        $empresaId = ErpContext::requireEmpresaId();
        $selected = collect($rows)
            ->filter(function (array $row) use ($productIds): bool {
                return in_array((int) $row['product_id'], $productIds, true)
                    && (float) ($row['sugestao'] ?? 0) > 0;
            })
            ->values();

        if ($selected->isEmpty()) {
            throw new InvalidArgumentException('Selecione ao menos um produto com sugestão maior que zero.');
        }

        $fornecedorId = $fornecedorIdForce;
        if (! $fornecedorId) {
            $fornecedorId = $selected
                ->pluck('ult_fornecedor_id')
                ->filter()
                ->countBy()
                ->sortDesc()
                ->keys()
                ->first();
        }

        if (! $fornecedorId) {
            throw new InvalidArgumentException('Informe o fornecedor no filtro ou selecione produtos com último fornecedor.');
        }

        $momento = Carbon::now(self::TIMEZONE);

        $compra = DB::transaction(function () use ($selected, $empresaId, $fornecedorId, $momento): Compra {
            $total = 0.0;
            $itensPayload = [];

            foreach ($selected as $row) {
                $qtd = (float) $row['sugestao'];
                $unit = (float) ($row['valor_ultima_compra'] ?: $row['ultimo_custo_cadastro'] ?: 0);
                $lineTotal = round($qtd * $unit, 2);
                $total += $lineTotal;
                $itensPayload[] = [
                    'product_id' => (int) $row['product_id'],
                    'quantidade' => $qtd,
                    'valor_unitario' => $unit,
                    'total' => $lineTotal,
                ];
            }

            $compra = Compra::query()->create([
                'empresa_id' => $empresaId,
                'numero' => Compra::nextNumero(),
                'data_emissao' => $momento->toDateString(),
                'data_entrada' => $momento->toDateString(),
                'numero_nota' => null,
                'fornecedor_id' => (int) $fornecedorId,
                'chave_nfe' => null,
                'total' => round($total, 2),
                'status' => Compra::STATUS_ABERTA,
            ]);

            foreach ($itensPayload as $item) {
                CompraItem::query()->create([
                    'compra_id' => $compra->id,
                    ...$item,
                ]);
            }

            return $compra;
        });

        return [
            'compra' => $compra,
            'itens' => $selected->count(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function grupoOptions(): array
    {
        return Product::query()
            ->where('ativo', true)
            ->whereNotNull('grupo')
            ->where('grupo', '!=', '')
            ->distinct()
            ->orderBy('grupo')
            ->limit(500)
            ->pluck('grupo', 'grupo')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function marcaOptions(): array
    {
        return Product::query()
            ->where('ativo', true)
            ->whereNotNull('marca')
            ->where('marca', '!=', '')
            ->distinct()
            ->orderBy('marca')
            ->limit(500)
            ->pluck('marca', 'marca')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function fornecedorOptions(): array
    {
        return Person::query()
            ->where('ativo', true)
            ->where('is_fornecedor', true)
            ->orderBy('nome_razao')
            ->limit(1000)
            ->pluck('nome_razao', 'id')
            ->all();
    }

    /**
     * @return array<int, float>
     */
    private function salesByProduct(Carbon $de, Carbon $ate): array
    {
        return VendaItem::query()
            ->select([
                'venda_itens.product_id',
                DB::raw('SUM(venda_itens.quantidade) as qtd'),
            ])
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()])
            ->whereNotNull('venda_itens.product_id')
            ->groupBy('venda_itens.product_id')
            ->pluck('qtd', 'product_id')
            ->map(fn ($qtd): float => (float) $qtd)
            ->all();
    }

    /**
     * @return array<int, string> product_id => Y-m-d
     */
    private function lastSaleByProduct(): array
    {
        return VendaItem::query()
            ->select([
                'venda_itens.product_id',
                DB::raw('MAX(vendas.data) as ultima_data'),
            ])
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereNotNull('venda_itens.product_id')
            ->groupBy('venda_itens.product_id')
            ->pluck('ultima_data', 'product_id')
            ->map(fn ($d): string => Carbon::parse($d)->toDateString())
            ->all();
    }

    /**
     * @return Collection<int, array{data: string, valor_unitario: float, fornecedor_id: int|null}>
     */
    private function lastPurchaseByProduct(?int $empresaId, bool $todasEmpresas): Collection
    {
        $driver = DB::connection()->getDriverName();

        $dateExpr = $driver === 'sqlite'
            ? "COALESCE(compras.data_entrada, compras.data_emissao)"
            : 'COALESCE(compras.data_entrada, compras.data_emissao)';

        $sub = CompraItem::query()
            ->select([
                'compra_itens.product_id',
                DB::raw("MAX({$dateExpr}) as max_data"),
            ])
            ->join('compras', 'compras.id', '=', 'compra_itens.compra_id')
            ->where('compras.status', Compra::STATUS_FECHADA)
            ->whereNotNull('compra_itens.product_id')
            ->when(! $todasEmpresas && $empresaId, fn ($q) => $q->where('compras.empresa_id', $empresaId))
            ->groupBy('compra_itens.product_id');

        $rows = CompraItem::query()
            ->select([
                'compra_itens.product_id',
                'compra_itens.valor_unitario',
                'compras.fornecedor_id',
                DB::raw("{$dateExpr} as data_compra"),
            ])
            ->join('compras', 'compras.id', '=', 'compra_itens.compra_id')
            ->joinSub($sub, 'ult', function ($join) use ($dateExpr): void {
                $join->on('ult.product_id', '=', 'compra_itens.product_id')
                    ->whereRaw("{$dateExpr} = ult.max_data");
            })
            ->where('compras.status', Compra::STATUS_FECHADA)
            ->when(! $todasEmpresas && $empresaId, fn ($q) => $q->where('compras.empresa_id', $empresaId))
            ->orderByDesc('compra_itens.id')
            ->get();

        $map = collect();
        foreach ($rows as $row) {
            $pid = (int) $row->product_id;
            if ($map->has($pid)) {
                continue;
            }
            $map->put($pid, [
                'data' => $row->data_compra
                    ? Carbon::parse($row->data_compra)->toDateString()
                    : null,
                'valor_unitario' => (float) $row->valor_unitario,
                'fornecedor_id' => $row->fornecedor_id ? (int) $row->fornecedor_id : null,
            ]);
        }

        return $map;
    }

    private function urgencia(float $estoque, mixed $duracao, float $faltaMinimo, float $sugestao): string
    {
        if ($sugestao <= 0) {
            return 'normal';
        }

        $dias = is_numeric($duracao) ? (float) $duracao : null;

        if ($estoque <= 0 || ($dias !== null && $dias <= 3)) {
            return 'urgente';
        }

        if ($faltaMinimo > 0 || ($dias !== null && $dias <= 7)) {
            return 'atencao';
        }

        return 'normal';
    }
}
