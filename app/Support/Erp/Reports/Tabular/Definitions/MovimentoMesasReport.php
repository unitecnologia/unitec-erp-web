<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\QueriesMesaPdvVendas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimentoMesasReport extends AbstractTabularReport
{
    use QueriesMesaPdvVendas;

    public function slug(): string
    {
        return 'movimento-mesas';
    }

    public function title(): string
    {
        return 'MOVIMENTO DE MESAS';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'mesa' => 'MESA',
            'nome' => 'NOME',
            'atendimentos' => 'ATENDIMENTOS',
            'faturamento' => 'FATURAMENTO',
            'ticket_medio' => 'TICKET MÉDIO',
            'descontos' => 'DESCONTOS',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['atendimentos', 'faturamento', 'ticket_medio', 'descontos'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter($this->periodFilterFields()));
    }

    public function build(Request $request): array
    {
        $this->assertEmpresaRestaurante();
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->resolveColumns($request->query('cols'));

        if (! $this->mesaNumeroDisponivel()) {
            return $this->result(
                $this->withEmpresaFilterValue([
                    'de' => $de->toDateString(),
                    'ate' => $ate->toDateString(),
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

        $agg = $this->whereVendaNaoCancelada($this->mesaVendasQuery($request, $de, $ate))
            ->groupBy('pdv_vendas.pdv_mesa_numero')
            ->orderBy('pdv_vendas.pdv_mesa_numero')
            ->get([
                'pdv_vendas.pdv_mesa_numero as mesa',
                DB::raw('COUNT(*) as atendimentos'),
                DB::raw("COALESCE(SUM({$vendas}.total), 0) as faturamento"),
                DB::raw("COALESCE(AVG({$vendas}.total), 0) as ticket_medio"),
                DB::raw("COALESCE(SUM({$vendas}.desconto), 0) as descontos"),
            ]);

        $rows = $agg->map(function (object $row) use ($rotulos): array {
            $numero = (int) $row->mesa;

            return [
                'mesa' => str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
                'nome' => $this->rotuloMesaDoMapa($rotulos, $numero),
                'atendimentos' => (string) (int) $row->atendimentos,
                'faturamento' => static::formatMoney((float) $row->faturamento),
                'ticket_medio' => static::formatMoney((float) $row->ticket_medio),
                'descontos' => static::formatMoney((float) $row->descontos),
            ];
        })->all();

        return $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary(['PERÍODO: '.$this->periodLabel($de, $ate)], $request),
        );
    }
}
