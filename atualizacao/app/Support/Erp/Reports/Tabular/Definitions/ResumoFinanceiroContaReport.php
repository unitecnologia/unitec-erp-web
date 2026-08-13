<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResumoFinanceiroContaReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'resumo-financeiro-conta';
    }

    public function title(): string
    {
        return 'RESUMO FINANCEIRO POR CONTA';
    }

    public function permission(): string
    {
        return 'contas_caixa.print';
    }

    public function columns(): array
    {
        return [
            'conta' => 'CONTA',
            'tipo' => 'TIPO',
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
            ->leftJoin('caixa_contas', 'caixa_contas.id', '=', 'caixa_lancamentos.caixa_conta_id')
            ->whereBetween('caixa_lancamentos.emissao', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('caixa_lancamentos.caixa_conta_id', 'caixa_contas.nome', 'caixa_contas.tipo')
            ->orderBy('caixa_contas.nome')
            ->limit(5000)
            ->get([
                'caixa_contas.nome as conta',
                'caixa_contas.tipo',
                DB::raw('SUM(' . static::sqlTable('caixa_lancamentos') . '.entrada) as entradas'),
                DB::raw('SUM(' . static::sqlTable('caixa_lancamentos') . '.saida) as saidas'),
            ])
            ->map(function ($row): array {
                $entradas = (float) $row->entradas;
                $saidas = (float) $row->saidas;

                return [
                    'conta' => (string) ($row->conta ?: 'SEM CONTA'),
                    'tipo' => (string) ($row->tipo ?: ''),
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
