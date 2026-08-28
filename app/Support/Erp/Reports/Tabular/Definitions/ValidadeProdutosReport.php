<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Product;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\ResolvesValidadeSituacao;
use Illuminate\Http\Request;

class ValidadeProdutosReport extends AbstractTabularReport
{
    use ResolvesValidadeSituacao;

    public function slug(): string
    {
        return 'validade-produtos';
    }

    public function title(): string
    {
        return 'LISTAGEM — VALIDADE DE PRODUTOS';
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
            'validade' => 'VALIDADE',
            'dias' => 'DIAS',
            'situacao' => 'SITUAÇÃO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['estoque', 'dias'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ['key' => 'grupo', 'label' => 'Grupo', 'type' => 'text'],
            ['key' => 'q', 'label' => 'Pesquisa', 'type' => 'text'],
            [
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => static::situacaoOptions(),
            ],
            ['key' => 'dias_alerta', 'label' => 'Dias alerta', 'type' => 'text'],
        ]);
    }

    public function build(Request $request): array
    {
        $columns = $this->resolveColumns($request->query('cols'));
        $grupo = trim((string) $request->query('grupo', ''));
        $q = trim((string) $request->query('q', ''));
        $situacaoFiltro = (string) $request->query('situacao', 'todos');
        $diasAlerta = static::parseDiasAlerta($request->query('dias_alerta'), 30);

        if (! array_key_exists($situacaoFiltro, static::situacaoOptions())) {
            $situacaoFiltro = 'todos';
        }

        $productsTable = (new Product)->getTable();

        $products = Product::query()
            ->select([
                "{$productsTable}.id",
                "{$productsTable}.codigo",
                "{$productsTable}.descricao",
                "{$productsTable}.grupo",
                "{$productsTable}.unidade",
                "{$productsTable}.estoque",
                "{$productsTable}.validade",
                "{$productsTable}.codigo_barras",
            ])
            ->where('ativo', true)
            ->whereNotNull('validade')
            ->when($grupo !== '', fn ($query) => $query->where('grupo', 'like', '%'.$grupo.'%'))
            ->when($q !== '', function ($query) use ($q): void {
                $like = '%'.$q.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('codigo', 'like', $like)
                        ->orWhere('descricao', 'like', $like)
                        ->orWhere('codigo_barras', 'like', $like);
                });
            })
            ->orderByRaw('validade ASC')
            ->limit(5000)
            ->get();

        $rows = [];

        foreach ($products as $product) {
            $dias = $product->validadeDiasRestantes();
            $situacao = static::situacaoFromDias($dias, $diasAlerta);

            if ($situacaoFiltro !== 'todos' && $situacao !== $situacaoFiltro) {
                continue;
            }

            $rows[] = [
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
                'estoque' => static::formatQuantity((float) $product->estoque),
                'validade' => static::formatDate($product->validade),
                'dias' => $dias === null ? '' : (string) $dias,
                'situacao' => static::situacaoLabel($situacao),
            ];
        }

        $summary = [
            'PRODUTOS COM VALIDADE INFORMADA',
            'ALERTA: '.$diasAlerta.' DIA(S)',
        ];

        if ($situacaoFiltro !== 'todos') {
            $summary[] = 'SITUAÇÃO: '.mb_strtoupper(static::situacaoOptions()[$situacaoFiltro], 'UTF-8');
        }

        if ($grupo !== '') {
            $summary[] = 'GRUPO: '.mb_strtoupper($grupo, 'UTF-8');
        }

        return $this->result(
            [
                'grupo' => $grupo,
                'q' => $q,
                'situacao' => $situacaoFiltro,
                'dias_alerta' => (string) $diasAlerta,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            $summary,
            withTotals: false,
        );
    }
}
