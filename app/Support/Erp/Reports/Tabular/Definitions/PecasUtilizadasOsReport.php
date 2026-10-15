<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PecasUtilizadasOsReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'pecas-utilizadas-os';
    }

    public function title(): string
    {
        return 'PEÇAS UTILIZADAS NAS OS';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓD.',
            'descricao' => 'DESCRIÇÃO',
            'empresa' => 'EMPRESA',
            'qtd' => 'QTD',
            'custo_unitario' => 'CUSTO UNIT.',
            'unitario' => 'VL. UNIT.',
            'custo_total' => 'CUSTO TOT.',
            'liquido' => 'VL. LÍQ.',
            'faturado' => 'FATURADO',
            'margem' => 'MARGEM %',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'codigo' => 7,
            'descricao' => 28,
            'empresa' => 9,
            'qtd' => 6,
            'custo_unitario' => 9,
            'unitario' => 9,
            'custo_total' => 9,
            'liquido' => 9,
            'faturado' => 8,
            'margem' => 7,
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'codigo',
            'descricao',
            'qtd',
            'custo_unitario',
            'unitario',
            'custo_total',
            'liquido',
            'faturado',
            'margem',
        ];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'custo_total', 'liquido', 'faturado', 'margem'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'situacao',
                'label' => 'Situação da OS',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'aberta' => 'Abertas',
                    'finalizada' => 'Finalizadas',
                    'cancelada' => 'Canceladas',
                ],
            ],
            [
                'key' => 'produto',
                'label' => 'Produto',
                'type' => 'text',
            ],
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
            [
                'key' => 'tecnico',
                'label' => 'Técnico',
                'type' => 'select',
                'options' => $this->tecnicoOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'descricao',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $produtoQ = trim((string) $request->query('produto', ''));
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $multi = $this->isMultiEmpresaScope($request);

        $itens = static::sqlTable('ordem_servico_itens');
        $os = static::sqlTable('ordens_servico');
        $products = static::sqlTable('products');

        $descricaoExpr = 'COALESCE('
            ."NULLIF(TRIM({$itens}.discriminacao), ''), "
            ."NULLIF(TRIM({$itens}.nome), ''), "
            ."NULLIF(TRIM({$products}.descricao), ''), "
            ."'-'"
            .')';
        $productKeyExpr = "COALESCE({$itens}.product_id, 0)";
        // Sem custo histórico no item da OS — usa custo atual do cadastro (preco_custo / preco_compra).
        $custoCadastroExpr = "COALESCE({$products}.preco_custo, {$products}.preco_compra, 0)";

        $finalizadasSql = "'".OrdemServico::SITUACAO_FINALIZADA."','".OrdemServico::SITUACAO_ENTREGUE."'";

        $base = $this->filteredItemsQuery($request, $de->toDateString(), $ate->toDateString(), $situacao, $produtoQ, $clienteQ, $tecnicoId);

        $totaisAgg = (clone $base)->select([
            DB::raw("COALESCE(SUM({$itens}.qtd), 0) as qtd"),
            DB::raw("COALESCE(SUM({$itens}.preco * {$itens}.qtd), 0) as bruto_preco"),
            DB::raw("COALESCE(SUM({$itens}.qtd * {$custoCadastroExpr}), 0) as custo_total"),
            DB::raw("COALESCE(SUM({$itens}.total), 0) as liquido"),
            DB::raw("COALESCE(SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) THEN {$itens}.total ELSE 0 END), 0) as faturado"),
        ])->first();

        $qtdTotal = (float) ($totaisAgg->qtd ?? 0);
        $brutoPrecoTotal = (float) ($totaisAgg->bruto_preco ?? 0);
        $custoTotal = (float) ($totaisAgg->custo_total ?? 0);
        $liquidoTotal = (float) ($totaisAgg->liquido ?? 0);
        $faturadoTotal = (float) ($totaisAgg->faturado ?? 0);
        $margemTotal = $liquidoTotal > 0.0000001
            ? (($liquidoTotal - $custoTotal) / $liquidoTotal) * 100
            : 0.0;

        $groupByRaw = $multi
            ? "{$os}.empresa_id, {$productKeyExpr}, {$descricaoExpr}"
            : "{$productKeyExpr}, {$descricaoExpr}";

        $select = [
            DB::raw("MAX({$descricaoExpr}) as descricao"),
            DB::raw("COALESCE(MAX(NULLIF(TRIM({$products}.codigo), '')), MAX(NULLIF(TRIM(CAST({$itens}.codigo_legado AS CHAR)), '')), '') as codigo"),
            DB::raw("COALESCE(SUM({$itens}.qtd), 0) as qtd"),
            DB::raw("CASE WHEN COALESCE(SUM({$itens}.qtd), 0) > 0.0000001 THEN SUM({$itens}.qtd * {$custoCadastroExpr}) / SUM({$itens}.qtd) ELSE NULL END as custo_unitario"),
            DB::raw("CASE WHEN COALESCE(SUM({$itens}.qtd), 0) > 0.0000001 THEN SUM({$itens}.preco * {$itens}.qtd) / SUM({$itens}.qtd) ELSE NULL END as unitario"),
            DB::raw("COALESCE(SUM({$itens}.qtd * {$custoCadastroExpr}), 0) as custo_total"),
            DB::raw("COALESCE(SUM({$itens}.total), 0) as liquido"),
            DB::raw("COALESCE(SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) THEN {$itens}.total ELSE 0 END), 0) as faturado"),
        ];

        if ($multi) {
            $select[] = DB::raw("{$os}.empresa_id as empresa_id");
        }

        $grouped = (clone $base)
            ->select($select)
            ->groupByRaw($groupByRaw)
            ->orderByDesc(DB::raw("SUM({$itens}.total)"))
            ->limit(5000)
            ->get();

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $grouped->map(function (object $row) use ($multi, $labels): array {
            $qtd = (float) ($row->qtd ?? 0);
            $custoUnitario = $row->custo_unitario !== null ? (float) $row->custo_unitario : null;
            $unitario = $row->unitario !== null ? (float) $row->unitario : null;
            $custoLinha = (float) ($row->custo_total ?? 0);
            $liquido = (float) ($row->liquido ?? 0);
            $margem = $liquido > 0.0000001
                ? (($liquido - $custoLinha) / $liquido) * 100
                : null;

            $mapped = [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => mb_strtoupper((string) ($row->descricao ?? '—'), 'UTF-8'),
                'qtd' => static::formatQuantity($qtd),
                'custo_unitario' => $custoUnitario !== null ? static::formatMoney($custoUnitario) : '',
                'unitario' => $unitario !== null ? static::formatMoney($unitario) : '',
                'custo_total' => static::formatMoney($custoLinha),
                'liquido' => static::formatMoney($liquido),
                'faturado' => static::formatMoney((float) ($row->faturado ?? 0)),
                'margem' => $margem !== null ? static::formatPercent($margem) : '',
            ];

            if ($multi) {
                $mapped['empresa'] = $labels[(int) ($row->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $mapped;
        })->all();

        $situacaoLabel = match ($situacao) {
            'aberta' => 'ABERTAS',
            'finalizada' => 'FINALIZADAS',
            'cancelada' => 'CANCELADAS',
            default => 'TODOS',
        };

        $tecnicoLabel = 'TODOS';
        if ($tecnicoId > 0) {
            $tecnico = Vendedor::query()->select(['id', 'codigo', 'nome'])->find($tecnicoId);
            $tecnicoLabel = $this->formatTecnico($tecnico) ?: (string) $tecnicoId;
        }

        $limitHint = count($rows) >= 5000 ? ' (limite de exibição; totais do rodapé incluem todos os filtros)' : '';

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'produto' => $produtoQ,
                'cliente' => $clienteQ,
                'tecnico' => $tecnicoId > 0 ? (string) $tecnicoId : 'todos',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA INÍCIO DA OS): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO DA OS: '.$situacaoLabel,
                'TÉCNICO: '.mb_strtoupper($tecnicoLabel, 'UTF-8'),
                'FATURAMENTO REALIZADO (OS FINALIZADAS): '.static::formatMoney($faturadoTotal),
                'CUSTO/MARGEM: custo atual do cadastro do produto (não há custo histórico na OS)',
                'PEÇAS AGRUPADAS: '.count($rows).$limitHint,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate($columns, [
            'qtd' => $qtdTotal,
            'custo_unitario' => $qtdTotal > 0.0000001 ? $custoTotal / $qtdTotal : null,
            'unitario' => $qtdTotal > 0.0000001 ? $brutoPrecoTotal / $qtdTotal : null,
            'custo_total' => $custoTotal,
            'liquido' => $liquidoTotal,
            'faturado' => $faturadoTotal,
            'margem' => $margemTotal,
        ]);

        return $built;
    }

    /**
     * Itens de peça (tipo P) nas OS — nunca usa venda espelho nem serviços.
     *
     * @return Builder<\App\Models\OrdemServicoItem>
     */
    private function filteredItemsQuery(
        Request $request,
        string $de,
        string $ate,
        string $situacao,
        string $produtoQ,
        string $clienteQ,
        int $tecnicoId,
    ): Builder {
        $query = OrdemServicoItem::query()
            ->join('ordens_servico', 'ordens_servico.id', '=', 'ordem_servico_itens.ordem_servico_id')
            ->leftJoin('products', 'products.id', '=', 'ordem_servico_itens.product_id')
            ->where('ordem_servico_itens.tipo', 'P')
            ->whereDate('ordens_servico.data_inicio', '>=', $de)
            ->whereDate('ordens_servico.data_inicio', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        $this->applySituacaoFilter($query, $situacao);

        if ($produtoQ !== '') {
            $like = '%'.$produtoQ.'%';
            $query->where(function (Builder $q) use ($like, $produtoQ): void {
                $q->where('ordem_servico_itens.discriminacao', 'like', $like)
                    ->orWhere('ordem_servico_itens.nome', 'like', $like)
                    ->orWhere('products.descricao', 'like', $like)
                    ->orWhere('products.codigo', 'like', $like);

                if (ctype_digit($produtoQ)) {
                    $q->orWhere('ordem_servico_itens.codigo_legado', (int) $produtoQ)
                        ->orWhere('ordem_servico_itens.product_id', (int) $produtoQ);
                }
            });
        }

        if ($clienteQ !== '') {
            $like = '%'.$clienteQ.'%';
            $query->where(function (Builder $q) use ($like, $clienteQ): void {
                $q->where('ordens_servico.nome', 'like', $like)
                    ->orWhereExists(function ($sub) use ($like, $clienteQ): void {
                        $sub->select(DB::raw('1'))
                            ->from('people')
                            ->whereColumn('people.id', 'ordens_servico.cliente_id')
                            ->where(function ($inner) use ($like, $clienteQ): void {
                                $inner->where('people.nome_razao', 'like', $like);

                                if (ctype_digit($clienteQ)) {
                                    $inner->orWhere('people.id', (int) $clienteQ)
                                        ->orWhere('people.codigo', 'like', $like);
                                }
                            });
                    });
            });
        }

        if ($tecnicoId > 0) {
            $query->where(function (Builder $q) use ($tecnicoId): void {
                $q->where('ordem_servico_itens.funcionario_id', $tecnicoId)
                    ->orWhere(function (Builder $fallback) use ($tecnicoId): void {
                        $fallback->whereNull('ordem_servico_itens.funcionario_id')
                            ->where('ordens_servico.atendente_id', $tecnicoId);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Models\OrdemServicoItem>  $query
     */
    private function applySituacaoFilter(Builder $query, string $situacao): void
    {
        if ($situacao === OrdemServico::SITUACAO_ABERTA) {
            $query->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_ABERTA,
                OrdemServico::SITUACAO_ANDAMENTO,
            ]);

            return;
        }

        if ($situacao === OrdemServico::SITUACAO_FINALIZADA) {
            $query->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ]);

            return;
        }

        if ($situacao === OrdemServico::SITUACAO_CANCELADA) {
            $query->where('ordens_servico.situacao', OrdemServico::SITUACAO_CANCELADA);
        }
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, float|null>  $sums
     * @return array<string, string>
     */
    private function totalsFromAggregate(array $columns, array $sums): array
    {
        $totals = [];
        $labelPlaced = false;
        $footerNumeric = ['qtd', 'custo_total', 'liquido', 'faturado', 'margem', 'custo_unitario', 'unitario'];

        foreach ($columns as $column) {
            if (! in_array($column, $footerNumeric, true)) {
                $totals[$column] = $labelPlaced ? '' : 'TOTAL';
                $labelPlaced = true;

                continue;
            }

            $value = $sums[$column] ?? null;

            if ($value === null) {
                $totals[$column] = '';

                continue;
            }

            $totals[$column] = match ($column) {
                'qtd' => static::formatQuantity((float) $value),
                'margem' => static::formatPercent((float) $value),
                default => static::formatMoney((float) $value),
            };
        }

        return $totals;
    }

    private function formatTecnico(?Vendedor $tecnico): string
    {
        if ($tecnico === null) {
            return '';
        }

        $codigo = trim((string) ($tecnico->codigo ?? ''));
        $nome = trim((string) ($tecnico->nome ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    /**
     * @return array<string, string>
     */
    private function tecnicoOptions(): array
    {
        $options = ['todos' => 'Todos'];
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(500);

        if ($empresaId) {
            $query->where(function (Builder $q) use ($empresaId): void {
                $q->whereHas('empresas', fn (Builder $e) => $e->where('empresas.id', (int) $empresaId))
                    ->orWhere('empresa_id', (int) $empresaId);
            });
        }

        foreach ($query->get(['id', 'codigo', 'nome']) as $vendedor) {
            $options[(string) $vendedor->id] = $this->formatTecnico($vendedor);
        }

        return $options;
    }
}
