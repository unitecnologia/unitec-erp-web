<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Compra;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class HistoricoComprasReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'historico-compras';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE COMPRAS';
    }

    public function permission(): string
    {
        return 'compras.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data_entrada' => 'ENTRADA',
            'data_emissao' => 'EMISSÃO',
            'nota' => 'NOTA',
            'fornecedor' => 'FORNECEDOR',
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
                'options' => ['todos' => 'Todos'] + Compra::statusLabels(),
            ],
        ]);
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $status = (string) $request->query('status', 'todos');

        $query = Compra::query()
            ->with('fornecedor')
            ->whereBetween('data_entrada', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('data_entrada')
            ->orderByDesc('id');

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $rows = $query->limit(5000)->get()->map(fn (Compra $compra): array => [
            'numero' => (string) $compra->numero,
            'data_entrada' => static::formatDate($compra->data_entrada),
            'data_emissao' => static::formatDate($compra->data_emissao),
            'nota' => (string) ($compra->numero_nota ?? ''),
            'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? ''),
            'status' => Compra::statusLabels()[$compra->status] ?? (string) $compra->status,
            'total' => static::formatMoney((float) $compra->total),
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
                'STATUS: ' . mb_strtoupper($status === 'todos' ? 'TODOS' : (Compra::statusLabels()[$status] ?? $status), 'UTF-8'),
            ],
        );
    }
}
