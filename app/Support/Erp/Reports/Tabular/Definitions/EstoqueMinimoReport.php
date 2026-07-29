<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Product;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class EstoqueMinimoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'estoque-minimo';
    }

    public function title(): string
    {
        return 'ESTOQUE — MÍNIMO';
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
            'estoque' => 'ESTOQUE',
            'estoque_minimo' => 'MÍNIMO',
            'falta' => 'FALTA',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['estoque', 'estoque_minimo', 'falta'];
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
            ->where('ativo', true)
            ->whereColumn('estoque', '<', 'estoque_minimo')
            ->when($grupo !== '', fn ($q) => $q->where('grupo', 'like', '%' . $grupo . '%'))
            ->orderBy('descricao')
            ->limit(5000)
            ->get()
            ->map(function (Product $product): array {
                $falta = max(0, (float) $product->estoque_minimo - (float) $product->estoque);

                return [
                    'codigo' => (string) ($product->codigo ?? ''),
                    'descricao' => (string) ($product->descricao ?? ''),
                    'grupo' => (string) ($product->grupo ?? ''),
                    'estoque' => static::formatQuantity((float) $product->estoque),
                    'estoque_minimo' => static::formatQuantity((float) $product->estoque_minimo),
                    'falta' => static::formatQuantity($falta),
                ];
            })
            ->all();

        $summary = ['FILTRO: ABAIXO DO MÍNIMO'];
        if ($grupo !== '') {
            $summary[] = 'GRUPO: ' . mb_strtoupper($grupo, 'UTF-8');
        }

        return $this->result(
            ['grupo' => $grupo, 'cols' => $columns],
            $columns,
            $rows,
            $summary,
        );
    }
}
