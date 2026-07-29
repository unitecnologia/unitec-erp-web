<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
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
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $rows = CaixaLancamento::query()
            ->leftJoin('planos_contas', 'planos_contas.id', '=', 'caixa_lancamentos.plano_conta_id')
            ->whereBetween('caixa_lancamentos.emissao', [$de->toDateString(), $ate->toDateString()])
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

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
        );
    }
}
