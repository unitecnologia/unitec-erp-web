<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Nfe;
use App\Models\NfeItem;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VendasCfopCsosnReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'vendas-cfop-csosn';
    }

    public function title(): string
    {
        return 'VENDAS POR CFOP / CSOSN';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'cfop' => 'CFOP',
            'csosn' => 'CSOSN',
            'qtd' => 'QTD ITENS',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['qtd', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->periodFilterFields());
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        if (Schema::hasTable('nfe_itens') && Schema::hasTable('nfes')) {
            $rows = NfeItem::query()
                ->join('nfes', 'nfes.id', '=', 'nfe_itens.nfe_id')
                ->whereBetween('nfes.data_emissao', [$de->toDateString(), $ate->toDateString()])
                ->when(
                    Schema::hasColumn('nfes', 'status'),
                    fn ($q) => $q->whereIn('nfes.status', [
                        Nfe::STATUS_TRANSMITIDA,
                        Nfe::STATUS_CONTINGENCIA,
                    ]),
                )
                ->groupBy('nfe_itens.cfop', 'nfe_itens.csosn')
                ->orderBy('nfe_itens.cfop')
                ->limit(5000)
                ->get([
                    'nfe_itens.cfop',
                    'nfe_itens.csosn',
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM(' . static::sqlTable('nfe_itens') . '.total) as total'),
                ])
                ->map(fn ($row): array => [
                    'cfop' => (string) ($row->cfop ?: '—'),
                    'csosn' => (string) ($row->csosn ?: '—'),
                    'qtd' => static::formatQuantity((float) $row->qtd),
                    'total' => static::formatMoney((float) $row->total),
                ])
                ->all();
        } else {
            // Fallback: CFOP/CSOSN do cadastro do produto nas vendas
            $rows = VendaItem::query()
                ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
                ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
                ->where('vendas.status', Venda::STATUS_FECHADO)
                ->whereBetween('vendas.data', [$de->toDateString(), $ate->toDateString()])
                ->groupBy('products.cfop_interno', 'products.csosn')
                ->orderBy('products.cfop_interno')
                ->limit(5000)
                ->get([
                    'products.cfop_interno as cfop',
                    'products.csosn',
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw('SUM(' . static::sqlTable('venda_itens') . '.total) as total'),
                ])
                ->map(fn ($row): array => [
                    'cfop' => (string) ($row->cfop ?: '—'),
                    'csosn' => (string) ($row->csosn ?: '—'),
                    'qtd' => static::formatQuantity((float) $row->qtd),
                    'total' => static::formatMoney((float) $row->total),
                ])
                ->all();
        }

        return $this->result(
            ['de' => $de->toDateString(), 'ate' => $ate->toDateString(), 'cols' => $columns],
            $columns,
            $rows,
            ['PERÍODO: ' . $this->periodLabel($de, $ate)],
        );
    }
}
