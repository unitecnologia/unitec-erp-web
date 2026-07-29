<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Product;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class ConferenciaEstoqueReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'conferencia-estoque';
    }

    public function title(): string
    {
        return 'LISTAGEM — CONFERÊNCIA DE ESTOQUE';
    }

    public function permission(): string
    {
        return 'produtos.print';
    }

    public function columns(): array
    {
        return [
            'codigo' => 'CÓDIGO',
            'codigo_barras' => 'CÓD.BARRA',
            'descricao' => 'DESCRIÇÃO',
            'grupo' => 'GRUPO',
            'localizacao' => 'LOCALIZAÇÃO',
            'unidade' => 'UND',
            'estoque' => 'ESTOQUE',
            'conferido' => 'CONFERIDO',
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
            ['key' => 'q', 'label' => 'Pesquisa', 'type' => 'text'],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $grupo = trim((string) $request->query('grupo', ''));
        $q = trim((string) $request->query('q', ''));

        $rows = Product::query()
            ->where('ativo', true)
            ->when($grupo !== '', fn ($query) => $query->where('grupo', 'like', '%' . $grupo . '%'))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%' . $q . '%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('codigo', 'like', $like)
                        ->orWhere('descricao', 'like', $like)
                        ->orWhere('codigo_barras', 'like', $like);
                });
            })
            ->orderBy('descricao')
            ->limit(5000)
            ->get()
            ->map(fn (Product $product): array => [
                'codigo' => (string) ($product->codigo ?? ''),
                'codigo_barras' => (string) ($product->codigo_barras ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'localizacao' => (string) ($product->localizacao ?? ''),
                'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
                'estoque' => static::formatQuantity((float) $product->estoque),
                'conferido' => '',
            ])
            ->all();

        $summary = ['LISTAGEM PARA CONFERÊNCIA FÍSICA'];
        if ($grupo !== '') {
            $summary[] = 'GRUPO: ' . mb_strtoupper($grupo, 'UTF-8');
        }

        return $this->result(
            ['grupo' => $grupo, 'q' => $q, 'cols' => $columns],
            $columns,
            $rows,
            $summary,
            withTotals: false,
        );
    }
}
