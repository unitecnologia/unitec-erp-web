<?php

namespace App\Support\Erp\Reports;

use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faturamento mensal (somente leitura): vendas fechadas + PDV comercial sem espelho.
 * Não soma NF-e/NFC-e, pedidos Force/DAV nem lançamentos financeiros — evita duplicar.
 */
final class FaturamentoMensalService
{
    /** @var list<string> */
    private const MESES_CURTOS = [
        1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
        5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
    ];

    /**
     * @return array{
     *   empresa_id: int,
     *   ano: int,
     *   mes: int|null,
     *   periodo_de: string,
     *   periodo_ate: string,
     *   bars: list<array{ym: string, mes: int, label: string, total: float, total_fmt: string, pct: float}>,
     *   resumo: array{
     *     faturamento_total: float,
     *     faturamento_total_fmt: string,
     *     media_mensal: float,
     *     media_mensal_fmt: string,
     *     melhor_mes: string,
     *     melhor_mes_total: float,
     *     melhor_mes_total_fmt: string,
     *     qtd_meses: int,
     *     qtd_vendas: int
     *   }
     * }
     */
    public function calcular(int $empresaId, int $ano, ?int $mes = null): array
    {
        $ano = max(2000, min(2100, $ano));
        $mes = $mes !== null && $mes >= 1 && $mes <= 12 ? $mes : null;

        if ($empresaId <= 0) {
            return $this->vazio($empresaId, $ano, $mes);
        }

        if ($mes !== null) {
            $de = Carbon::create($ano, $mes, 1)->startOfDay();
            $ate = $de->copy()->endOfMonth()->startOfDay();
            $monthKeys = [sprintf('%04d-%02d', $ano, $mes)];
        } else {
            $de = Carbon::create($ano, 1, 1)->startOfDay();
            $ate = Carbon::create($ano, 12, 31)->startOfDay();
            $monthKeys = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthKeys[] = sprintf('%04d-%02d', $ano, $m);
            }
        }

        $totais = array_fill_keys($monthKeys, 0.0);
        $qtd = 0;

        [$mapVendas, $qtdVendas] = $this->agregarVendas($empresaId, $de->toDateString(), $ate->toDateString());
        foreach ($mapVendas as $ym => $valor) {
            if (array_key_exists($ym, $totais)) {
                $totais[$ym] += $valor;
            }
        }
        $qtd += $qtdVendas;

        [$mapPdv, $qtdPdv] = $this->agregarPdvSemEspelho($empresaId, $de->toDateString(), $ate->toDateString());
        foreach ($mapPdv as $ym => $valor) {
            if (array_key_exists($ym, $totais)) {
                $totais[$ym] += $valor;
            }
        }
        $qtd += $qtdPdv;

        $max = 0.00001;
        foreach ($totais as $valor) {
            $max = max($max, (float) $valor);
        }

        $bars = [];
        $melhorYm = $monthKeys[0] ?? '';
        $melhorTotal = -1.0;
        $soma = 0.0;
        $mesesComValor = 0;

        foreach ($monthKeys as $ym) {
            $valor = round((float) ($totais[$ym] ?? 0), 2);
            $mesNum = (int) substr($ym, 5, 2);
            $soma += $valor;
            if ($valor > 0) {
                $mesesComValor++;
            }
            if ($valor > $melhorTotal) {
                $melhorTotal = $valor;
                $melhorYm = $ym;
            }

            $bars[] = [
                'ym' => $ym,
                'mes' => $mesNum,
                'label' => (self::MESES_CURTOS[$mesNum] ?? $ym).'/'.substr($ym, 2, 2),
                'total' => $valor,
                'total_fmt' => $this->formatRs($valor),
                'pct' => round(($valor / $max) * 100, 2),
            ];
        }

        $divisor = $mes !== null ? 1 : max(1, $mesesComValor > 0 ? $mesesComValor : count($monthKeys));
        $media = round($soma / $divisor, 2);
        $melhorMesNum = $melhorYm !== '' ? (int) substr($melhorYm, 5, 2) : 0;
        $melhorLabel = $melhorMesNum > 0
            ? ((self::MESES_CURTOS[$melhorMesNum] ?? '').'/'.substr((string) $ano, 2, 2))
            : '—';

        return [
            'empresa_id' => $empresaId,
            'ano' => $ano,
            'mes' => $mes,
            'periodo_de' => $de->toDateString(),
            'periodo_ate' => $ate->toDateString(),
            'bars' => $bars,
            'resumo' => [
                'faturamento_total' => round($soma, 2),
                'faturamento_total_fmt' => $this->formatRs($soma),
                'media_mensal' => $media,
                'media_mensal_fmt' => $this->formatRs($media),
                'melhor_mes' => $melhorLabel,
                'melhor_mes_total' => round(max(0.0, $melhorTotal), 2),
                'melhor_mes_total_fmt' => $this->formatRs(max(0.0, $melhorTotal)),
                'qtd_meses' => count($monthKeys),
                'qtd_vendas' => $qtd,
            ],
        ];
    }

    /**
     * Anos com venda fechada (ou ano corrente) para o select.
     *
     * @return list<int>
     */
    public function anosDisponiveis(int $empresaId): array
    {
        $atual = (int) Carbon::now()->year;
        $anos = [$atual];

        if ($empresaId <= 0 || ! Schema::hasTable('vendas')) {
            return $anos;
        }

        $dataCol = $this->sqlTable('vendas').'.`data`';
        $expr = $this->yearExpr($dataCol);
        $rows = Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('status', Venda::STATUS_FECHADO)
            ->whereNotNull('data')
            ->toBase()
            ->selectRaw("{$expr} as ano")
            ->groupBy(DB::raw($expr))
            ->orderBy('ano')
            ->pluck('ano');

        foreach ($rows as $ano) {
            $ano = (int) $ano;
            if ($ano >= 2000 && $ano <= 2100) {
                $anos[] = $ano;
            }
        }

        $anos = array_values(array_unique($anos));
        rsort($anos);

        return $anos;
    }

    /**
     * @return array{0: array<string, float>, 1: int}
     */
    private function agregarVendas(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('vendas')) {
            return [[], 0];
        }

        $vendasSql = $this->sqlTable('vendas');
        $ymExpr = $this->yearMonthExpr("{$vendasSql}.`data`");

        $rows = Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('status', Venda::STATUS_FECHADO)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate)
            ->toBase()
            ->selectRaw("{$ymExpr} as ym")
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw("COALESCE(SUM({$vendasSql}.`total`), 0) as total")
            ->groupBy(DB::raw($ymExpr))
            ->orderBy('ym')
            ->get();

        $map = [];
        $qtd = 0;
        foreach ($rows as $row) {
            $ym = $this->normalizeYm((string) ($row->ym ?? ''));
            if ($ym === '') {
                continue;
            }
            $map[$ym] = round((float) ($row->total ?? 0), 2);
            $qtd += (int) ($row->qtd ?? 0);
        }

        return [$map, $qtd];
    }

    /**
     * PDV comercial fechado sem espelho em vendas (legado/falha de mirror).
     *
     * @return array{0: array<string, float>, 1: int}
     */
    private function agregarPdvSemEspelho(int $empresaId, string $de, string $ate): array
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasTable('pdv_caixa_sessoes')) {
            return [[], 0];
        }

        $pv = 'pdv_vendas';
        $cs = 'pdv_caixa_sessoes';
        $pvSql = $this->sqlTable($pv);
        // selectRaw/groupBy não aplicam DB_PREFIX — usar nome físico (wrapTable).
        $competencia = "COALESCE({$pvSql}.`fechado_em`, {$pvSql}.`created_at`)";
        $ymExpr = $this->yearMonthExpr($competencia);

        $query = DB::table($pv)
            ->join($cs, "{$cs}.id", '=', "{$pv}.pdv_caixa_sessao_id")
            ->where("{$cs}.empresa_id", $empresaId)
            ->where("{$pv}.situacao", 'F')
            ->whereNull("{$pv}.venda_id")
            ->whereNotNull("{$pv}.fechado_em")
            ->whereDate("{$pv}.fechado_em", '>=', $de)
            ->whereDate("{$pv}.fechado_em", '<=', $ate);

        if (Schema::hasColumn('pdv_vendas', 'origem')) {
            $query->where(function ($q) use ($pv): void {
                $q->whereNull("{$pv}.origem")
                    ->orWhereNotIn("{$pv}.origem", [
                        PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
                        PdvVenda::ORIGEM_OS_NFCE_PECAS,
                    ]);
            });
        }

        $rows = $query
            ->selectRaw("{$ymExpr} as ym")
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw("COALESCE(SUM({$pvSql}.`total`), 0) as total")
            ->groupBy(DB::raw($ymExpr))
            ->orderBy('ym')
            ->get();

        $map = [];
        $qtd = 0;
        foreach ($rows as $row) {
            $ym = $this->normalizeYm((string) ($row->ym ?? ''));
            if ($ym === '') {
                continue;
            }
            $map[$ym] = round((float) ($row->total ?? 0), 2);
            $qtd += (int) ($row->qtd ?? 0);
        }

        return [$map, $qtd];
    }

    private function yearMonthExpr(string $columnSql): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "strftime('%Y-%m', {$columnSql})";
        }

        return "DATE_FORMAT({$columnSql}, '%Y-%m')";
    }

    private function yearExpr(string $columnSql): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "CAST(strftime('%Y', {$columnSql}) AS INTEGER)";
        }

        return "YEAR({$columnSql})";
    }

    private function sqlTable(string $table): string
    {
        return DB::connection()->getQueryGrammar()->wrapTable($table);
    }

    private function normalizeYm(string $ym): string
    {
        $ym = trim($ym);
        if (preg_match('/^\d{4}-\d{2}$/', $ym) === 1) {
            return $ym;
        }

        return '';
    }

    private function formatRs(float $value): string
    {
        return 'R$ '.ErpMoney::formatBr($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function vazio(int $empresaId, int $ano, ?int $mes): array
    {
        $bars = [];
        $keys = $mes !== null
            ? [sprintf('%04d-%02d', $ano, $mes)]
            : array_map(static fn (int $m): string => sprintf('%04d-%02d', $ano, $m), range(1, 12));

        foreach ($keys as $ym) {
            $mesNum = (int) substr($ym, 5, 2);
            $bars[] = [
                'ym' => $ym,
                'mes' => $mesNum,
                'label' => (self::MESES_CURTOS[$mesNum] ?? $ym).'/'.substr((string) $ano, 2, 2),
                'total' => 0.0,
                'total_fmt' => $this->formatRs(0),
                'pct' => 0.0,
            ];
        }

        return [
            'empresa_id' => $empresaId,
            'ano' => $ano,
            'mes' => $mes,
            'periodo_de' => '',
            'periodo_ate' => '',
            'bars' => $bars,
            'resumo' => [
                'faturamento_total' => 0.0,
                'faturamento_total_fmt' => $this->formatRs(0),
                'media_mensal' => 0.0,
                'media_mensal_fmt' => $this->formatRs(0),
                'melhor_mes' => '—',
                'melhor_mes_total' => 0.0,
                'melhor_mes_total_fmt' => $this->formatRs(0),
                'qtd_meses' => count($keys),
                'qtd_vendas' => 0,
            ],
        ];
    }
}
