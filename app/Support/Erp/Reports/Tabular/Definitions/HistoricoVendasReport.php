<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Venda;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class HistoricoVendasReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-vendas';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE VENDAS';
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
            'cliente' => 'CLIENTE',
            'vendedor' => 'VENDEDOR',
            'forma' => 'FORMA PAGTO',
            'tipo' => 'TIPO',
            'plataforma' => 'ORIGEM',
            'status' => 'STATUS',
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
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => ['todos' => 'Todos'] + Venda::statusLabels(),
            ],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $status = (string) $request->query('status', Venda::STATUS_FECHADO);

        $query = Venda::query()
            ->with(['cliente', 'vendedor'])
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $rows = $query->limit(5000)->get()->map(fn (Venda $venda): array => [
            'numero' => (string) $venda->numero,
            'data' => static::formatDate($venda->data),
            'cliente' => (string) ($venda->cliente?->nome_razao ?? ''),
            'vendedor' => $venda->vendedorNome(),
            'forma' => (string) ($venda->forma_pagamento ?? ''),
            'tipo' => Venda::tipoLabels()[$venda->tipo] ?? (string) $venda->tipo,
            'plataforma' => $venda->plataformaLabel(),
            'status' => Venda::statusLabels()[$venda->status] ?? (string) $venda->status,
            'total' => static::formatMoney((float) $venda->total),
        ])->all();

        return $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'status' => $status,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            [
                'PERÍODO: ' . $this->periodLabel($de, $ate),
                'STATUS: ' . mb_strtoupper($status === 'todos' ? 'TODOS' : (Venda::statusLabels()[$status] ?? $status), 'UTF-8'),
            ],
        );
    }
}
