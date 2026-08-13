<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OutrasSaidaMovimento;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Http\Request;

class OutrasSaidasMovimentoReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'outras-saidas-movimento';
    }

    public function title(): string
    {
        return 'OUTRAS SAÍDAS DE ESTOQUE';
    }

    public function permission(): string
    {
        return 'ajuste_estoque.access';
    }

    public function columns(): array
    {
        return [
            'movimento' => 'MOVIMENTO',
            'data' => 'DATA',
            'hora' => 'HORA',
            'tipo' => 'MOVIMENTAÇÃO',
            'fornecedor' => 'FORNECEDOR',
            'estoque' => 'ESTOQUE',
            'codigo' => 'CÓDIGO',
            'descricao' => 'DESCRIÇÃO',
            'quantidade' => 'QTDE',
            'preco' => 'PREÇO COMPRA',
            'total' => 'TOTAL',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['quantidade', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField([
            ...$this->periodFilterFields(),
            [
                'key' => 'tipo',
                'label' => 'Movimentação',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todas',
                    'uso_consumo' => 'Uso / consumo',
                    'perda' => 'Perda',
                    'outras' => 'Outras',
                ],
            ],
            [
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => [
                    'todas' => 'Todas',
                    'aberta' => 'Aberto',
                    'finalizada' => 'Fechado',
                    'cancelada' => 'Cancelado',
                ],
            ],
        ]);
    }

    public function build(Request $request): array
    {
        $id = (int) $request->query('movimento', 0);
        $columns = $this->resolveColumns($request->query('cols'));
        $empresaId = ErpContext::currentEmpresaId();

        if ($id <= 0) {
            return $this->buildListagemMensal($request, $columns, $empresaId);
        }

        $movimento = OutrasSaidaMovimento::query()
            ->with(['itens', 'estoque'])
            ->whereKey($id)
            ->when($empresaId, fn ($query, $empresa) => $query->where('empresa_id', $empresa))
            ->first();

        if (! $movimento) {
            return $this->result(
                ['movimento' => $id, 'cols' => $columns],
                $columns,
                [],
                ['MOVIMENTO NÃO ENCONTRADO'],
            );
        }

        $tipo = match ($movimento->tipo_movimento) {
            'uso_consumo' => 'USO / CONSUMO',
            'perda' => 'PERDA',
            'outras' => 'OUTRAS',
            default => '—',
        };
        $estoque = $movimento->estoque
            ? trim(($movimento->estoque->codigo ? $movimento->estoque->codigo.' — ' : '').$movimento->estoque->nome)
            : '—';

        $rows = $movimento->itens
            ->sortBy('item')
            ->values()
            ->map(fn ($item): array => [
                'movimento' => (string) $movimento->numero,
                'data' => static::formatDate($movimento->data),
                'hora' => $movimento->hora ? substr((string) $movimento->hora, 0, 5) : '—',
                'tipo' => $tipo,
                'fornecedor' => (string) ($movimento->fornecedor_nome ?: '—'),
                'estoque' => $estoque,
                'codigo' => (string) ($item->produto_codigo ?: '—'),
                'descricao' => (string) ($item->produto_descricao ?: '—'),
                'quantidade' => static::formatQuantity((float) $item->qtd),
                'preco' => static::formatMoney((float) $item->preco),
                'total' => static::formatMoney((float) $item->total),
            ])
            ->all();

        return $this->result(
            ['movimento' => $movimento->id, 'cols' => $columns],
            $columns,
            $rows,
            [
                'MOVIMENTO: '.$movimento->numero,
                'SITUAÇÃO: '.mb_strtoupper((string) $movimento->situacao, 'UTF-8'),
                'MOVIMENTAÇÃO: '.$tipo,
            ],
        );
    }

    /**
     * @param list<string> $columns
     */
    private function buildListagemMensal(Request $request, array $columns, ?int $empresaId): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $tipoFiltro = (string) $request->query('tipo', 'todos');
        $situacaoFiltro = (string) $request->query('situacao', 'todas');

        $movimentos = OutrasSaidaMovimento::query()
            ->with(['itens', 'estoque'])
            ->when($empresaId, fn ($query, $empresa) => $query->where('empresa_id', $empresa))
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->when($tipoFiltro !== 'todos', fn ($query) => $query->where('tipo_movimento', $tipoFiltro))
            ->when($situacaoFiltro !== 'todas', fn ($query) => $query->where('situacao', $situacaoFiltro))
            ->orderBy('data')
            ->orderBy('hora')
            ->orderBy('numero')
            ->limit(5000)
            ->get();

        $rows = [];
        foreach ($movimentos as $movimento) {
            $tipo = match ($movimento->tipo_movimento) {
                'uso_consumo' => 'USO / CONSUMO',
                'perda' => 'PERDA',
                'outras' => 'OUTRAS',
                default => '—',
            };
            $estoque = $movimento->estoque
                ? trim(($movimento->estoque->codigo ? $movimento->estoque->codigo.' — ' : '').$movimento->estoque->nome)
                : '—';

            foreach ($movimento->itens as $item) {
                $rows[] = [
                    'movimento' => (string) $movimento->numero,
                    'data' => static::formatDate($movimento->data),
                    'hora' => $movimento->hora ? substr((string) $movimento->hora, 0, 5) : '—',
                    'tipo' => $tipo,
                    'fornecedor' => (string) ($movimento->fornecedor_nome ?: '—'),
                    'estoque' => $estoque,
                    'codigo' => (string) ($item->produto_codigo ?: '—'),
                    'descricao' => (string) ($item->produto_descricao ?: '—'),
                    'quantidade' => static::formatQuantity((float) $item->qtd),
                    'preco' => static::formatMoney((float) $item->preco),
                    'total' => static::formatMoney((float) $item->total),
                ];
            }
        }

        return $this->result(
            [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'tipo' => $tipoFiltro,
                'situacao' => $situacaoFiltro,
                'cols' => $columns,
            ],
            $columns,
            $rows,
            [
                'PERÍODO: '.$this->periodLabel($de, $ate),
                'MOVIMENTAÇÃO: '.mb_strtoupper($tipoFiltro === 'todos' ? 'TODAS' : str_replace('_', ' ', $tipoFiltro), 'UTF-8'),
                'SITUAÇÃO: '.mb_strtoupper($situacaoFiltro === 'todas' ? 'TODAS' : $situacaoFiltro, 'UTF-8'),
            ],
        );
    }
}
