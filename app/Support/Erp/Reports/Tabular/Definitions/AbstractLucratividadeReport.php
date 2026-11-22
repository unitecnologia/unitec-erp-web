<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadeProdutos\LucratividadeProdutosAggregator;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\LimitsRankingResults;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class AbstractLucratividadeReport extends AbstractTabularReport
{
    use LimitsRankingResults;

    abstract protected function sortOrder(): string;

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'qtd' => 'QTD',
            'receita' => 'RECEITA',
            'custo' => 'CUSTO',
            'lucro' => 'LUCRO',
            'margem' => 'MARGEM',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'receita', 'custo', 'lucro', 'margem'];
    }

    public function filterFields(): array
    {
        $fields = $this->withEmpresaFilter($this->periodFilterFields());
        $fields[] = $this->resultLimitField();

        return $this->withColumnsField($fields);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $resultLimit = $this->resultLimitFromRequest($request);
        $payload = app(LucratividadeProdutosAggregator::class)->aggregate($de, $ate, $request);
        /** @var Collection<int, object> $agg */
        $agg = $payload['rows'];
        $truncated = (bool) $payload['truncated'];
        $totalVendas = (int) $payload['total_vendas'];
        $processadas = (int) $payload['vendas_processadas'];
        $vendaLimit = (int) $payload['venda_limit'];

        // Ordena o ranking completo e só então aplica a quantidade de resultados.
        $sorted = $this->sortLucratividadeAgg($agg, $this->sortOrder());
        $rankedTotal = $sorted->count();
        $limited = $sorted->take($resultLimit)->values();
        $rows = $this->formatLucratividadeHistoricoRows($limited);

        $summary = [
            'PERÍODO: '.$this->periodLabel($de, $ate),
            'Exibindo '.count($rows).' de '.$rankedTotal.' produto(s) no ranking (limite '.$resultLimit.').',
            'Custo histórico D2.6 (fechamento). Sem substituição pelo custo cadastral atual.',
            'Receita com rateio de desconto/acréscimo de cabeçalho. Devoluções finalizadas abatidas. Canceladas fora.',
        ];

        if ($truncated) {
            $summary[] = 'LISTAGEM/RANKING PARCIAL: processadas '.$processadas.' de '.$totalVendas
                .' venda(s) fechadas (limite '.$vendaLimit.'). Totais abaixo não representam o universo completo.';
        }

        $anyIndisp = $limited->contains(static fn (object $r): bool => ! (bool) ($r->custo_disponivel ?? false));
        if ($anyIndisp) {
            $summary[] = 'Há produto(s) com custo Indisponível — lucro/margem dessas linhas e o rodapé de custo/lucro/margem não inventam valores.';
        }

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'qtd_resultados' => (string) $resultLimit,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary($summary, $request),
            withTotals: false,
        );

        // Rodapé reflete só as linhas exibidas (já limitadas após o ranking).
        $built['totals'] = $this->buildLucratividadeTotals($limited, $columns, $truncated);
        $built['meta'] = [
            'truncated' => $truncated,
            'total_count' => $totalVendas,
            'detail_limit' => $vendaLimit,
            'vendas_processadas' => $processadas,
            'result_limit' => $resultLimit,
            'ranked_total' => $rankedTotal,
        ];

        return $built;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    protected function buildLucratividadeTotals(Collection $rows, array $columns, bool $truncated): array
    {
        $indisp = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
        $sumQtd = 0.0;
        $sumReceita = 0.0;
        $sumCusto = 0.0;
        $sumLucro = 0.0;
        $allDisponivel = true;

        foreach ($rows as $row) {
            $sumQtd += (float) ($row->qtd ?? 0);
            $sumReceita += (float) ($row->receita ?? 0);
            if (! (bool) ($row->custo_disponivel ?? false)) {
                $allDisponivel = false;

                continue;
            }
            $sumCusto += (float) ($row->custo ?? 0);
            $sumLucro += (float) ($row->lucro ?? 0);
        }

        // Truncado ou qualquer Indisponível: não exibe custo/lucro/margem como totais completos.
        $custoLucroOk = $allDisponivel && ! $truncated && $rows->isNotEmpty();
        $margem = ($custoLucroOk && $sumReceita > 0.0000001)
            ? ($sumLucro / $sumReceita) * 100
            : null;

        $label = $truncated ? 'TOTAL PARCIAL' : 'TOTAL';
        $footer = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'qtd') {
                $footer[$column] = static::formatQuantity($sumQtd);

                continue;
            }
            if ($column === 'receita') {
                $footer[$column] = static::formatMoney($sumReceita);

                continue;
            }
            if ($column === 'custo') {
                $footer[$column] = $custoLucroOk ? static::formatMoney($sumCusto) : $indisp;

                continue;
            }
            if ($column === 'lucro') {
                $footer[$column] = $custoLucroOk ? static::formatMoney($sumLucro) : $indisp;

                continue;
            }
            if ($column === 'margem') {
                $footer[$column] = ($custoLucroOk && $margem !== null)
                    ? static::formatPercent($margem)
                    : $indisp;

                continue;
            }
            $footer[$column] = $labelPlaced ? '' : $label;
            $labelPlaced = true;
        }

        return $footer;
    }

    /**
     * Ordena o ranking por lucro (indisponíveis no fim).
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object>
     */
    protected function sortLucratividadeAgg(Collection $rows, string $order = 'desc'): Collection
    {
        return $rows->sort(function (object $a, object $b) use ($order): int {
            $da = (bool) ($a->custo_disponivel ?? false);
            $db = (bool) ($b->custo_disponivel ?? false);
            if ($da !== $db) {
                return $da ? -1 : 1;
            }
            if (! $da) {
                return strcmp((string) ($a->descricao ?? ''), (string) ($b->descricao ?? ''));
            }

            $la = (float) ($a->lucro ?? 0);
            $lb = (float) ($b->lucro ?? 0);

            return $order === 'asc' ? $la <=> $lb : $lb <=> $la;
        })->values();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function formatLucratividadeHistoricoRows(Collection $rows): array
    {
        $indisp = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

        return $rows->map(function (object $row) use ($indisp): array {
            $disponivel = (bool) ($row->custo_disponivel ?? false);

            return [
                'codigo' => (string) ($row->codigo ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
                'grupo' => (string) ($row->grupo ?? ''),
                'qtd' => AbstractTabularReport::formatQuantity((float) $row->qtd),
                'receita' => AbstractTabularReport::formatMoney((float) $row->receita),
                'custo' => $disponivel
                    ? AbstractTabularReport::formatMoney((float) $row->custo)
                    : $indisp,
                'lucro' => $disponivel && $row->lucro !== null
                    ? AbstractTabularReport::formatMoney((float) $row->lucro)
                    : $indisp,
                'margem' => $disponivel && $row->margem !== null
                    ? AbstractTabularReport::formatPercent((float) $row->margem)
                    : $indisp,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return list<array<string, mixed>>
     */
    protected function mapLucratividadeHistoricoRows(Collection $rows, string $order = 'desc'): array
    {
        return $this->formatLucratividadeHistoricoRows($this->sortLucratividadeAgg($rows, $order));
    }
}
