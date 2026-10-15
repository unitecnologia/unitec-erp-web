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

class ProdutividadePorTecnicoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'produtividade-por-tecnico';
    }

    public function title(): string
    {
        return 'PRODUTIVIDADE POR TÉCNICO';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'tecnico' => 'TÉCNICO',
            'empresa' => 'EMPRESA',
            'qtd_os' => 'OS',
            'finalizadas' => 'FINALIZ.',
            'pendentes' => 'PEND.',
            'qtd_servicos' => 'QTD SERV.',
            'valor_servicos' => 'VL. SERV.',
            'ticket_medio' => 'TICKET MÉD.',
            'tempo_medio' => 'TEMPO MÉD.',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'tecnico' => 28,
            'empresa' => 10,
            'qtd_os' => 7,
            'finalizadas' => 8,
            'pendentes' => 7,
            'qtd_servicos' => 9,
            'valor_servicos' => 11,
            'ticket_medio' => 10,
            'tempo_medio' => 10,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['qtd_os', 'finalizadas', 'pendentes', 'qtd_servicos', 'tempo_medio'];
    }

    public function defaultColumns(): array
    {
        return [
            'tecnico',
            'qtd_os',
            'finalizadas',
            'pendentes',
            'qtd_servicos',
            'valor_servicos',
            'ticket_medio',
            'tempo_medio',
        ];
    }

    public function numericColumns(): array
    {
        return ['qtd_os', 'finalizadas', 'pendentes', 'qtd_servicos', 'valor_servicos', 'ticket_medio'];
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
                'key' => 'tecnico',
                'label' => 'Técnico',
                'type' => 'select',
                'options' => $this->tecnicoOptions(),
            ],
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'tecnico',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $tecnicoFiltro = trim((string) $request->query('tecnico', 'todos'));
        $clienteQ = trim((string) $request->query('cliente', ''));
        $multi = $this->isMultiEmpresaScope($request);

        $os = static::sqlTable('ordens_servico');
        $itens = static::sqlTable('ordem_servico_itens');
        $vendedores = static::sqlTable('vendedores');
        $finalizadasSql = "'".OrdemServico::SITUACAO_FINALIZADA."','".OrdemServico::SITUACAO_ENTREGUE."'";
        $pendentesSql = "'".OrdemServico::SITUACAO_ABERTA."','".OrdemServico::SITUACAO_ANDAMENTO."'";

        $base = $this->filteredOsQuery($request, $de->toDateString(), $ate->toDateString(), $situacao, $tecnicoFiltro, $clienteQ);

        // Qtd de serviços por OS (tipo S) — não multiplica linhas da OS no agrupamento final.
        $servicosSub = DB::table('ordem_servico_itens')
            ->where('ordem_servico_itens.tipo', 'S')
            ->whereIn('ordem_servico_itens.ordem_servico_id', (clone $base)->select('ordens_servico.id'))
            ->groupBy('ordem_servico_itens.ordem_servico_id')
            ->select([
                'ordem_servico_itens.ordem_servico_id',
                DB::raw("COALESCE(SUM({$itens}.qtd), 0) as qtd_servicos"),
            ]);

        $servAlias = static::sqlTable('serv_agg');
        $tecnicoKey = "COALESCE({$os}.atendente_id, 0)";

        $groupByRaw = $multi
            ? "{$os}.empresa_id, {$tecnicoKey}"
            : $tecnicoKey;

        $select = [
            DB::raw("{$tecnicoKey} as tecnico_id"),
            DB::raw("MAX({$vendedores}.codigo) as tecnico_codigo"),
            DB::raw("MAX({$vendedores}.nome) as tecnico_nome"),
            DB::raw('COUNT(*) as qtd_os'),
            DB::raw("SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) THEN 1 ELSE 0 END) as finalizadas"),
            DB::raw("SUM(CASE WHEN {$os}.situacao IN ({$pendentesSql}) THEN 1 ELSE 0 END) as pendentes"),
            DB::raw("COALESCE(SUM({$servAlias}.qtd_servicos), 0) as qtd_servicos"),
            // Valor líquido do cabeçalho (1x por OS) — não usa venda espelho nem soma de itens.
            DB::raw("COALESCE(SUM({$os}.total_servicos), 0) as valor_servicos"),
            // Tempo só com datas reais de início e término/entrega em OS concluídas.
            DB::raw(
                "AVG(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) "
                ."AND {$os}.data_inicio IS NOT NULL "
                ."AND COALESCE({$os}.data_termino, {$os}.data_entrega) IS NOT NULL "
                ."THEN DATEDIFF(COALESCE({$os}.data_termino, {$os}.data_entrega), {$os}.data_inicio) "
                .'END) as tempo_medio_dias'
            ),
            DB::raw(
                "SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) "
                ."AND {$os}.data_inicio IS NOT NULL "
                ."AND COALESCE({$os}.data_termino, {$os}.data_entrega) IS NOT NULL "
                .'THEN 1 ELSE 0 END) as qtd_com_tempo'
            ),
        ];

        if ($multi) {
            $select[] = DB::raw("{$os}.empresa_id as empresa_id");
        }

        $totaisAgg = (clone $base)
            ->leftJoinSub($servicosSub, 'serv_agg', 'serv_agg.ordem_servico_id', '=', 'ordens_servico.id')
            ->select([
                DB::raw('COUNT(*) as qtd_os'),
                DB::raw("SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) THEN 1 ELSE 0 END) as finalizadas"),
                DB::raw("SUM(CASE WHEN {$os}.situacao IN ({$pendentesSql}) THEN 1 ELSE 0 END) as pendentes"),
                DB::raw("COALESCE(SUM({$servAlias}.qtd_servicos), 0) as qtd_servicos"),
                DB::raw("COALESCE(SUM({$os}.total_servicos), 0) as valor_servicos"),
                DB::raw(
                    "AVG(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) "
                    ."AND {$os}.data_inicio IS NOT NULL "
                    ."AND COALESCE({$os}.data_termino, {$os}.data_entrega) IS NOT NULL "
                    ."THEN DATEDIFF(COALESCE({$os}.data_termino, {$os}.data_entrega), {$os}.data_inicio) "
                    .'END) as tempo_medio_dias'
                ),
                DB::raw(
                    "SUM(CASE WHEN {$os}.situacao IN ({$finalizadasSql}) "
                    ."AND {$os}.data_inicio IS NOT NULL "
                    ."AND COALESCE({$os}.data_termino, {$os}.data_entrega) IS NOT NULL "
                    .'THEN 1 ELSE 0 END) as qtd_com_tempo'
                ),
            ])
            ->first();

        $grouped = (clone $base)
            ->leftJoin('vendedores', 'vendedores.id', '=', 'ordens_servico.atendente_id')
            ->leftJoinSub($servicosSub, 'serv_agg', 'serv_agg.ordem_servico_id', '=', 'ordens_servico.id')
            ->select($select)
            ->groupByRaw($groupByRaw)
            ->orderByDesc(DB::raw("COALESCE(SUM({$os}.total_servicos), 0)"))
            ->orderByRaw("MAX({$vendedores}.nome)")
            ->limit(5000)
            ->get();

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $grouped->map(function (object $row) use ($multi, $labels): array {
            $qtdOs = (int) ($row->qtd_os ?? 0);
            $valor = (float) ($row->valor_servicos ?? 0);
            $ticket = $qtdOs > 0 ? $valor / $qtdOs : null;
            $qtdComTempo = (int) ($row->qtd_com_tempo ?? 0);
            $tempoMedio = $qtdComTempo > 0 && $row->tempo_medio_dias !== null
                ? (float) $row->tempo_medio_dias
                : null;

            $mapped = [
                'tecnico' => $this->formatTecnicoRow($row),
                'qtd_os' => static::formatQuantity((float) $qtdOs),
                'finalizadas' => static::formatQuantity((float) ($row->finalizadas ?? 0)),
                'pendentes' => static::formatQuantity((float) ($row->pendentes ?? 0)),
                'qtd_servicos' => static::formatQuantity((float) ($row->qtd_servicos ?? 0)),
                'valor_servicos' => static::formatMoney($valor),
                'ticket_medio' => $ticket !== null ? static::formatMoney($ticket) : '',
                'tempo_medio' => $tempoMedio !== null ? $this->formatTempoMedio($tempoMedio) : '',
            ];

            if ($multi) {
                $mapped['empresa'] = $labels[(int) ($row->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $mapped;
        })->all();

        $qtdOsTotal = (float) ($totaisAgg->qtd_os ?? 0);
        $valorTotal = (float) ($totaisAgg->valor_servicos ?? 0);
        $qtdComTempoTotal = (int) ($totaisAgg->qtd_com_tempo ?? 0);
        $tempoMedioTotal = $qtdComTempoTotal > 0 && $totaisAgg->tempo_medio_dias !== null
            ? (float) $totaisAgg->tempo_medio_dias
            : null;
        $ticketTotal = $qtdOsTotal > 0 ? $valorTotal / $qtdOsTotal : null;

        $situacaoLabel = match ($situacao) {
            'aberta' => 'ABERTAS',
            'finalizada' => 'FINALIZADAS',
            'cancelada' => 'CANCELADAS',
            default => 'TODOS',
        };

        $tecnicoLabel = match ($tecnicoFiltro) {
            '', 'todos' => 'TODOS',
            'sem' => 'SEM TÉCNICO',
            default => $this->tecnicoOptions()[$tecnicoFiltro]
                ?? $this->formatTecnico(Vendedor::query()->select(['id', 'codigo', 'nome'])->find((int) $tecnicoFiltro))
                ?: $tecnicoFiltro,
        };

        $limitHint = count($rows) >= 5000 ? ' (limite de exibição; totais do rodapé incluem todos os filtros)' : '';

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'tecnico' => $tecnicoFiltro !== '' ? $tecnicoFiltro : 'todos',
                'cliente' => $clienteQ,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA INÍCIO DA OS): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO: '.$situacaoLabel,
                'TÉCNICO: '.mb_strtoupper((string) $tecnicoLabel, 'UTF-8'),
                'AGRUPAMENTO: responsável da OS (atendente); OS sem técnico aparecem como SEM TÉCNICO',
                'TEMPO MÉDIO: apenas OS finalizadas/entregues com data início e término/entrega preenchidas',
                'TÉCNICOS: '.count($rows).$limitHint,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate($columns, [
            'qtd_os' => $qtdOsTotal,
            'finalizadas' => (float) ($totaisAgg->finalizadas ?? 0),
            'pendentes' => (float) ($totaisAgg->pendentes ?? 0),
            'qtd_servicos' => (float) ($totaisAgg->qtd_servicos ?? 0),
            'valor_servicos' => $valorTotal,
            'ticket_medio' => $ticketTotal,
            'tempo_medio' => $tempoMedioTotal,
        ]);

        return $built;
    }

    /**
     * @return Builder<\App\Models\OrdemServico>
     */
    private function filteredOsQuery(
        Request $request,
        string $de,
        string $ate,
        string $situacao,
        string $tecnicoFiltro,
        string $clienteQ,
    ): Builder {
        $query = OrdemServico::query()
            ->whereDate('ordens_servico.data_inicio', '>=', $de)
            ->whereDate('ordens_servico.data_inicio', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        $this->applySituacaoFilter($query, $situacao);

        if ($tecnicoFiltro === 'sem') {
            $query->whereNull('ordens_servico.atendente_id');
        } elseif ($tecnicoFiltro !== '' && $tecnicoFiltro !== 'todos' && ctype_digit($tecnicoFiltro)) {
            $query->where('ordens_servico.atendente_id', (int) $tecnicoFiltro);
        }

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

    private function formatTecnicoRow(object $row): string
    {
        $id = (int) ($row->tecnico_id ?? 0);

        if ($id <= 0) {
            return 'SEM TÉCNICO';
        }

        $codigo = trim((string) ($row->tecnico_codigo ?? ''));
        $nome = trim((string) ($row->tecnico_nome ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        if ($nome !== '') {
            return $nome;
        }

        if ($codigo !== '') {
            return $codigo;
        }

        return 'TÉCNICO #'.$id;
    }

    private function formatTempoMedio(float $dias): string
    {
        if ($dias < 0) {
            return '';
        }

        if (abs($dias - round($dias)) < 0.05) {
            $inteiro = (int) round($dias);

            return $inteiro.' dia'.($inteiro === 1 ? '' : 's');
        }

        return number_format($dias, 1, ',', '.').' dias';
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
        $footerNumeric = [
            'qtd_os',
            'finalizadas',
            'pendentes',
            'qtd_servicos',
            'valor_servicos',
            'ticket_medio',
            'tempo_medio',
        ];

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
                'valor_servicos', 'ticket_medio' => static::formatMoney((float) $value),
                'tempo_medio' => $this->formatTempoMedio((float) $value),
                default => static::formatQuantity((float) $value),
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
        $options = [
            'todos' => 'Todos',
            'sem' => 'Sem técnico',
        ];
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
