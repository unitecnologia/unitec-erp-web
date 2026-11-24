<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\ProductEstoqueSaldoService;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosicaoEstoqueReport extends AbstractTabularReport
{
    private const ROW_LIMIT = 10000;

    private const EPS = 0.0000001;

    public function slug(): string
    {
        return 'posicao-estoque';
    }

    public function title(): string
    {
        return 'POSIÇÃO DO ESTOQUE';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function requiresExplicitGenerate(): bool
    {
        return true;
    }

    public function locksCurrentEmpresa(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'marca' => 'MARCA',
            'fornecedor' => 'FORNECEDOR',
            'unidade' => 'UND',
            'quantidade' => 'QTD',
            'custo_unitario' => 'CUSTO UNIT.',
            'valor' => 'CUSTO TOTAL',
            'venda_unitario' => 'VENDA UNIT.',
            'venda_total' => 'VENDA TOTAL',
            'participacao' => '% ESTOQUE',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'codigo',
            'descricao',
            'quantidade',
            'custo_unitario',
            'valor',
            'venda_unitario',
            'venda_total',
            'participacao',
        ];
    }

    public function numericColumns(): array
    {
        return ['quantidade', 'custo_unitario', 'valor', 'venda_unitario', 'venda_total', 'participacao'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'codigo' => 7,
            'descricao' => 24,
            'grupo' => 10,
            'marca' => 8,
            'fornecedor' => 12,
            'unidade' => 4,
            'quantidade' => 7,
            'custo_unitario' => 8,
            'valor' => 9,
            'venda_unitario' => 8,
            'venda_total' => 9,
            'participacao' => 7,
        ];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            [
                'key' => 'grupo',
                'label' => 'Grupo',
                'type' => 'text',
                'placeholder' => 'Todos',
            ],
            [
                'key' => 'subgrupo',
                'label' => 'Subgrupo',
                'type' => 'text',
                'placeholder' => 'Refina o grupo',
            ],
            [
                'key' => 'marca',
                'label' => 'Marca',
                'type' => 'text',
                'placeholder' => 'Todas',
            ],
            [
                'key' => 'fornecedor',
                'label' => 'Fornecedor',
                'type' => 'text',
                'placeholder' => 'Todos',
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação do estoque',
                'type' => 'select',
                'options' => $this->situacaoOptions(),
                'value' => 'todos',
            ],
            [
                'key' => 'ordem',
                'label' => 'Ordenar por',
                'type' => 'select',
                'options' => $this->ordemOptions(),
            ],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $grupo = trim((string) $request->query('grupo', ''));
        $subgrupo = trim((string) $request->query('subgrupo', ''));
        $marca = trim((string) $request->query('marca', ''));
        $fornecedor = trim((string) $request->query('fornecedor', ''));
        $situacao = $this->resolveSituacao($request->query('situacao'));
        $ordem = $this->resolveOrdem($request->query('ordem'));

        $saldos = app(ProductEstoqueSaldoService::class);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $estoqueExpr = $this->estoqueExpression($saldos, $empresaId);
        $custoExpr = $this->custoUnitarioExpression();
        $valorExpr = "(({$estoqueExpr}) * ({$custoExpr}))";
        $products = (new Product)->getTable();
        $people = (new Person)->getTable();
        $peopleSql = static::sqlTable($people);
        $vendaExpr = $this->precoVendaExpression($products, $empresaId);

        $query = Product::query()
            ->leftJoin($people, "{$people}.id", '=', "{$products}.ult_fornecedor_id")
            ->where("{$products}.ativo", true)
            ->where(function (Builder $inner) use ($products): void {
                // Estoque físico: exclui flag serviço e tipo 09-SERVIÇOS do cadastro.
                $inner->where("{$products}.is_servico", false)
                    ->where(function (Builder $tipo) use ($products): void {
                        $tipo->whereNull("{$products}.tipo_produto")
                            ->orWhere("{$products}.tipo_produto", '')
                            ->orWhere("{$products}.tipo_produto", '<>', '09');
                    });
            })
            ->select([
                "{$products}.codigo",
                "{$products}.descricao",
                "{$products}.grupo",
                "{$products}.marca",
                "{$products}.unidade",
                "{$products}.estoque_minimo",
                DB::raw("{$peopleSql}.nome_razao as fornecedor_nome"),
                DB::raw("({$estoqueExpr}) as quantidade_atual"),
                DB::raw("({$custoExpr}) as custo_atual"),
                DB::raw("({$valorExpr}) as valor_atual"),
                DB::raw("({$vendaExpr}) as venda_unitaria_atual"),
                DB::raw("(({$estoqueExpr}) * ({$vendaExpr})) as venda_total_atual"),
            ]);

        $this->applyEmpresaPrecoJoin($query, $products, $empresaId);

        $this->applyTextFilter($query, "{$products}.grupo", $grupo);
        $this->applyTextFilter($query, "{$products}.grupo", $subgrupo);
        $this->applyTextFilter($query, "{$products}.marca", $marca);
        if ($fornecedor !== '') {
            $query->where("{$people}.nome_razao", 'like', '%'.$fornecedor.'%');
        }

        $this->applySituacaoFilter($query, $situacao, $estoqueExpr, $products);
        $this->applyOrdem($query, $ordem, $estoqueExpr, $valorExpr, $products);

        $rawRows = $query
            ->limit(self::ROW_LIMIT + 1)
            ->get();

        $truncated = $rawRows->count() > self::ROW_LIMIT;
        if ($truncated) {
            $rawRows = $rawRows->take(self::ROW_LIMIT);
        }

        $qtdPositiva = 0.0;
        $qtdNegativa = 0.0;
        $valorPositivo = 0.0;
        $valorNegativo = 0.0;
        $vendaTotalPositiva = 0.0;
        $semCustoEstoqueNz = 0;

        foreach ($rawRows as $row) {
            $qtd = (float) ($row->quantidade_atual ?? 0);
            $custo = (float) ($row->custo_atual ?? 0);
            $valor = (float) ($row->valor_atual ?? 0);
            $vendaUnit = (float) ($row->venda_unitaria_atual ?? 0);
            $vendaTotal = (float) ($row->venda_total_atual ?? 0);
            $temCusto = $custo > self::EPS;
            $temVenda = $vendaUnit > self::EPS;

            if ($qtd > self::EPS) {
                $qtdPositiva += $qtd;
                if ($temCusto) {
                    $valorPositivo += $valor;
                }
                if ($temVenda) {
                    $vendaTotalPositiva += $vendaTotal;
                }
            } elseif ($qtd < -self::EPS) {
                $qtdNegativa += $qtd;
                if ($temCusto) {
                    $valorNegativo += $valor;
                }
            }

            if (abs($qtd) > self::EPS && ! $temCusto) {
                $semCustoEstoqueNz++;
            }
        }

        $rows = [];
        foreach ($rawRows as $row) {
            $qtd = (float) ($row->quantidade_atual ?? 0);
            $custo = (float) ($row->custo_atual ?? 0);
            $valor = (float) ($row->valor_atual ?? 0);
            $vendaUnit = (float) ($row->venda_unitaria_atual ?? 0);
            $vendaTotal = (float) ($row->venda_total_atual ?? 0);
            $temCusto = $custo > self::EPS;
            $temVenda = $vendaUnit > self::EPS;
            $valorExibido = $temCusto ? $valor : 0.0;
            $vendaTotalExibido = $temVenda ? $vendaTotal : 0.0;

            // % só sobre o total valorizado positivo (negativos não entram no denominador).
            $participacao = '';
            if ($qtd > self::EPS && $temCusto && $valorPositivo > self::EPS) {
                $participacao = static::formatPercent(($valorExibido / $valorPositivo) * 100.0);
            } elseif ($qtd > self::EPS) {
                $participacao = static::formatPercent(0.0);
            }

            $rows[] = [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
                'grupo' => (string) ($row->grupo ?? ''),
                'marca' => (string) ($row->marca ?? ''),
                'fornecedor' => (string) ($row->fornecedor_nome ?? ''),
                'unidade' => mb_strtoupper((string) ($row->unidade ?: 'UN'), 'UTF-8'),
                'quantidade' => static::formatQuantity($qtd),
                'custo_unitario' => $temCusto ? static::formatMoney($custo) : '',
                'valor' => static::formatMoney($valorExibido),
                'venda_unitario' => $temVenda ? static::formatMoney($vendaUnit) : '',
                'venda_total' => static::formatMoney($vendaTotalExibido),
                'participacao' => $participacao,
            ];
        }

        $saldoLiquido = $valorPositivo + $valorNegativo;
        $custoPositivoFmt = static::formatMoney($valorPositivo);
        $custoNegativoFmt = static::formatMoney($valorNegativo);
        $saldoLiquidoFmt = static::formatMoney($saldoLiquido);

        $vendaTotalPositivaFmt = static::formatMoney($vendaTotalPositiva);

        $summary = [];

        if ($grupo !== '') {
            $summary[] = 'GRUPO: '.mb_strtoupper($grupo, 'UTF-8');
        }
        if ($subgrupo !== '') {
            $summary[] = 'SUBGRUPO: '.mb_strtoupper($subgrupo, 'UTF-8');
        }
        if ($marca !== '') {
            $summary[] = 'MARCA: '.mb_strtoupper($marca, 'UTF-8');
        }
        if ($fornecedor !== '') {
            $summary[] = 'FORNECEDOR: '.mb_strtoupper($fornecedor, 'UTF-8');
        }
        if ($situacao !== 'todos') {
            $summary[] = 'SITUAÇÃO: '.$this->situacaoOptions()[$situacao];
        }
        if ($ordem !== 'descricao') {
            $summary[] = 'ORDENAÇÃO: '.$this->ordemOptions()[$ordem];
        }
        if ($semCustoEstoqueNz > 0) {
            $summary[] = 'SEM CUSTO CONFIÁVEL (estoque ≠ 0): '.$semCustoEstoqueNz.' produto(s)';
            $summary[] = 'VALORIZAÇÃO PARCIAL — itens sem custo não entram no custo total/%';
        }
        if ($truncated) {
            $summary[] = 'LIMITADO A '.number_format(self::ROW_LIMIT, 0, ',', '.').' ITENS (refine os filtros)';
        }

        // CSV: inclui o resumo valorizado ao final (tela/PDF usam rodapé + extra_sections).
        if ($request->boolean('csv')) {
            $rows[] = $this->blankRow($columns);
            $rows[] = $this->resumoCsvRow($columns, 'CUSTO DO ESTOQUE POSITIVO', $custoPositivoFmt, 'valor');
            $rows[] = $this->resumoCsvRow($columns, 'ESTOQUE NEGATIVO VALORIZADO', $custoNegativoFmt, 'valor');
            $rows[] = $this->resumoCsvRow($columns, 'SALDO LÍQUIDO VALORIZADO', $saldoLiquidoFmt, 'valor');
            $rows[] = $this->resumoCsvRow($columns, 'VENDA TOTAL (ESTOQUE POSITIVO)', $vendaTotalPositivaFmt, 'venda_total');
            if ($semCustoEstoqueNz > 0) {
                $rows[] = $this->resumoCsvRow(
                    $columns,
                    'VALORIZAÇÃO PARCIAL — '.$semCustoEstoqueNz.' produto(s) sem custo confiável (estoque ≠ 0)',
                    '',
                );
            }
        }

        $built = $this->result(
            [
                'grupo' => $grupo,
                'subgrupo' => $subgrupo,
                'marca' => $marca,
                'fornecedor' => $fornecedor,
                'situacao' => $situacao,
                'ordem' => $ordem,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            $summary,
        );

        // Rodapé da grade: custo do estoque positivo (base do %).
        $labelPlaced = false;
        foreach ($columns as $column) {
            if (in_array($column, $this->numericColumns(), true)) {
                continue;
            }
            $built['totals'][$column] = $labelPlaced ? '' : 'CUSTO EST. POSITIVO';
            $labelPlaced = true;
        }
        if (isset($built['totals']['custo_unitario'])) {
            $built['totals']['custo_unitario'] = '';
        }
        if (isset($built['totals']['venda_unitario'])) {
            $built['totals']['venda_unitario'] = '';
        }
        if (isset($built['totals']['participacao'])) {
            $built['totals']['participacao'] = $valorPositivo > self::EPS
                ? static::formatPercent(100.0)
                : static::formatPercent(0.0);
        }
        if (isset($built['totals']['quantidade'])) {
            $built['totals']['quantidade'] = static::formatQuantity($qtdPositiva);
        }
        if (isset($built['totals']['valor'])) {
            $built['totals']['valor'] = $custoPositivoFmt;
        }
        if (isset($built['totals']['venda_total'])) {
            $built['totals']['venda_total'] = $vendaTotalPositivaFmt;
        }

        $built['extra_sections'] = [[
            'title' => 'RESUMO VALORIZADO',
            'hide_header' => true,
            'columns' => ['rotulo', 'valor'],
            'column_labels' => [
                'rotulo' => '',
                'valor' => '',
            ],
            'numeric_columns' => ['valor'],
            'rows' => [
                [
                    'rotulo' => 'CUSTO DO ESTOQUE POSITIVO',
                    'valor' => $custoPositivoFmt,
                ],
                [
                    'rotulo' => 'ESTOQUE NEGATIVO VALORIZADO',
                    'valor' => $custoNegativoFmt,
                ],
                [
                    'rotulo' => 'SALDO LÍQUIDO VALORIZADO',
                    'valor' => $saldoLiquidoFmt,
                ],
            ],
        ]];

        return $built;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function blankRow(array $columns): array
    {
        $row = [];
        foreach ($columns as $column) {
            $row[$column] = '';
        }

        return $row;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function resumoCsvRow(array $columns, string $rotulo, string $valor, string $valorColumn = 'valor'): array
    {
        $row = $this->blankRow($columns);
        $labelColumn = in_array('descricao', $columns, true)
            ? 'descricao'
            : (in_array('codigo', $columns, true) ? 'codigo' : ($columns[0] ?? null));

        if ($labelColumn !== null) {
            $row[$labelColumn] = $rotulo;
        }
        if ($valor !== '' && in_array($valorColumn, $columns, true)) {
            $row[$valorColumn] = $valor;
        }

        return $row;
    }

    /**
     * Mesma cascata usada no dashboard/gestão: custo > médio > compra.
     */
    private function custoUnitarioExpression(): string
    {
        $p = static::sqlTable((new Product)->getTable());

        return "CASE
            WHEN COALESCE({$p}.preco_custo, 0) > 0 THEN {$p}.preco_custo
            WHEN COALESCE({$p}.e_medio, 0) > 0 THEN {$p}.e_medio
            WHEN COALESCE({$p}.preco_compra, 0) > 0 THEN {$p}.preco_compra
            ELSE 0
        END";
    }

    /**
     * Preço de venda atual: promo legada vigente → overlay da empresa → cadastro.
     */
    private function precoVendaExpression(string $products, int $empresaId): string
    {
        $p = static::sqlTable($products);
        $pep = static::sqlTable('product_empresa_precos');
        $hoje = now()->toDateString();
        $cadastroExpr = "COALESCE({$p}.preco_venda, 0)";

        if ($empresaId > 0 && ErpSchema::hasTable('product_empresa_precos')) {
            $cadastroExpr = "CASE
                WHEN COALESCE({$pep}.preco_venda, 0) > 0 THEN {$pep}.preco_venda
                ELSE COALESCE({$p}.preco_venda, 0)
            END";
        }

        return "CASE
            WHEN {$p}.promo_data_inicio IS NOT NULL
                AND {$p}.promo_data_fim IS NOT NULL
                AND DATE('{$hoje}') BETWEEN DATE({$p}.promo_data_inicio) AND DATE({$p}.promo_data_fim)
                AND COALESCE({$p}.promo_preco_venda, 0) > 0
            THEN {$p}.promo_preco_venda
            ELSE ({$cadastroExpr})
        END";
    }

    private function applyEmpresaPrecoJoin(Builder $query, string $products, int $empresaId): void
    {
        if ($empresaId <= 0 || ! ErpSchema::hasTable('product_empresa_precos')) {
            return;
        }

        $query->leftJoin('product_empresa_precos', function ($join) use ($products, $empresaId): void {
            $join->on('product_empresa_precos.product_id', '=', "{$products}.id")
                ->where('product_empresa_precos.empresa_id', '=', $empresaId);
        });
    }

    private function estoqueExpression(ProductEstoqueSaldoService $saldos, int $empresaId): string
    {
        if ($empresaId > 0 && $saldos->suportaEstoquePorEmpresa($empresaId)) {
            return $saldos->sqlEstoqueEmpresaExpression($saldos->estoqueIdParaEmpresa($empresaId));
        }

        return $saldos->sqlEstoqueEmpresaExpression(null);
    }

    private function applyTextFilter(Builder $query, string $column, string $value): void
    {
        if ($value === '') {
            return;
        }

        $query->where($column, 'like', '%'.$value.'%');
    }

    private function applySituacaoFilter(Builder $query, string $situacao, string $estoqueExpr, string $products): void
    {
        $productsSql = static::sqlTable($products);

        match ($situacao) {
            'positivo' => $query->whereRaw("({$estoqueExpr}) > 0"),
            'negativo' => $query->whereRaw("({$estoqueExpr}) < 0"),
            'zero' => $query->whereRaw("({$estoqueExpr}) = 0"),
            'critico' => $query
                ->where("{$products}.estoque_minimo", '>', 0)
                ->whereRaw("({$estoqueExpr}) < {$productsSql}.estoque_minimo"),
            default => null,
        };
    }

    private function applyOrdem(
        Builder $query,
        string $ordem,
        string $estoqueExpr,
        string $valorExpr,
        string $products,
    ): void {
        match ($ordem) {
            'codigo' => $query->orderBy("{$products}.codigo"),
            'grupo' => $query->orderBy("{$products}.grupo")->orderBy("{$products}.descricao"),
            'quantidade' => $query->orderByRaw("({$estoqueExpr}) DESC")->orderBy("{$products}.descricao"),
            'valor' => $query->orderByRaw("({$valorExpr}) DESC")->orderBy("{$products}.descricao"),
            'participacao' => $query->orderByRaw("({$valorExpr}) DESC")->orderBy("{$products}.descricao"),
            default => $query->orderBy("{$products}.descricao"),
        };
    }

    /**
     * @return array<string, string>
     */
    private function situacaoOptions(): array
    {
        return [
            'todos' => 'Todos',
            'positivo' => 'Estoque positivo',
            'negativo' => 'Estoque negativo',
            'zero' => 'Estoque zerado',
            'critico' => 'Abaixo do mínimo',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function ordemOptions(): array
    {
        return [
            'descricao' => 'Descrição',
            'codigo' => 'Código',
            'grupo' => 'Grupo',
            'quantidade' => 'Maior quantidade',
            'valor' => 'Maior valor',
            'participacao' => 'Maior participação',
        ];
    }

    private function resolveSituacao(mixed $raw): string
    {
        $value = is_string($raw) ? trim($raw) : '';

        return array_key_exists($value, $this->situacaoOptions()) ? $value : 'todos';
    }

    private function resolveOrdem(mixed $raw): string
    {
        $value = is_string($raw) ? trim($raw) : '';

        return array_key_exists($value, $this->ordemOptions()) ? $value : 'descricao';
    }
}
