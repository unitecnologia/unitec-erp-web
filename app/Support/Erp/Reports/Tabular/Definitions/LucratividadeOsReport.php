<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OrdemServico;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LucratividadeOsReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'lucratividade-os';
    }

    public function title(): string
    {
        return 'LUCRATIVIDADE DAS OS';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'OS',
            'data' => 'DATA',
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'tecnico' => 'TÉCNICO',
            'situacao' => 'SIT.',
            'receita_servicos' => 'R. SERV.',
            'receita_pecas' => 'R. PEÇAS',
            'receita_liquida' => 'R. LÍQ.',
            'custo_pecas_cad' => 'CUSTO CAD.',
            'resultado_estimado' => 'RESULT. EST.',
            'margem' => 'MARGEM %',
            'faturado' => 'FATURADO',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 4,
            'data' => 7,
            'empresa' => 9,
            'cliente' => 26,
            'tecnico' => 10,
            'situacao' => 7,
            'receita_servicos' => 8,
            'receita_pecas' => 8,
            'receita_liquida' => 8,
            'custo_pecas_cad' => 8,
            'resultado_estimado' => 8,
            'margem' => 6,
            'faturado' => 7,
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'situacao',
            'receita_servicos',
            'receita_pecas',
            'receita_liquida',
            'custo_pecas_cad',
            'resultado_estimado',
            'margem',
            'faturado',
        ];
    }

    public function numericColumns(): array
    {
        return [
            'receita_servicos',
            'receita_pecas',
            'receita_liquida',
            'custo_pecas_cad',
            'resultado_estimado',
            'margem',
            'faturado',
        ];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'aberta' => 'Abertas',
                    'finalizada' => 'Finalizadas',
                    'cancelada' => 'Canceladas',
                ],
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
            after: 'data',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $multi = $this->isMultiEmpresaScope($request);

        $osTable = static::sqlTable('ordens_servico');
        $itens = static::sqlTable('ordem_servico_itens');
        $products = static::sqlTable('products');
        $custoCadastroExpr = "COALESCE({$products}.preco_custo, {$products}.preco_compra, 0)";
        $finalizadasSql = "'".OrdemServico::SITUACAO_FINALIZADA."','".OrdemServico::SITUACAO_ENTREGUE."'";

        $base = $this->filteredOsQuery($request, $de->toDateString(), $ate->toDateString(), $situacao, $clienteQ, $tecnicoId);

        // Totais do cabeçalho (sem join de itens — evita duplicar receita).
        $headerAgg = (clone $base)->select([
            DB::raw("COALESCE(SUM({$osTable}.total_servicos), 0) as receita_servicos"),
            DB::raw("COALESCE(SUM({$osTable}.total_produtos), 0) as receita_pecas"),
            DB::raw("COALESCE(SUM({$osTable}.total_geral), 0) as receita_liquida"),
            DB::raw("COALESCE(SUM(CASE WHEN {$osTable}.situacao IN ({$finalizadasSql}) THEN {$osTable}.total_geral ELSE 0 END), 0) as faturado"),
            DB::raw("COUNT(*) as qtd_os"),
        ])->first();

        $receitaServicosTotal = (float) ($headerAgg->receita_servicos ?? 0);
        $receitaPecasTotal = (float) ($headerAgg->receita_pecas ?? 0);
        $receitaLiquidaTotal = (float) ($headerAgg->receita_liquida ?? 0);
        $faturadoTotal = (float) ($headerAgg->faturado ?? 0);

        // Custo de peças (cadastro atual) só nos itens tipo P das OS filtradas.
        $custoTotal = (float) (DB::table('ordem_servico_itens')
            ->leftJoin('products', 'products.id', '=', 'ordem_servico_itens.product_id')
            ->where('ordem_servico_itens.tipo', 'P')
            ->whereIn('ordem_servico_itens.ordem_servico_id', (clone $base)->select('ordens_servico.id'))
            ->selectRaw("COALESCE(SUM({$itens}.qtd * {$custoCadastroExpr}), 0) as custo_pecas")
            ->first()
            ?->custo_pecas ?? 0);

        $resultadoTotal = $receitaLiquidaTotal - $custoTotal;
        $margemTotal = $receitaLiquidaTotal > 0.0000001
            ? ($resultadoTotal / $receitaLiquidaTotal) * 100
            : 0.0;

        $custoSub = DB::table('ordem_servico_itens')
            ->leftJoin('products', 'products.id', '=', 'ordem_servico_itens.product_id')
            ->where('ordem_servico_itens.tipo', 'P')
            ->whereIn('ordem_servico_itens.ordem_servico_id', (clone $base)->select('ordens_servico.id'))
            ->groupBy('ordem_servico_itens.ordem_servico_id')
            ->select([
                'ordem_servico_itens.ordem_servico_id',
                DB::raw("COALESCE(SUM({$itens}.qtd * {$custoCadastroExpr}), 0) as custo_pecas"),
            ]);

        // Alias do joinSub recebe DB_PREFIX; no SELECT raw precisamos do nome já prefixado.
        $custoAliasSql = static::sqlTable('custo_agg');

        $ordens = (clone $base)
            ->with([
                'cliente:id,nome_razao',
                'atendente:id,codigo,nome',
            ])
            ->leftJoinSub($custoSub, 'custo_agg', 'custo_agg.ordem_servico_id', '=', 'ordens_servico.id')
            ->select([
                'ordens_servico.id',
                'ordens_servico.empresa_id',
                'ordens_servico.numero',
                'ordens_servico.situacao',
                'ordens_servico.data_inicio',
                'ordens_servico.cliente_id',
                'ordens_servico.atendente_id',
                'ordens_servico.nome',
                'ordens_servico.total_servicos',
                'ordens_servico.total_produtos',
                'ordens_servico.total_geral',
                DB::raw("COALESCE({$custoAliasSql}.custo_pecas, 0) as custo_pecas_cad"),
            ])
            ->orderBy('ordens_servico.data_inicio')
            ->orderBy('ordens_servico.numero')
            ->orderBy('ordens_servico.id')
            ->limit(5000)
            ->get();

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $ordens->map(function (OrdemServico $ordem) use ($multi, $labels): array {
            $receitaServicos = (float) ($ordem->total_servicos ?? 0);
            $receitaPecas = (float) ($ordem->total_produtos ?? 0);
            $receitaLiquida = (float) ($ordem->total_geral ?? 0);
            $custoPecas = (float) ($ordem->custo_pecas_cad ?? 0);
            $resultado = $receitaLiquida - $custoPecas;
            $margem = $receitaLiquida > 0.0000001
                ? ($resultado / $receitaLiquida) * 100
                : null;
            $faturado = in_array((string) $ordem->situacao, [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ], true) ? $receitaLiquida : 0.0;

            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'data' => static::formatDate($ordem->data_inicio),
                'cliente' => $ordem->clienteNome(),
                'tecnico' => $this->formatTecnico($ordem->atendente),
                'situacao' => mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
                'receita_servicos' => static::formatMoney($receitaServicos),
                'receita_pecas' => static::formatMoney($receitaPecas),
                'receita_liquida' => static::formatMoney($receitaLiquida),
                'custo_pecas_cad' => static::formatMoney($custoPecas),
                'resultado_estimado' => static::formatMoney($resultado),
                'margem' => $margem !== null ? static::formatPercent($margem) : '',
                'faturado' => static::formatMoney($faturado),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $row;
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
                'cliente' => $clienteQ,
                'tecnico' => $tecnicoId > 0 ? (string) $tecnicoId : 'todos',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA INÍCIO DA OS): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO: '.$situacaoLabel,
                'TÉCNICO: '.mb_strtoupper($tecnicoLabel, 'UTF-8'),
                'FATURAMENTO REALIZADO (OS FINALIZADAS/ENTREGUES): '.static::formatMoney($faturadoTotal),
                'CUSTO PEÇAS: custo atual do cadastro (não há custo histórico na OS)',
                'RESULTADO ESTIMADO: receita líquida − custo peças (cad.); não desconta mão de obra e não é lucro líquido real',
                'OS EXIBIDAS: '.count($rows).$limitHint,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate($columns, [
            'receita_servicos' => $receitaServicosTotal,
            'receita_pecas' => $receitaPecasTotal,
            'receita_liquida' => $receitaLiquidaTotal,
            'custo_pecas_cad' => $custoTotal,
            'resultado_estimado' => $resultadoTotal,
            'margem' => $margemTotal,
            'faturado' => $faturadoTotal,
        ]);

        return $built;
    }

    /**
     * Consulta de OS (sem venda espelho).
     *
     * @return Builder<\App\Models\OrdemServico>
     */
    private function filteredOsQuery(
        Request $request,
        string $de,
        string $ate,
        string $situacao,
        string $clienteQ,
        int $tecnicoId,
    ): Builder {
        $query = OrdemServico::query()
            ->whereDate('ordens_servico.data_inicio', '>=', $de)
            ->whereDate('ordens_servico.data_inicio', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        $this->applySituacaoFilter($query, $situacao);

        if ($clienteQ !== '') {
            $like = '%'.$clienteQ.'%';
            $query->where(function (Builder $q) use ($like, $clienteQ): void {
                $q->where('ordens_servico.nome', 'like', $like)
                    ->orWhereHas('cliente', function (Builder $cliente) use ($like, $clienteQ): void {
                        $cliente->where(function (Builder $inner) use ($like, $clienteQ): void {
                            $inner->where('nome_razao', 'like', $like);

                            if (ctype_digit($clienteQ)) {
                                $inner->orWhere('id', (int) $clienteQ)
                                    ->orWhere('codigo', 'like', $like);
                            }
                        });
                    });
            });
        }

        if ($tecnicoId > 0) {
            $query->where('ordens_servico.atendente_id', $tecnicoId);
        }

        return $query;
    }

    /**
     * @param  Builder<\App\Models\OrdemServico>  $query
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
     * @param  array<string, float>  $sums
     * @return array<string, string>
     */
    private function totalsFromAggregate(array $columns, array $sums): array
    {
        $totals = [];
        $labelPlaced = false;
        $footerNumeric = [
            'receita_servicos',
            'receita_pecas',
            'receita_liquida',
            'custo_pecas_cad',
            'resultado_estimado',
            'margem',
            'faturado',
        ];

        foreach ($columns as $column) {
            if (! in_array($column, $footerNumeric, true)) {
                $totals[$column] = $labelPlaced ? '' : 'TOTAL';
                $labelPlaced = true;

                continue;
            }

            $value = (float) ($sums[$column] ?? 0);

            $totals[$column] = $column === 'margem'
                ? static::formatPercent($value)
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
