<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Product;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class EstoqueNegativoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'estoque-negativo';
    }

    public function title(): string
    {
        return 'ESTOQUE — NEGATIVO';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'unidade' => 'UND',
            'estoque' => 'ESTOQUE',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['estoque'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ['key' => 'grupo', 'label' => 'Grupo', 'type' => 'text'],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $grupo = trim((string) $request->query('grupo', ''));

        $rows = Product::query()
            ->where('estoque', '<', 0)
            ->when($grupo !== '', fn ($q) => $q->where('grupo', 'like', '%' . $grupo . '%'))
            ->orderBy('estoque')
            ->limit(5000)
            ->get()
            ->map(fn (Product $product): array => [
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
                'estoque' => static::formatQuantity((float) $product->estoque),
            ])
            ->all();

        return $this->result(
            ['grupo' => $grupo, 'cols' => $columns],
            $columns,
            $rows,
            ['FILTRO: ESTOQUE NEGATIVO'],
        );
    }
}
