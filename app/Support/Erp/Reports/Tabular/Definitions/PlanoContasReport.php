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
            'saidas' => 'SAÍDAS',
            'saldo' => 'SALDO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['entradas', 'saidas', 'saldo'];
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
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $planoId = (int) $request->query('plano', 0);
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

        $rows = $query
            ->groupBy(
                'caixa_lancamentos.plano_conta_id',
                'planos_contas.codigo',
                'planos_contas.descricao',
                'caixa_lancamentos.plano_contas',
            )
            ->orderBy('planos_contas.codigo')
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
            ->map(function ($row): array {
                $entradas = (float) $row->entradas;
                $saidas = (float) $row->saidas;

                return [
                    'codigo' => (string) ($row->codigo ?? ''),
                    'plano' => (string) $row->plano,
                    'entradas' => static::formatMoney($entradas),
                    'saidas' => static::formatMoney($saidas),
                    'saldo' => static::formatMoney($entradas - $saidas),
                ];
            })
            ->all();

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

        return $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'plano' => $planoId > 0 ? (string) $planoId : '',
                'cols' => $columns,
            ],
            $columns,
            $rows,
            $summary,
        );
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
