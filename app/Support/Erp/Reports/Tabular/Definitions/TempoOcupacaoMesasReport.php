<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\QueriesMesaPdvVendas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TempoOcupacaoMesasReport extends AbstractTabularReport
{
    use QueriesMesaPdvVendas;

    public function slug(): string
    {
        return 'tempo-ocupacao-mesas';
    }

    public function title(): string
    {
        return 'TEMPO DE OCUPAÇÃO DAS MESAS';
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
            'tempo_medio' => 'TEMPO MÉDIO',
            'tempo_total' => 'TEMPO TOTAL',
            'menor' => 'MENOR',
            'maior' => 'MAIOR',
            'rotatividade' => 'ROTATIVIDADE/DIA',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['atendimentos', 'rotatividade'];
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
        $dias = max(1, $de->diffInDays($ate) + 1);
        $vendas = $this->mesaVendasSql();
        $duracaoExpr = "TIMESTAMPDIFF(SECOND, {$vendas}.aberto_em, COALESCE({$vendas}.fechado_em, {$vendas}.created_at))";

        $agg = $this->whereVendaNaoCancelada($this->mesaVendasQuery($request, $de, $ate))
            ->whereNotNull('pdv_vendas.aberto_em')
            ->groupBy('pdv_vendas.pdv_mesa_numero')
            ->orderBy('pdv_vendas.pdv_mesa_numero')
            ->get([
                'pdv_vendas.pdv_mesa_numero as mesa',
                DB::raw('COUNT(*) as atendimentos'),
                DB::raw("AVG({$duracaoExpr}) as media_seg"),
                DB::raw("SUM({$duracaoExpr}) as total_seg"),
                DB::raw("MIN({$duracaoExpr}) as min_seg"),
                DB::raw("MAX({$duracaoExpr}) as max_seg"),
            ]);

        $rows = $agg->map(function (object $row) use ($rotulos, $dias): array {
            $numero = (int) $row->mesa;
            $atend = (int) $row->atendimentos;

            return [
                'mesa' => str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
                'nome' => $this->rotuloMesaDoMapa($rotulos, $numero),
                'atendimentos' => (string) $atend,
                'tempo_medio' => $this->formatDuracaoSegundos((int) round((float) $row->media_seg)),
                'tempo_total' => $this->formatDuracaoSegundos((int) round((float) $row->total_seg)),
                'menor' => $this->formatDuracaoSegundos((int) $row->min_seg),
                'maior' => $this->formatDuracaoSegundos((int) $row->max_seg),
                'rotatividade' => number_format($atend / $dias, 2, ',', '.'),
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
