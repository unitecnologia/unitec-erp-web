<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\QueriesMesaPdvVendas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CancelamentosDescontosMesasReport extends AbstractTabularReport
{
    use QueriesMesaPdvVendas;

    public function slug(): string
    {
        return 'cancelamentos-descontos-mesas';
    }

    public function title(): string
    {
        return 'CANCELAMENTOS E DESCONTOS (MESAS)';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'data' => 'DATA',
            'venda' => 'VENDA',
            'mesa' => 'MESA',
            'nome' => 'NOME',
            'tipo' => 'TIPO',
            'desconto' => 'DESCONTO',
            'total' => 'TOTAL',
            'operador' => 'OPERADOR',
            'motivo' => 'MOTIVO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['desconto', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'tipo',
                'label' => 'Tipo',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'desconto' => 'Desconto',
                    'estorno' => 'Estorno/Cancelamento',
                ],
            ],
        ]));
    }

    public function build(Request $request): array
    {
        $this->assertEmpresaRestaurante();
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));
        $tipo = (string) $request->query('tipo', 'todos');

        if (! $this->mesaNumeroDisponivel()) {
            return $this->result(
                $this->withEmpresaFilterValue([
                    'de' => $de->toDateString(),
                    'ate' => $ate->toDateString(),
                    'tipo' => $tipo,
                    'cols' => $columns,
                ], $request),
                $columns,
                [],
                $this->withEmpresaSummary([
                    'PERÍODO: '.$this->periodLabel($de, $ate),
                    'Atualize o sistema para habilitar o vínculo mesa × venda.',
                ], $request),
            );
        }

        $rotulos = $this->mapaRotulosMesas($request);
        $vendas = $this->mesaVendasSql();

        $query = $this->mesaVendasQuery($request, $de, $ate)
            ->where(function ($q): void {
                $q->where('pdv_vendas.desconto', '>', 0)
                    ->orWhere('pdv_vendas.situacao', 'C');
            });

        if ($tipo === 'desconto') {
            $query->where('pdv_vendas.desconto', '>', 0);
            $this->whereVendaNaoCancelada($query);
        } elseif ($tipo === 'estorno') {
            $query->where('pdv_vendas.situacao', 'C');
        }

        $rows = $query
            ->orderByDesc(DB::raw("COALESCE({$vendas}.fechado_em, {$vendas}.created_at)"))
            ->limit(5000)
            ->get([
                'pdv_vendas.numero',
                'pdv_vendas.pdv_mesa_numero',
                'pdv_vendas.fechado_em',
                'pdv_vendas.created_at',
                'pdv_vendas.desconto',
                'pdv_vendas.total',
                'pdv_vendas.vendedor_nome',
                'pdv_vendas.situacao',
                'pdv_vendas.motivo_estorno',
            ])
            ->map(function (object $row) use ($rotulos): array {
                $numero = (int) $row->pdv_mesa_numero;
                $cancelada = $this->vendaEstaCancelada($row->situacao !== null ? (string) $row->situacao : null);
                $data = $row->fechado_em
                    ? Carbon::parse($row->fechado_em)
                    : ($row->created_at ? Carbon::parse($row->created_at) : null);

                return [
                    'data' => $data?->format('d/m/Y H:i') ?? '',
                    'venda' => (string) ($row->numero ?? ''),
                    'mesa' => str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
                    'nome' => $this->rotuloMesaDoMapa($rotulos, $numero),
                    'tipo' => $cancelada ? 'Estorno' : 'Desconto',
                    'desconto' => static::formatMoney((float) $row->desconto),
                    'total' => static::formatMoney((float) $row->total),
                    'operador' => (string) ($row->vendedor_nome ?? ''),
                    'motivo' => (string) ($row->motivo_estorno ?? ''),
                ];
            })
            ->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'tipo' => $tipo,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary(['PERÍODO: '.$this->periodLabel($de, $ate)], $request),
        );
    }
}
