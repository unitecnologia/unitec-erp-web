<?php

namespace App\Support\Erp\Reports\Tabular\Concerns;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait BuildsReportChartScope
{
    /**
     * Período do gráfico diário: respeita de/ate do request, limitado a 31 dias (do ate para trás).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function chartPeriodClampForChart(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);

        if ($de->diffInDays($ate) > 30) {
            $de = $ate->copy()->subDays(30)->startOfDay();
        }

        return [$de->copy()->startOfDay(), $ate->copy()->startOfDay()];
    }

    /**
     * Período do gráfico: respeita de/ate do request, limitado a 12 meses (do ate para trás).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function chartPeriodClamp(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);

        $months = (($ate->year - $de->year) * 12) + ($ate->month - $de->month) + 1;

        if ($months > 12) {
            $de = $ate->copy()->subMonthsNoOverflow(11)->startOfMonth();
        }

        return [$de->copy()->startOfDay(), $ate->copy()->startOfDay()];
    }

    /**
     * IDs de empresa para o gráfico: accessibleEmpresaIds(); uma ou todas.
     *
     * @return list<int>
     */
    protected function chartEmpresaIds(Request $request): array
    {
        $accessible = $this->chartAccessibleEmpresaIds();

        if ($accessible === []) {
            $current = ErpContext::currentEmpresaId();

            return $current ? [$current] : [];
        }

        if (count($accessible) === 1) {
            return $accessible;
        }

        $raw = trim((string) $request->query('chart_empresas', 'todas'));

        if ($raw === '' || $raw === 'todas') {
            return $accessible;
        }

        $id = (int) $raw;

        if ($id > 0 && in_array($id, $accessible, true)) {
            return [$id];
        }

        return $accessible;
    }

    /**
     * @return list<int>
     */
    protected function chartAccessibleEmpresaIds(): array
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'accessibleEmpresaIds')) {
            return array_values(array_filter(
                array_map('intval', $user->accessibleEmpresaIds()),
                static fn (int $id): bool => $id > 0,
            ));
        }

        return ErpContext::accessibleEmpresaIds();
    }

    /**
     * @return array{
     *     show_empresa_select: bool,
     *     empresas: list<array{id: int, label: string}>,
     *     default_empresa: string
     * }
     */
    protected function chartEmpresaUiConfig(): array
    {
        $ids = $this->chartAccessibleEmpresaIds();
        $empresas = [];

        if ($ids !== []) {
            $empresas = Empresa::query()
                ->whereIn('id', $ids)
                ->where('ativo', true)
                ->orderBy('codigo')
                ->orderBy('nome')
                ->get(['id', 'fantasia', 'nome', 'razao_social'])
                ->map(fn (Empresa $e): array => [
                    'id' => (int) $e->id,
                    'label' => ReportEmpresaScope::labelEmpresa($e),
                ])
                ->all();
        }

        return [
            'show_empresa_select' => count($empresas) >= 2,
            'empresas' => $empresas,
            'default_empresa' => 'todas',
        ];
    }

    /**
     * Chaves YYYY-MM e rótulos curtos (jan/26) cobrindo de..ate.
     *
     * @return array{keys: list<string>, labels: list<string>}
     */
    protected function chartMonthAxis(CarbonInterface $de, CarbonInterface $ate): array
    {
        $keys = [];
        $labels = [];
        $cursor = $de->copy()->startOfMonth();
        $end = $ate->copy()->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $this->chartMonthShortLabel($cursor);
            $cursor->addMonth();
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    protected function chartMonthShortLabel(CarbonInterface $month): string
    {
        static $nomes = [
            1 => 'jan', 2 => 'fev', 3 => 'mar', 4 => 'abr',
            5 => 'mai', 6 => 'jun', 7 => 'jul', 8 => 'ago',
            9 => 'set', 10 => 'out', 11 => 'nov', 12 => 'dez',
        ];

        return ($nomes[(int) $month->month] ?? $month->format('m')).'/'.$month->format('y');
    }

    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    protected function chartFillMonths(array $monthKeys, array $valuesByYm): array
    {
        $out = [];

        foreach ($monthKeys as $ym) {
            $out[] = round((float) ($valuesByYm[$ym] ?? 0), 2);
        }

        return $out;
    }

    /**
     * Chaves Y-m-d e rótulos curtos (dd/mm) cobrindo de..ate inclusive.
     *
     * @return array{keys: list<string>, labels: list<string>}
     */
    protected function chartDayAxis(CarbonInterface $de, CarbonInterface $ate): array
    {
        $keys = [];
        $labels = [];
        $cursor = $de->copy()->startOfDay();
        $end = $ate->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $keys[] = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('d/m');
            $cursor->addDay();
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    /**
     * @param  list<string>  $dayKeys
     * @param  array<string, float>  $valuesByDate
     * @return list<float>
     */
    protected function chartFillDays(array $dayKeys, array $valuesByDate): array
    {
        $out = [];

        foreach ($dayKeys as $ymd) {
            $out[] = round((float) ($valuesByDate[$ymd] ?? 0), 2);
        }

        return $out;
    }
}
