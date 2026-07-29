<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\PdvVenda;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendasPdvReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'vendas-pdv';
    }

    public function title(): string
    {
        return 'VENDAS POR PDV';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data' => 'DATA',
            'operador' => 'OPERADOR',
            'cliente' => 'CLIENTE',
            'forma' => 'FORMA PAGTO',
            'situacao' => 'SITUAÇÃO',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        $rows = PdvVenda::query()
            ->with(['person', 'user', 'vendedor'])
            ->whereBetween(DB::raw('DATE(COALESCE(fechado_em, created_at))'), [$de->toDateString(), $ate->toDateString()])
            ->where(function ($q): void {
                $q->whereNull('situacao')
                    ->orWhere('situacao', '!=', 'estornada');
            })
            ->orderByDesc('fechado_em')
            ->limit(5000)
            ->get()
            ->map(function (PdvVenda $venda): array {
                $data = $venda->fechado_em ?? $venda->created_at;

                return [
                    'numero' => (string) ($venda->numero ?? ''),
                    'data' => $data ? $data->format('d/m/Y H:i') : '',
                    'operador' => (string) ($venda->vendedor_nome ?: $venda->vendedor?->nome ?: $venda->user?->name ?: ''),
                    'cliente' => (string) ($venda->person?->nome_razao ?? ''),
                    'forma' => (string) ($venda->forma_pagamento ?? ''),
                    'situacao' => (string) ($venda->situacao ?: 'fechada'),
                    'total' => static::formatMoney((float) $venda->total),
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
