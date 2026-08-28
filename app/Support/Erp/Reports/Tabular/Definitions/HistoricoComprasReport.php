<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Compra;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

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
            'empresa' => 'EMPRESA',
            'data_emissao' => 'EMISSÃO',
            'nota' => 'NOTA',
            'fornecedor' => 'FORNECEDOR',
            'status' => 'STATUS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data_entrada',
            'data_emissao',
            'nota',
            'fornecedor',
            'status',
            'total',
        ];
    }

    public function numericColumns(): array
    {
        return ['total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'status',
                'label' => 'Status',
                'type' => 'select',
                'options' => ['todos' => 'Todos'] + Compra::statusLabels(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'data_entrada',
        );
        $status = (string) $request->query('status', 'todos');
        $multi = $this->isMultiEmpresaScope($request);

        $query = Compra::query()
            ->with(['fornecedor', 'empresa'])
            ->whereBetween('data_entrada', [$de->toDateString(), $ate->toDateString()])
            ->orderByDesc('data_entrada')
            ->orderByDesc('id');

        if (Schema::hasColumn((new Compra)->getTable(), 'empresa_id')) {
            ReportEmpresaScope::applyToQuery($query, $request, 'empresa_id');
        }

        if ($status !== 'todos') {
            $query->where('status', $status);
        }

        $rows = $query->limit(5000)->get()->map(function (Compra $compra) use ($multi): array {
            $row = [
                'numero' => (string) $compra->numero,
                'data_entrada' => static::formatDate($compra->data_entrada),
                'data_emissao' => static::formatDate($compra->data_emissao),
                'nota' => (string) ($compra->numero_nota ?? ''),
                'fornecedor' => (string) ($compra->fornecedor?->nome_razao ?? ''),
                'status' => Compra::statusLabels()[$compra->status] ?? (string) $compra->status,
                'total' => static::formatMoney((float) $compra->total),
            ];

            if ($multi) {
                $row['empresa'] = ReportEmpresaScope::labelEmpresa($compra->empresa);
            }

            return $row;
        })->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'status' => $status,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO: '.$this->periodLabel($de, $ate),
                'STATUS: '.mb_strtoupper($status === 'todos' ? 'TODOS' : (Compra::statusLabels()[$status] ?? $status), 'UTF-8'),
            ], $request),
        );
    }
}
