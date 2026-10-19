<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\QueriesMesaPdvVendas;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoricoFechamentosMesasReport extends AbstractTabularReport
{
    use QueriesMesaPdvVendas;

    public function slug(): string
    {
        return 'historico-fechamentos-mesas';
    }

    public function title(): string
    {
        return 'HISTÓRICO DE FECHAMENTOS DE MESAS';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function columns(): array
    {
        return [
            'venda' => 'VENDA',
            'mesa' => 'MESA',
            'nome' => 'NOME',
            'abertura' => 'ABERTURA',
            'encerramento' => 'ENCERRAMENTO',
            'duracao' => 'DURAÇÃO',
            'itens' => 'ITENS',
            'desconto' => 'DESCONTO',
            'total' => 'TOTAL',
            'operador' => 'OPERADOR',
            'situacao' => 'SITUAÇÃO',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'venda', 'mesa', 'nome', 'abertura', 'encerramento', 'duracao', 'itens', 'total', 'operador',
        ];
    }

    public function numericColumns(): array
    {
        return ['itens', 'desconto', 'total'];
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
        $itensAlias = static::sqlTable('itens_agg');

        $itensSub = DB::table('pdv_venda_itens')
            ->groupBy('pdv_venda_itens.pdv_venda_id')
            ->select([
                'pdv_venda_itens.pdv_venda_id',
                DB::raw('COUNT(*) as qtd_itens'),
            ]);

        $rows = $this->mesaVendasQuery($request, $de, $ate)
            ->leftJoinSub($itensSub, 'itens_agg', 'itens_agg.pdv_venda_id', '=', 'pdv_vendas.id')
            ->orderByDesc(DB::raw("COALESCE({$vendas}.fechado_em, {$vendas}.created_at)"))
            ->limit(5000)
            ->get([
                'pdv_vendas.numero',
                'pdv_vendas.pdv_mesa_numero',
                'pdv_vendas.aberto_em',
                'pdv_vendas.fechado_em',
                'pdv_vendas.created_at',
                'pdv_vendas.desconto',
                'pdv_vendas.total',
                'pdv_vendas.vendedor_nome',
                'pdv_vendas.situacao',
                DB::raw("COALESCE({$itensAlias}.qtd_itens, 0) as qtd_itens"),
            ])
            ->map(function (object $row) use ($rotulos): array {
                $numero = (int) $row->pdv_mesa_numero;
                $abertura = $row->aberto_em ? Carbon::parse($row->aberto_em) : null;
                $fechamento = $row->fechado_em
                    ? Carbon::parse($row->fechado_em)
                    : ($row->created_at ? Carbon::parse($row->created_at) : null);
                $duracao = ($abertura && $fechamento)
                    ? $this->formatDuracaoSegundos((int) abs($abertura->diffInSeconds($fechamento)))
                    : '—';

                return [
                    'venda' => (string) ($row->numero ?? ''),
                    'mesa' => str_pad((string) $numero, 2, '0', STR_PAD_LEFT),
                    'nome' => $this->rotuloMesaDoMapa($rotulos, $numero),
                    'abertura' => $abertura?->format('d/m/Y H:i') ?? '—',
                    'encerramento' => $fechamento?->format('d/m/Y H:i') ?? '—',
                    'duracao' => $duracao,
                    'itens' => (string) (int) $row->qtd_itens,
                    'desconto' => static::formatMoney((float) $row->desconto),
                    'total' => static::formatMoney((float) $row->total),
                    'operador' => (string) ($row->vendedor_nome ?? ''),
                    'situacao' => $this->rotuloSituacaoVenda($row->situacao !== null ? (string) $row->situacao : null),
                ];
            })
            ->all();

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
