<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OrdemServico;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OS por forma de pagamento — valor faturado do snapshot do faturamento
 * (ordens_servico.faturamento_pagamentos). Não usa venda espelho nem valor recebido.
 *
 * Layout idêntico a VendasFormaPagamentoReport: Forma, Qtd, Total.
 */
class OsPorFormaPagamentoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'os-por-forma-pagamento';
    }

    public function title(): string
    {
        return 'OS POR FORMA DE PAGAMENTO';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'empresa' => 'EMPRESA',
            'forma' => 'FORMA DE PAGAMENTO',
            'qtd' => 'QTD',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return ['forma', 'qtd', 'total'];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'forma',
                'label' => 'Forma de pagamento',
                'type' => 'select',
                'options' => $this->formaPagamentoFilterOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: '',
        );
        $multi = $this->isMultiEmpresaScope($request);
        $hasEmpresa = Schema::hasColumn((new OrdemServico)->getTable(), 'empresa_id');
        $formaFiltro = $this->resolveFormaFiltro($request);
        $cadastro = VendasPorFormaPagamentoAggregator::formasCadastro();
        $empresaScope = $hasEmpresa ? ReportEmpresaScope::resolveIds($request) : null;
        $groupByEmpresa = $multi && $hasEmpresa;

        $aggregated = $this->aggregate(
            $de->toDateString(),
            $ate->toDateString(),
            $empresaScope,
            $formaFiltro,
            $groupByEmpresa,
            $cadastro,
        );

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = collect($aggregated)
            ->map(function (array $row) use ($multi, $labels): array {
                $mapped = [
                    'forma' => $row['forma'],
                    'qtd' => $row['qtd'],
                    'total' => $row['total'],
                    '_codigo' => $row['codigo'],
                ];

                if ($multi) {
                    $empresaId = (int) ($row['empresa_id'] ?? 0);
                    $mapped['empresa'] = $labels[$empresaId]
                        ?? ReportEmpresaScope::labelEmpresa(null);
                    $mapped['_empresa_id'] = $empresaId;
                }

                return $mapped;
            })
            ->sort(function (array $a, array $b) use ($multi): int {
                if ($multi) {
                    $empresaCmp = ($a['_empresa_id'] ?? 0) <=> ($b['_empresa_id'] ?? 0);
                    if ($empresaCmp !== 0) {
                        return $empresaCmp;
                    }
                }

                $codigoA = $a['_codigo'] ?? PHP_INT_MAX;
                $codigoB = $b['_codigo'] ?? PHP_INT_MAX;
                if ($codigoA !== $codigoB) {
                    return $codigoA <=> $codigoB;
                }

                return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
            })
            ->values()
            ->map(function (array $row): array {
                return array_filter([
                    'empresa' => $row['empresa'] ?? null,
                    'forma' => $row['forma'],
                    'qtd' => static::formatQuantity((float) $row['qtd']),
                    'total' => static::formatMoney((float) $row['total']),
                ], fn ($v) => $v !== null);
            })
            ->all();

        $summary = [
            'PERÍODO (DATA FATURAMENTO): '.$this->periodLabel($de, $ate),
            'TOTAL = valor faturado (snapshot do faturamento); não é valor recebido',
        ];
        if ($formaFiltro !== '') {
            $summary[] = 'FORMA: '.VendasPorFormaPagamentoAggregator::rotuloFormaCadastro($formaFiltro, $cadastro);
        }

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'forma' => $formaFiltro,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary($summary, $request),
        );
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @param  \Illuminate\Support\Collection<int, \App\Models\FormaPagamento>  $cadastro
     * @return list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>
     */
    private function aggregate(
        string $de,
        string $ate,
        int|array|null $empresaScope,
        string $formaFiltro,
        bool $groupByEmpresa,
        $cadastro,
    ): array {
        if (is_array($empresaScope) && $empresaScope === []) {
            return [];
        }

        $aggregated = [];
        $this->somarDoSnapshot($aggregated, $de, $ate, $empresaScope, $groupByEmpresa, $cadastro);
        $this->somarSemSnapshot($aggregated, $de, $ate, $empresaScope, $groupByEmpresa, $cadastro);

        $filtro = mb_strtoupper(trim($formaFiltro), 'UTF-8');
        $rows = array_values($aggregated);

        if ($filtro !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => mb_strtoupper((string) $row['forma'], 'UTF-8') === $filtro,
            ));
        }

        return $rows;
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  int|list<int>|null  $empresaScope
     * @param  \Illuminate\Support\Collection<int, \App\Models\FormaPagamento>  $cadastro
     */
    private function somarDoSnapshot(
        array &$aggregated,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        $cadastro,
    ): void {
        if (! Schema::hasColumn((new OrdemServico)->getTable(), 'faturamento_pagamentos')) {
            return;
        }

        $os = static::sqlTable('ordens_servico');
        $finalizadas = "'".OrdemServico::SITUACAO_FINALIZADA."','".OrdemServico::SITUACAO_ENTREGUE."'";

        $empresaSql = '';
        $bindings = [$de, $ate];

        if (is_int($empresaScope) && $empresaScope > 0) {
            $empresaSql = " AND os.empresa_id = ?";
            $bindings[] = $empresaScope;
        } elseif (is_array($empresaScope)) {
            $ids = array_values(array_filter(array_map('intval', $empresaScope), static fn (int $id): bool => $id > 0));
            if ($ids === []) {
                return;
            }
            $empresaSql = ' AND os.empresa_id IN ('.implode(',', array_fill(0, count($ids), '?')).')';
            array_push($bindings, ...$ids);
        }

        $empresaSelect = $groupByEmpresa ? 'os.empresa_id,' : '';
        $empresaGroup = $groupByEmpresa ? 'os.empresa_id, ' : '';

        $sql = "
            SELECT {$empresaSelect} jt.forma AS forma, COUNT(*) AS qtd, COALESCE(SUM(jt.valor), 0) AS total
            FROM {$os} AS os
            CROSS JOIN JSON_TABLE(
                os.faturamento_pagamentos,
                '\$[*]' COLUMNS (
                    forma VARCHAR(120) CHARACTER SET utf8mb4 PATH '\$.forma',
                    valor DECIMAL(15,2) PATH '\$.valor'
                )
            ) AS jt
            WHERE os.situacao IN ({$finalizadas})
              AND os.data_termino IS NOT NULL
              AND DATE(os.data_termino) >= ?
              AND DATE(os.data_termino) <= ?
              AND os.faturamento_pagamentos IS NOT NULL
              AND JSON_VALID(os.faturamento_pagamentos)
              AND JSON_LENGTH(os.faturamento_pagamentos) > 0
              AND jt.valor > 0
              {$empresaSql}
            GROUP BY {$empresaGroup} jt.forma
        ";

        foreach (DB::select($sql, $bindings) as $row) {
            $this->somarLinha(
                $aggregated,
                (string) ($row->forma ?? ''),
                (float) ($row->qtd ?? 0),
                (float) ($row->total ?? 0),
                $groupByEmpresa ? (int) ($row->empresa_id ?? 0) : 0,
                $groupByEmpresa,
                $cadastro,
            );
        }
    }

    /**
     * OS faturadas sem snapshot utilizável → total_geral em "NÃO INFORMADA".
     *
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  int|list<int>|null  $empresaScope
     * @param  \Illuminate\Support\Collection<int, \App\Models\FormaPagamento>  $cadastro
     */
    private function somarSemSnapshot(
        array &$aggregated,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        $cadastro,
    ): void {
        $query = OrdemServico::query()
            ->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ])
            ->whereNotNull('ordens_servico.data_termino')
            ->whereDate('ordens_servico.data_termino', '>=', $de)
            ->whereDate('ordens_servico.data_termino', '<=', $ate);

        if (is_int($empresaScope) && $empresaScope > 0) {
            $query->where('ordens_servico.empresa_id', $empresaScope);
        } elseif (is_array($empresaScope)) {
            $ids = array_values(array_filter(array_map('intval', $empresaScope), static fn (int $id): bool => $id > 0));
            if ($ids === []) {
                return;
            }
            $query->whereIn('ordens_servico.empresa_id', $ids);
        }

        $osTable = static::sqlTable('ordens_servico');

        if (Schema::hasColumn((new OrdemServico)->getTable(), 'faturamento_pagamentos')) {
            $query->where(function (Builder $q) use ($osTable): void {
                $q->whereNull('ordens_servico.faturamento_pagamentos')
                    ->orWhere('ordens_servico.faturamento_pagamentos', '')
                    ->orWhere('ordens_servico.faturamento_pagamentos', '[]')
                    ->orWhere('ordens_servico.faturamento_pagamentos', 'null')
                    ->orWhereRaw("NOT JSON_VALID({$osTable}.faturamento_pagamentos)")
                    ->orWhereRaw("JSON_LENGTH({$osTable}.faturamento_pagamentos) = 0");
            });
        }

        $select = [
            DB::raw('COUNT(*) as qtd'),
            DB::raw("COALESCE(SUM({$osTable}.total_geral), 0) as total"),
        ];

        if ($groupByEmpresa) {
            $select[] = 'ordens_servico.empresa_id';
            $query->groupBy('ordens_servico.empresa_id');
        }

        foreach ($query->select($select)->get() as $row) {
            if ((float) ($row->qtd ?? 0) <= 0) {
                continue;
            }

            $this->somarLinha(
                $aggregated,
                '',
                (float) $row->qtd,
                (float) ($row->total ?? 0),
                $groupByEmpresa ? (int) ($row->empresa_id ?? 0) : 0,
                $groupByEmpresa,
                $cadastro,
            );
        }
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  \Illuminate\Support\Collection<int, \App\Models\FormaPagamento>  $cadastro
     */
    private function somarLinha(
        array &$aggregated,
        string $formaBruta,
        float $qtd,
        float $total,
        int $empresaId,
        bool $groupByEmpresa,
        $cadastro,
    ): void {
        $base = VendasPorFormaPagamentoAggregator::formaBase($formaBruta);

        if (VendasPorFormaPagamentoAggregator::isFormaMista($base)) {
            return;
        }

        $label = VendasPorFormaPagamentoAggregator::rotuloFormaCadastro($base, $cadastro);
        $key = ($groupByEmpresa ? $empresaId.'|' : '').mb_strtoupper($label, 'UTF-8');
        $codigo = VendasPorFormaPagamentoAggregator::codigoFormaCadastro($base, $cadastro);

        if (! isset($aggregated[$key])) {
            $aggregated[$key] = [
                'empresa_id' => $groupByEmpresa ? $empresaId : 0,
                'forma' => $label,
                'qtd' => 0.0,
                'total' => 0.0,
                'codigo' => $codigo,
            ];
        }

        $aggregated[$key]['qtd'] += $qtd;
        $aggregated[$key]['total'] += $total;

        if ($codigo < $aggregated[$key]['codigo']) {
            $aggregated[$key]['codigo'] = $codigo;
            $aggregated[$key]['forma'] = $label;
        }
    }

    /**
     * @return array<string, string>
     */
    private function formaPagamentoFilterOptions(): array
    {
        $options = ['' => 'Todas'];

        foreach (VendasPorFormaPagamentoAggregator::formasCadastro() as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
            if ($descricao === '') {
                continue;
            }
            $options[$descricao] = $descricao;
        }

        return $options;
    }

    private function resolveFormaFiltro(Request $request): string
    {
        return mb_strtoupper(trim((string) $request->query('forma', '')), 'UTF-8');
    }
}
