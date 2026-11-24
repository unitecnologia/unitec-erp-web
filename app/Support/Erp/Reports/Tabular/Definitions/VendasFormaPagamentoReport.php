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
            'participacao' => '% PARTICIPAÇÃO',
        ];
    }

    public function defaultColumns(): array
    {
        return ['forma', 'qtd', 'total', 'participacao'];
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total', 'participacao'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'empresa' => 18,
            'forma' => 36,
            'qtd' => 10,
            'total' => 18,
            'participacao' => 14,
        ];
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
            [
                'key' => 'ordem',
                'label' => 'Ordenar por',
                'type' => 'select',
                'options' => $this->ordemOptions(),
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
        $ordem = $this->resolveOrdem($request->query('ordem'));
        $cadastro = VendasPorFormaPagamentoAggregator::formasCadastro();
        $empresaScope = $hasEmpresa ? ReportEmpresaScope::resolveIds($request) : null;

        $detailed = VendasPorFormaPagamentoAggregator::aggregateDetailed(
            $de,
            $ate,
            $empresaScope,
            $formaFiltro,
            $multi && $hasEmpresa,
        );

        $aggregated = $detailed['rows'];
        $vendasUnicas = (int) $detailed['vendas_unicas'];
        $totalGeral = (float) $detailed['total'];
        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $mapped = collect($aggregated)
            ->map(function (array $row) use ($multi, $labels, $totalGeral): array {
                $total = (float) $row['total'];
                $item = [
                    'forma' => $row['forma'],
                    'qtd' => (float) $row['qtd'],
                    'total' => $total,
                    'participacao' => $totalGeral > 0.009
                        ? round(($total / $totalGeral) * 100, 2)
                        : 0.0,
                    '_codigo' => $row['codigo'],
                ];

                if ($multi) {
                    $empresaId = (int) ($row['empresa_id'] ?? 0);
                    $item['empresa'] = $labels[$empresaId]
                        ?? ReportEmpresaScope::labelEmpresa(null);
                    $item['_empresa_id'] = $empresaId;
                }

                return $item;
            })
            ->all();

        $this->ordenarLinhas($mapped, $ordem, $multi);

        $rows = array_map(static function (array $row) use ($multi): array {
            $out = [
                'forma' => $row['forma'],
                'qtd' => static::formatQuantity((float) $row['qtd']),
                'total' => static::formatMoney((float) $row['total']),
                'participacao' => static::formatPercent((float) $row['participacao']),
            ];

            if ($multi) {
                $out = ['empresa' => $row['empresa'] ?? ''] + $out;
            }

            return array_filter($out, static fn ($v) => $v !== null);
        }, $mapped);

        $summary = [
            'PERÍODO: '.$this->periodLabel($de, $ate),
            'FATURAMENTO por forma (inclui crédito em Contas a Receber); não é recebimento de caixa',
            'QTD = participações por forma (venda mista conta em cada meio)',
            'VENDAS ÚNICAS: '.static::formatQuantity((float) $vendasUnicas)
                .' | TOTAL: '.static::formatMoney($totalGeral),
            'ORDENAÇÃO: '.$this->rotuloOrdem($ordem),
        ];
        if ($formaFiltro !== '') {
            $summary[] = 'FORMA: '.VendasPorFormaPagamentoAggregator::rotuloFormaCadastro($formaFiltro, $cadastro);
        }

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'forma' => $formaFiltro,
                'ordem' => $ordem,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary($summary, $request),
        );

        // Totais: QTD = vendas únicas; % = 100% do faturamento listado.
        if (isset($built['totals']['qtd'])) {
            $built['totals']['qtd'] = static::formatQuantity((float) $vendasUnicas);
        }
        if (isset($built['totals']['participacao'])) {
            $built['totals']['participacao'] = $totalGeral > 0.009
                ? static::formatPercent(100.0)
                : static::formatPercent(0.0);
        }

        return $built;
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

    /**
     * @return array<string, string>
     */
    private function ordemOptions(): array
    {
        return [
            'forma' => 'Forma de Pagamento',
            'maior_valor' => 'Maior Valor',
        ];
    }

    private function resolveOrdem(mixed $raw): string
    {
        $ordem = is_string($raw) ? trim($raw) : '';

        return array_key_exists($ordem, $this->ordemOptions()) ? $ordem : 'forma';
    }

    private function rotuloOrdem(string $ordem): string
    {
        return $this->ordemOptions()[$ordem] ?? 'Forma de Pagamento';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function ordenarLinhas(array &$rows, string $ordem, bool $multi): void
    {
        usort($rows, static function (array $a, array $b) use ($ordem, $multi): int {
            if ($multi) {
                $empresaCmp = ((int) ($a['_empresa_id'] ?? 0)) <=> ((int) ($b['_empresa_id'] ?? 0));
                if ($empresaCmp !== 0) {
                    return $empresaCmp;
                }
            }

            if ($ordem === 'maior_valor') {
                $byTotal = ((float) ($b['total'] ?? 0)) <=> ((float) ($a['total'] ?? 0));
                if ($byTotal !== 0) {
                    return $byTotal;
                }
            }

            $codigoA = (int) ($a['_codigo'] ?? PHP_INT_MAX);
            $codigoB = (int) ($b['_codigo'] ?? PHP_INT_MAX);
            if ($codigoA !== $codigoB) {
                return $codigoA <=> $codigoB;
            }

            return strcmp((string) ($a['forma'] ?? ''), (string) ($b['forma'] ?? ''));
        });
    }
}
