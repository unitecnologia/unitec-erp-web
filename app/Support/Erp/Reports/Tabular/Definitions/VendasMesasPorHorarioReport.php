<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\QueriesMesaPdvVendas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendasMesasPorHorarioReport extends AbstractTabularReport
{
    use QueriesMesaPdvVendas;

    public function slug(): string
    {
        return 'vendas-mesas-horario';
    }

    public function title(): string
    {
        return 'VENDAS POR HORÁRIO (MESAS)';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'faixa' => 'FAIXA HORÁRIA',
            'atendimentos' => 'ATENDIMENTOS',
            'faturamento' => 'FATURAMENTO',
            'ticket_medio' => 'TICKET MÉDIO',
            'participacao' => '% FATURAMENTO',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['atendimentos', 'faturamento', 'ticket_medio', 'participacao'];
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

        $vendas = $this->mesaVendasSql();
        $horaExpr = "HOUR(COALESCE({$vendas}.fechado_em, {$vendas}.created_at))";

        $agg = $this->whereVendaNaoCancelada($this->mesaVendasQuery($request, $de, $ate))
            ->select([
                DB::raw("{$horaExpr} as hora"),
                DB::raw('COUNT(*) as atendimentos'),
                DB::raw("COALESCE(SUM({$vendas}.total), 0) as faturamento"),
                DB::raw("COALESCE(AVG({$vendas}.total), 0) as ticket_medio"),
            ])
            ->groupBy(DB::raw($horaExpr))
            ->orderBy('hora')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->hora);

        $totalFat = (float) $agg->sum(fn (object $row): float => (float) $row->faturamento);
        $rows = [];

        for ($h = 0; $h < 24; $h++) {
            $row = $agg->get($h);
            $fat = $row ? (float) $row->faturamento : 0.0;
            $qtd = $row ? (int) $row->atendimentos : 0;

            if ($qtd === 0 && $fat <= 0) {
                continue;
            }

            $rows[] = [
                'faixa' => sprintf('%02d:00–%02d:59', $h, $h),
                'atendimentos' => (string) $qtd,
                'faturamento' => static::formatMoney($fat),
                'ticket_medio' => static::formatMoney($row ? (float) $row->ticket_medio : 0.0),
                'participacao' => static::formatPercent($totalFat > 0 ? ($fat / $totalFat) * 100 : 0.0),
            ];
        }

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
