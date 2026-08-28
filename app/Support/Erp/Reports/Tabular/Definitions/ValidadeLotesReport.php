<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\ProductLote;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\ResolvesValidadeSituacao;
use Illuminate\Http\Request;

class ValidadeLotesReport extends AbstractTabularReport
{
    use ResolvesValidadeSituacao;

    public function slug(): string
    {
        return 'validade-lotes';
    }

    public function title(): string
    {
        return 'LISTAGEM — VALIDADE POR LOTE';
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
            'lote' => 'LOTE',
            'data_validade' => 'VALIDADE',
            'quantidade' => 'QTD',
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
        return ['quantidade', 'dias'];
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

        $lotes = ProductLote::query()
            ->select(['id', 'product_id', 'lote', 'data_validade', 'quantidade_atual'])
            ->whereNotNull('data_validade')
            ->whereHas('product', function ($query) use ($grupo): void {
                $query->where('ativo', true);

                if ($grupo !== '') {
                    $query->where('grupo', 'like', '%'.$grupo.'%');
                }
            })
            ->when($q !== '', function ($builder) use ($q): void {
                $like = '%'.$q.'%';
                $builder->where(function ($inner) use ($like): void {
                    $inner->where('lote', 'like', $like)
                        ->orWhereHas('product', function ($productQuery) use ($like): void {
                            $productQuery->where('codigo', 'like', $like)
                                ->orWhere('descricao', 'like', $like)
                                ->orWhere('codigo_barras', 'like', $like);
                        });
                });
            })
            ->with(['product:id,codigo,descricao,grupo,codigo_barras,ativo'])
            ->orderBy('data_validade')
            ->orderBy('id')
            ->limit(5000)
            ->get();

        $rows = [];

        foreach ($lotes as $lote) {
            $product = $lote->product;
            if (! $product || ! $product->ativo) {
                continue;
            }

            $dias = $lote->diasRestantes();
            $situacao = static::situacaoFromDias($dias, $diasAlerta);

            if ($situacaoFiltro !== 'todos' && $situacao !== $situacaoFiltro) {
                continue;
            }

            $rows[] = [
                'codigo' => (string) ($product->codigo ?? ''),
                'descricao' => (string) ($product->descricao ?? ''),
                'grupo' => (string) ($product->grupo ?? ''),
                'lote' => (string) ($lote->lote ?? ''),
                'data_validade' => static::formatDate($lote->data_validade),
                'quantidade' => static::formatQuantity((float) $lote->quantidade_atual),
                'dias' => $dias === null ? '' : (string) $dias,
                'situacao' => static::situacaoLabel($situacao),
                '_sort_validade' => $lote->data_validade?->format('Y-m-d') ?? '',
                '_sort_codigo' => (string) ($product->codigo ?? ''),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($a['_sort_validade'] ?? ''), (string) ($b['_sort_validade'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp((string) ($a['_sort_codigo'] ?? ''), (string) ($b['_sort_codigo'] ?? ''));
        });

        $rows = array_map(static function (array $row): array {
            unset($row['_sort_validade'], $row['_sort_codigo']);

            return $row;
        }, $rows);

        $summary = [
            'LOTES COM VALIDADE INFORMADA',
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
