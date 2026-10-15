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

class ServicosPrestadosReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'servicos-prestados';
    }

    public function title(): string
    {
        return 'SERVIÇOS PRESTADOS';
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
            'unitario' => 'VL. UNIT.',
            'desconto' => 'DESC.',
            'liquido' => 'VL. LÍQ.',
            'faturado' => 'FATURADO',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'codigo' => 8,
            'descricao' => 34,
            'empresa' => 10,
            'qtd' => 7,
            'unitario' => 10,
            'desconto' => 9,
            'liquido' => 10,
            'faturado' => 10,
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'codigo',
            'descricao',
            'qtd',
            'unitario',
            'desconto',
            'liquido',
            'faturado',
        ];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'desconto', 'liquido', 'faturado'];
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
                'key' => 'servico',
                'label' => 'Serviço',
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
        $servicoQ = trim((string) $request->query('servico', ''));
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $multi = $this->isMultiEmpresaScope($request);

        $itens = static::sqlTable('ordem_servico_itens');
        $os = static::sqlTable('ordens_servico');
        $products = static::sqlTable('products');

        $descricaoExpr = 'COALESCE('
            ."NULLIF(TRIM({$itens}.discriminacao), ''), "
            ."NULLIF(TRIM({$itens}.nome), ''), "
            ."NULLIF(TRIM({$itens}.servico_prestado), ''), "
            ."NULLIF(TRIM({$products}.descricao), ''), "
            ."'-'"
            .')';
        $productKeyExpr = "COALESCE({$itens}.product_id, 0)";

        $finalizadasSql = "'".OrdemServico::SITUACAO_FINALIZADA."','".OrdemServico::SITUACAO_ENTREGUE."'";

        $base = $this->filteredItemsQuery($request, $de->toDateString(), $ate->toDateString(), $situacao, $servicoQ, $clienteQ, $tecnicoId);

        // Totais com os mesmos filtros (sem limite) — faturado só OS finalizadas/entregues.
        $totaisAgg = (clone $base)->select([
            DB::raw("COALESCE(SUM({$itens}.qtd), 0) as qtd"),
            DB::raw("COALESCE(SUM({$itens}.desconto), 0) as desconto"),
            DB::raw("COALESCE(SUM({$itens}.total), 0) as liquido"),
            DB::raw("COALESCE(SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) THEN {$itens}.total ELSE 0 END), 0) as faturado"),
        ])->first();

        $qtdTotal = (float) ($totaisAgg->qtd ?? 0);
        $descontoTotal = (float) ($totaisAgg->desconto ?? 0);
        $liquidoTotal = (float) ($totaisAgg->liquido ?? 0);
        $faturadoTotal = (float) ($totaisAgg->faturado ?? 0);

        // groupByRaw evita conflito com ONLY_FULL_GROUP_BY do MySQL.
        $groupByRaw = $multi
            ? "{$os}.empresa_id, {$productKeyExpr}, {$descricaoExpr}"
            : "{$productKeyExpr}, {$descricaoExpr}";

        $select = [
            DB::raw("MAX({$descricaoExpr}) as descricao"),
            DB::raw("COALESCE(MAX(NULLIF(TRIM({$products}.codigo), '')), MAX(NULLIF(TRIM(CAST({$itens}.codigo_legado AS CHAR)), '')), '') as codigo"),
            DB::raw("COALESCE(SUM({$itens}.qtd), 0) as qtd"),
            DB::raw("CASE WHEN COALESCE(SUM({$itens}.qtd), 0) > 0.0000001 THEN SUM({$itens}.preco * {$itens}.qtd) / SUM({$itens}.qtd) ELSE NULL END as unitario"),
            DB::raw("COALESCE(SUM({$itens}.desconto), 0) as desconto"),
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
            $unitario = $row->unitario !== null ? (float) $row->unitario : null;

            $mapped = [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => mb_strtoupper((string) ($row->descricao ?? '—'), 'UTF-8'),
                'qtd' => static::formatQuantity($qtd),
                'unitario' => $unitario !== null ? static::formatMoney($unitario) : '',
                'desconto' => static::formatMoney((float) ($row->desconto ?? 0)),
                'liquido' => static::formatMoney((float) ($row->liquido ?? 0)),
                'faturado' => static::formatMoney((float) ($row->faturado ?? 0)),
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
                'servico' => $servicoQ,
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
                'SERVIÇOS AGRUPADOS: '.count($rows).$limitHint,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate($columns, [
            'qtd' => $qtdTotal,
            'desconto' => $descontoTotal,
            'liquido' => $liquidoTotal,
            'faturado' => $faturadoTotal,
        ]);

        return $built;
    }

    /**
     * Itens de serviço (tipo S) nas OS — nunca usa venda espelho.
     *
     * @return Builder<\App\Models\OrdemServicoItem>
     */
    private function filteredItemsQuery(
        Request $request,
        string $de,
        string $ate,
        string $situacao,
        string $servicoQ,
        string $clienteQ,
        int $tecnicoId,
    ): Builder {
        $query = OrdemServicoItem::query()
            ->join('ordens_servico', 'ordens_servico.id', '=', 'ordem_servico_itens.ordem_servico_id')
            ->leftJoin('products', 'products.id', '=', 'ordem_servico_itens.product_id')
            ->where('ordem_servico_itens.tipo', 'S')
            ->whereDate('ordens_servico.data_inicio', '>=', $de)
            ->whereDate('ordens_servico.data_inicio', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        $this->applySituacaoFilter($query, $situacao);

        if ($servicoQ !== '') {
            $like = '%'.$servicoQ.'%';
            $query->where(function (Builder $q) use ($like, $servicoQ): void {
                $q->where('ordem_servico_itens.discriminacao', 'like', $like)
                    ->orWhere('ordem_servico_itens.nome', 'like', $like)
                    ->orWhere('ordem_servico_itens.servico_prestado', 'like', $like)
                    ->orWhere('products.descricao', 'like', $like)
                    ->orWhere('products.codigo', 'like', $like);

                if (ctype_digit($servicoQ)) {
                    $q->orWhere('ordem_servico_itens.codigo_legado', (int) $servicoQ)
                        ->orWhere('ordem_servico_itens.product_id', (int) $servicoQ);
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
     * @param  array{qtd: float, desconto: float, liquido: float, faturado: float}  $sums
     * @return array<string, string>
     */
    private function totalsFromAggregate(array $columns, array $sums): array
    {
        $numeric = $this->numericColumns();
        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if (! in_array($column, $numeric, true)) {
                $totals[$column] = $labelPlaced ? '' : 'TOTAL';
                $labelPlaced = true;

                continue;
            }

            $value = (float) ($sums[$column] ?? 0);

            $totals[$column] = $column === 'qtd'
                ? static::formatQuantity($value)
                : static::formatMoney($value);
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
