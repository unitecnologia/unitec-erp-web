<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class VendasFormaPagamentoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'vendas-forma-pagamento';
    }

    public function title(): string
    {
        return 'VENDAS POR FORMA DE PAGAMENTO';
    }

    public function permission(): string
    {
        return 'vendas.print';
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
        $hasEmpresa = Schema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $formaFiltro = $this->resolveFormaFiltro($request);
        $cadastro = VendasPorFormaPagamentoAggregator::formasCadastro();
        $empresaScope = $hasEmpresa ? ReportEmpresaScope::resolveIds($request) : null;

        $aggregated = VendasPorFormaPagamentoAggregator::aggregate(
            $de,
            $ate,
            $empresaScope,
            $formaFiltro,
            $multi && $hasEmpresa,
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

        $summary = ['PERÍODO: '.$this->periodLabel($de, $ate)];
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
