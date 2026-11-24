<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Models\PlanoConta;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlanoContasReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'plano-contas';
    }

    public function title(): string
    {
        return 'RELATÓRIO POR PLANO DE CONTAS';
    }

    public function permission(): string
    {
        return 'planos_contas.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'plano' => 'PLANO DE CONTAS',
            'entradas' => 'ENTRADAS',
            'pct_entradas' => '% ENTRADAS',
            'saidas' => 'SAÍDAS',
            'pct_saidas' => '% SAÍDAS',
            'saldo' => 'SALDO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['entradas', 'pct_entradas', 'saidas', 'pct_saidas', 'saldo'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'codigo' => 8,
            'plano' => 28,
            'entradas' => 14,
            'pct_entradas' => 10,
            'saidas' => 14,
            'pct_saidas' => 10,
            'saldo' => 14,
        ];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'plano',
                'label' => 'Plano de Contas',
                'type' => 'lookup',
                'placeholder' => 'Todos os planos',
                'options' => $this->planoOptions(),
            ],
            [
                'key' => 'ordem',
                'label' => 'Ordenar por',
                'type' => 'select',
                'options' => [
                    'codigo' => 'Código',
                    'maior_entrada' => 'Maior Entrada',
                    'maior_saida' => 'Maior Saída',
                ],
            ],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $planoId = (int) $request->query('plano', 0);
        $ordem = $this->resolveOrdem($request->query('ordem'));
        $planoFiltro = $planoId > 0 ? $this->resolverPlanoComSubplanos($planoId) : null;

        $query = CaixaLancamento::query()
            ->leftJoin('planos_contas', 'planos_contas.id', '=', 'caixa_lancamentos.plano_conta_id')
            ->whereBetween('caixa_lancamentos.emissao', [$de->toDateString(), $ate->toDateString()]);
        CaixaTransferencia::excluirDoQuery($query, 'caixa_lancamentos.documento');

        if ($planoFiltro !== null) {
            if ($planoFiltro['ids'] === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('caixa_lancamentos.plano_conta_id', $planoFiltro['ids']);
            }
        }

        /** @var list<array{codigo: string, plano: string, entradas: float, saidas: float}> $raw */
        $raw = $query
            ->groupBy(
                'caixa_lancamentos.plano_conta_id',
                'planos_contas.codigo',
                'planos_contas.descricao',
                'caixa_lancamentos.plano_contas',
            )
            ->limit(5000)
            ->get([
                'planos_contas.codigo',
                DB::raw(
                    'COALESCE(' . static::sqlTable('planos_contas') . '.descricao, '
                    . static::sqlTable('caixa_lancamentos') . '.plano_contas, \'SEM PLANO\') as plano'
                ),
                DB::raw('SUM(' . static::sqlTable('caixa_lancamentos') . '.entrada) as entradas'),
                DB::raw('SUM(' . static::sqlTable('caixa_lancamentos') . '.saida) as saidas'),
            ])
            ->map(static function ($row): array {
                return [
                    'codigo' => (string) ($row->codigo ?? ''),
                    'plano' => (string) $row->plano,
                    'entradas' => round((float) $row->entradas, 2),
                    'saidas' => round((float) $row->saidas, 2),
                ];
            })
            ->all();

        $totalEntradas = round(array_sum(array_column($raw, 'entradas')), 2);
        $totalSaidas = round(array_sum(array_column($raw, 'saidas')), 2);

        $comPct = array_map(static function (array $row) use ($totalEntradas, $totalSaidas): array {
            $entradas = (float) $row['entradas'];
            $saidas = (float) $row['saidas'];

            return [
                'codigo' => $row['codigo'],
                'plano' => $row['plano'],
                'entradas' => $entradas,
                'pct_entradas' => $totalEntradas > 0.009
                    ? round(($entradas / $totalEntradas) * 100, 2)
                    : 0.0,
                'saidas' => $saidas,
                'pct_saidas' => $totalSaidas > 0.009
                    ? round(($saidas / $totalSaidas) * 100, 2)
                    : 0.0,
                'saldo' => round($entradas - $saidas, 2),
            ];
        }, $raw);

        $this->ordenarLinhas($comPct, $ordem);

        $rows = array_map(static function (array $row): array {
            return [
                'codigo' => $row['codigo'],
                'plano' => $row['plano'],
                'entradas' => static::formatMoney((float) $row['entradas']),
                'pct_entradas' => static::formatPercent((float) $row['pct_entradas']),
                'saidas' => static::formatMoney((float) $row['saidas']),
                'pct_saidas' => static::formatPercent((float) $row['pct_saidas']),
                'saldo' => static::formatMoney((float) $row['saldo']),
            ];
        }, $comPct);

        $summary = ['PERÍODO: ' . $this->periodLabel($de, $ate)];

        if ($planoFiltro !== null) {
            $plano = $planoFiltro['plano'];
            if (! $plano instanceof PlanoConta) {
                $summary[] = 'PLANO: não encontrado';
            } else {
                $linha = 'PLANO: ' . $this->rotuloPlano($plano);
                if (count($planoFiltro['ids']) > 1) {
                    $linha .= ' (inclui subplanos)';
                }
                $summary[] = $linha;
            }
        }

        $summary[] = 'ORDENAÇÃO: '.$this->rotuloOrdem($ordem);

        $built = $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'plano' => $planoId > 0 ? (string) $planoId : '',
                'ordem' => $ordem,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            $summary,
        );

        // Totais de % ficam em 100% (não a soma arredondada linha a linha).
        if (isset($built['totals']['pct_entradas'])) {
            $built['totals']['pct_entradas'] = $totalEntradas > 0.009
                ? static::formatPercent(100.0)
                : static::formatPercent(0.0);
        }
        if (isset($built['totals']['pct_saidas'])) {
            $built['totals']['pct_saidas'] = $totalSaidas > 0.009
                ? static::formatPercent(100.0)
                : static::formatPercent(0.0);
        }

        return $built;
    }

    private function resolveOrdem(mixed $raw): string
    {
        $ordem = is_string($raw) ? trim($raw) : '';

        return in_array($ordem, ['codigo', 'maior_entrada', 'maior_saida'], true)
            ? $ordem
            : 'codigo';
    }

    private function rotuloOrdem(string $ordem): string
    {
        return match ($ordem) {
            'maior_entrada' => 'Maior Entrada',
            'maior_saida' => 'Maior Saída',
            default => 'Código',
        };
    }

    /**
     * @param  list<array{codigo: string, plano: string, entradas: float, pct_entradas: float, saidas: float, pct_saidas: float, saldo: float}>  $rows
     */
    private function ordenarLinhas(array &$rows, string $ordem): void
    {
        usort($rows, static function (array $a, array $b) use ($ordem): int {
            if ($ordem === 'maior_entrada') {
                $cmp = ((float) $b['entradas']) <=> ((float) $a['entradas']);
                if ($cmp !== 0) {
                    return $cmp;
                }
            } elseif ($ordem === 'maior_saida') {
                $cmp = ((float) $b['saidas']) <=> ((float) $a['saidas']);
                if ($cmp !== 0) {
                    return $cmp;
                }
            }

            $codigoCmp = strnatcasecmp((string) $a['codigo'], (string) $b['codigo']);
            if ($codigoCmp !== 0) {
                return $codigoCmp;
            }

            return strnatcasecmp((string) $a['plano'], (string) $b['plano']);
        });
    }

    /**
     * @return array<string, string>
     */
    private function planoOptions(): array
    {
        $options = [];

        foreach (
            PlanoConta::query()
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'conta_completa', 'descricao']) as $plano
        ) {
            $options[(string) $plano->id] = $this->rotuloPlano($plano);
        }

        return $options;
    }

    /**
     * Plano escolhido e descendentes: filhos por pai_codigo e por conta completa (1, 1.01, 1.01.001).
     *
     * @return array{plano: ?PlanoConta, ids: list<int>}
     */
    private function resolverPlanoComSubplanos(int $planoId): array
    {
        $raiz = PlanoConta::query()->whereKey($planoId)->first(['id', 'codigo', 'conta_completa', 'descricao']);

        if (! $raiz instanceof PlanoConta) {
            return ['plano' => null, 'ids' => []];
        }

        $ids = [(int) $raiz->id => true];
        $codigosVistos = [(int) $raiz->codigo => true];
        $pendentes = [(int) $raiz->codigo];

        $conta = trim((string) ($raiz->conta_completa ?? ''));

        if ($conta !== '') {
            $prefixo = addcslashes($conta, '\\%_') . '.%';

            foreach (
                PlanoConta::query()
                    ->where('conta_completa', 'like', $prefixo)
                    ->get(['id', 'codigo']) as $filho
            ) {
                $ids[(int) $filho->id] = true;
                $codigo = (int) $filho->codigo;

                if (! isset($codigosVistos[$codigo])) {
                    $codigosVistos[$codigo] = true;
                    $pendentes[] = $codigo;
                }
            }
        }

        for ($nivel = 0; $nivel < 12 && $pendentes !== []; $nivel++) {
            $lote = $pendentes;
            $pendentes = [];

            foreach (
                PlanoConta::query()
                    ->whereIn('pai_codigo', $lote)
                    ->get(['id', 'codigo']) as $filho
            ) {
                $ids[(int) $filho->id] = true;
                $codigo = (int) $filho->codigo;

                if (! isset($codigosVistos[$codigo])) {
                    $codigosVistos[$codigo] = true;
                    $pendentes[] = $codigo;
                }
            }
        }

        return ['plano' => $raiz, 'ids' => array_map('intval', array_keys($ids))];
    }

    private function rotuloPlano(PlanoConta $plano): string
    {
        $conta = trim((string) ($plano->conta_completa ?? ''));
        $codigo = $conta !== '' ? $conta : (string) $plano->codigo;

        return $codigo . ' — ' . mb_strtoupper((string) $plano->descricao, 'UTF-8');
    }
}
