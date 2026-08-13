<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Orcamento;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class HistoricoOrcamentosReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-orcamentos';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE ORÇAMENTOS';
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
            'status' => 'STATUS',
            'plataforma' => 'ORIGEM',
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
                'options' => ['todos' => 'Todos'] + Orcamento::statusLabels(),
            ],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $status = (string) $request->query('status', 'todos');

        $query = Orcamento::query()
            ->with(['cliente', 'vendedor'])
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $rows = $query->limit(5000)->get()->map(function (Orcamento $orcamento): array {
            return [
                'numero' => (string) $orcamento->numero,
                'data' => static::formatDate($orcamento->data),
                'cliente' => (string) ($orcamento->cliente?->nome_razao ?? ''),
                'vendedor' => (string) ($orcamento->vendedor?->nome ?? ''),
                'forma' => (string) ($orcamento->forma_pagamento ?? ''),
                'status' => $orcamento->statusLabel(),
                'plataforma' => Orcamento::plataformaLabels()[$orcamento->plataformaEfetiva()] ?? (string) $orcamento->plataforma,
                'total' => static::formatMoney((float) $orcamento->total),
            ];
        })->all();

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
                'STATUS: ' . mb_strtoupper($status === 'todos' ? 'TODOS' : (Orcamento::statusLabels()[$status] ?? $status), 'UTF-8'),
            ],
        );
    }
}
