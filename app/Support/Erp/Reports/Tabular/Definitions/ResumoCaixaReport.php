<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\CaixaLancamento;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResumoCaixaReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'resumo-caixa';
    }

    public function title(): string
    {
        return 'RESUMO CAIXA';
    }

    public function permission(): string
    {
        return 'caixa.print';
    }

    public function columns(): array
    {
        return [
            'data' => 'DATA',
            'entradas' => 'ENTRADAS',
            'saidas' => 'SAÍDAS',
            'saldo' => 'SALDO DO DIA',
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
            ->whereBetween('emissao', [$de->toDateString(), $ate->toDateString()])
            ->groupBy('emissao')
            ->orderBy('emissao')
            ->get([
                'emissao',
                DB::raw('SUM(entrada) as entradas'),
                DB::raw('SUM(saida) as saidas'),
            ])
            ->map(function ($row): array {
                $entradas = (float) $row->entradas;
                $saidas = (float) $row->saidas;

                return [
                    'data' => static::formatDate($row->emissao),
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
