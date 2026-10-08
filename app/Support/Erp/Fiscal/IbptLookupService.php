<?php

namespace App\Support\Erp\Fiscal;

use App\Models\FiscalIbptItem;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Consulta a tabela local IPBTAX (fiscal_ibpt_itens) e calcula tributos aproximados (Lei 12.741/2012).
 */
final class IbptLookupService
{
    /**
     * Origens SEFAZ consideradas importadas (alíquota federal "importado").
     *
     * @var list<int>
     */
    private const ORIGENS_IMPORTADAS = [1, 2, 6, 7];

    public function findByNcm(string $ncm, ?string $exTipi = null): ?FiscalIbptItem
    {
        $ncm = preg_replace('/\D/', '', $ncm) ?? '';

        if ($ncm === '' || strlen($ncm) < 4) {
            return null;
        }

        $ncm = substr(str_pad($ncm, 8, '0', STR_PAD_LEFT), 0, 8);
        $today = Carbon::today()->toDateString();

        $query = FiscalIbptItem::query()
            ->where(function ($q) use ($ncm): void {
                $q->where('ncm', $ncm)
                    ->orWhere('ncm', ltrim($ncm, '0'))
                    ->orWhereRaw("LPAD(REPLACE(ncm, ' ', ''), 8, '0') = ?", [$ncm]);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('vigencia_inicio')
                    ->orWhereDate('vigencia_inicio', '<=', $today);
            })
            ->where(function ($q) use ($today): void {
                $q->whereNull('vigencia_fim')
                    ->orWhereDate('vigencia_fim', '>=', $today);
            })
            ->orderByDesc('vigencia_inicio')
            ->orderByDesc('id');

        if ($exTipi !== null && $exTipi !== '') {
            $ex = preg_replace('/\D/', '', $exTipi) ?? '0';
            $query->where(function ($q) use ($ex): void {
                $q->where('ex_tipi', $ex)
                    ->orWhere('ex_tipi', (string) (int) $ex)
                    ->orWhereNull('ex_tipi');
            });
        }

        $item = $query->first();

        if ($item) {
            return $item;
        }

        // Fallback sem filtro de vigência (tabela antiga / vigência nula parcial).
        return FiscalIbptItem::query()
            ->where(function ($q) use ($ncm): void {
                $q->where('ncm', $ncm)
                    ->orWhereRaw("LPAD(REPLACE(ncm, ' ', ''), 8, '0') = ?", [$ncm]);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * NBS (9 dígitos). A tabela IBPT só traz parte dos desdobros: sem o código exato, usa o pai (1.2001.31.10 → 1.2001.31.00).
     */
    public function findByNbs(string $nbs): ?FiscalIbptItem
    {
        $nbs = preg_replace('/\D/', '', $nbs) ?? '';

        if (strlen($nbs) !== 9) {
            return null;
        }

        $today = Carbon::today()->toDateString();

        foreach (array_unique([$nbs, substr($nbs, 0, 7).'00', substr($nbs, 0, 5).'0000']) as $codigo) {
            $item = FiscalIbptItem::query()
                ->where('ncm', $codigo)
                ->orderByRaw('CASE WHEN (vigencia_inicio IS NULL OR vigencia_inicio <= ?) AND (vigencia_fim IS NULL OR vigencia_fim >= ?) THEN 0 ELSE 1 END', [$today, $today])
                ->orderByDesc('vigencia_inicio')
                ->orderByDesc('id')
                ->first();

            if ($item) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array{
     *     encontrado: bool,
     *     ncm: string,
     *     aliq_federal: float,
     *     aliq_estadual: float,
     *     aliq_municipal: float,
     *     trib_fed: float,
     *     trib_est: float,
     *     trib_mun: float,
     *     trib_imp: float,
     *     v_tot_trib: float,
     *     fonte: string,
     *     chave: string,
     *     versao: string
     * }
     */
    public function calcularParaBase(string $ncm, float $baseCalculo, int $origem = 0, ?string $exTipi = null): array
    {
        $base = max(0, round($baseCalculo, 2));
        $empty = [
            'encontrado' => false,
            'ncm' => preg_replace('/\D/', '', $ncm) ?? '',
            'aliq_federal' => 0.0,
            'aliq_estadual' => 0.0,
            'aliq_municipal' => 0.0,
            'trib_fed' => 0.0,
            'trib_est' => 0.0,
            'trib_mun' => 0.0,
            'trib_imp' => 0.0,
            'v_tot_trib' => 0.0,
            'fonte' => '',
            'chave' => '',
            'versao' => '',
        ];

        $codigo = preg_replace('/\D/', '', $ncm) ?? '';
        $item = strlen($codigo) === 9 ? $this->findByNbs($codigo) : $this->findByNcm($ncm, $exTipi);

        if (! $item) {
            return $empty;
        }

        $importado = in_array($origem, self::ORIGENS_IMPORTADAS, true);
        $aliqFed = (float) ($importado ? $item->aliq_importado : $item->aliq_nacional);
        $aliqEst = (float) $item->aliq_estadual;
        $aliqMun = (float) $item->aliq_municipal;

        $tribFed = round($base * $aliqFed / 100, 2);
        $tribEst = round($base * $aliqEst / 100, 2);
        $tribMun = round($base * $aliqMun / 100, 2);
        $tribImp = $importado ? $tribFed : 0.0;

        return [
            'encontrado' => true,
            'ncm' => (string) $item->ncm,
            'aliq_federal' => $aliqFed,
            'aliq_estadual' => $aliqEst,
            'aliq_municipal' => $aliqMun,
            'trib_fed' => $tribFed,
            'trib_est' => $tribEst,
            'trib_mun' => $tribMun,
            'trib_imp' => $tribImp,
            'v_tot_trib' => round($tribFed + $tribEst + $tribMun, 2),
            'fonte' => (string) ($item->fonte ?? 'IBPT'),
            'chave' => (string) ($item->chave ?? ''),
            'versao' => (string) ($item->versao ?? ''),
        ];
    }

    /**
     * @return array{
     *     encontrado: bool,
     *     ncm: string,
     *     aliq_federal: float,
     *     aliq_estadual: float,
     *     aliq_municipal: float,
     *     trib_fed: float,
     *     trib_est: float,
     *     trib_mun: float,
     *     trib_imp: float,
     *     v_tot_trib: float,
     *     fonte: string,
     *     chave: string,
     *     versao: string
     * }
     */
    public function calcularParaProduto(?Product $product, float $baseCalculo): array
    {
        if (! $product) {
            return $this->calcularParaBase('', $baseCalculo);
        }

        return $this->calcularParaBase(
            (string) ($product->ncm ?? ''),
            $baseCalculo,
            (int) ($product->origem ?? 0),
        );
    }

    /**
     * Texto padrão Lei 12.741 para infCpl / cupom / DANFE.
     *
     * @param  array{trib_fed?: float, trib_est?: float, trib_mun?: float, v_tot_trib?: float, fonte?: string, chave?: string, versao?: string}  $totais
     */
    public function formatarTextoLei12741(array $totais): string
    {
        $fed = (float) ($totais['trib_fed'] ?? 0);
        $est = (float) ($totais['trib_est'] ?? 0);
        $mun = (float) ($totais['trib_mun'] ?? 0);
        $tot = (float) ($totais['v_tot_trib'] ?? ($fed + $est + $mun));

        if ($tot <= 0) {
            return '';
        }

        $fonte = trim((string) ($totais['fonte'] ?? 'IBPT'));
        if ($fonte === '') {
            $fonte = 'IBPT';
        }

        return sprintf(
            'Trib. aprox. Federal: R$ %s | Estadual: R$ %s | Municipal: R$ %s | Total: R$ %s. Fonte: %s. Lei 12.741/2012.',
            number_format($fed, 2, ',', '.'),
            number_format($est, 2, ',', '.'),
            number_format($mun, 2, ',', '.'),
            number_format($tot, 2, ',', '.'),
            $fonte,
        );
    }

    /**
     * Linhas da bobina (qualquer UF) com os tributos aproximados da Lei 12.741/2012.
     * A alíquota vem da tabela IBPT; o texto cabe na largura da impressora POS.
     *
     * @param  array{trib_fed?: float, trib_est?: float, trib_mun?: float, v_tot_trib?: float, fonte?: string}  $totais
     * @return list<string>
     */
    public function linhasBobina(array $totais, int $width = 48): array
    {
        $width = max(32, $width);
        $fed = (float) ($totais['trib_fed'] ?? 0);
        $est = (float) ($totais['trib_est'] ?? 0);
        $mun = (float) ($totais['trib_mun'] ?? 0);
        $tot = (float) ($totais['v_tot_trib'] ?? ($fed + $est + $mun));
        $fonte = trim((string) ($totais['fonte'] ?? 'IBPT'));
        if ($fonte === '') {
            $fonte = 'IBPT';
        }

        if ($tot <= 0) {
            return $this->quebrarLinhaBobina(
                'Tributos aprox. conforme Lei 12.741/2012. Fonte: '.$fonte.'.',
                $width,
            );
        }

        $money = static fn (float $valor): string => number_format($valor, 2, ',', '.');

        return $this->quebrarLinhaBobina(sprintf(
            'Trib. aprox. Fed. R$ %s Est. R$ %s Mun. R$ %s Total R$ %s. Fonte: %s. Lei 12.741/2012.',
            $money($fed),
            $money($est),
            $money($mun),
            $money($tot),
            $fonte,
        ), $width);
    }

    /**
     * @param  iterable<int, object>  $itens  Itens com product e total
     * @return list<string>
     */
    public function linhasBobinaDosItens(iterable $itens, int $width = 48): array
    {
        $rows = [];

        foreach ($itens as $item) {
            $ibpt = $this->calcularParaProduto($item->product ?? null, (float) ($item->total ?? 0));
            $rows[] = [
                'trib_fed' => $ibpt['trib_fed'],
                'trib_est' => $ibpt['trib_est'],
                'trib_mun' => $ibpt['trib_mun'],
                'trib_imp' => $ibpt['trib_imp'],
                'ibpt_fonte' => $ibpt['fonte'],
                'ibpt_chave' => $ibpt['chave'],
                'ibpt_versao' => $ibpt['versao'],
            ];
        }

        return $this->linhasBobina($this->agregarItens($rows), $width);
    }

    /**
     * @return list<string>
     */
    private function quebrarLinhaBobina(string $texto, int $width): array
    {
        $linhas = preg_split('/\R/u', wordwrap($texto, $width, "\n", true)) ?: [];

        return array_values(array_filter(
            array_map(static fn (string $linha): string => rtrim($linha), $linhas),
            static fn (string $linha): bool => $linha !== '',
        ));
    }

    /**
     * Agrega itens já calculados (com chaves trib_*).
     *
     * @param  list<array<string, mixed>>  $itens
     * @return array{trib_fed: float, trib_est: float, trib_mun: float, trib_imp: float, v_tot_trib: float, fonte: string, chave: string, versao: string}
     */
    public function agregarItens(array $itens): array
    {
        $agg = [
            'trib_fed' => 0.0,
            'trib_est' => 0.0,
            'trib_mun' => 0.0,
            'trib_imp' => 0.0,
            'v_tot_trib' => 0.0,
            'fonte' => '',
            'chave' => '',
            'versao' => '',
        ];

        foreach ($itens as $item) {
            $agg['trib_fed'] += (float) ($item['trib_fed'] ?? 0);
            $agg['trib_est'] += (float) ($item['trib_est'] ?? 0);
            $agg['trib_mun'] += (float) ($item['trib_mun'] ?? 0);
            $agg['trib_imp'] += (float) ($item['trib_imp'] ?? 0);

            if ($agg['fonte'] === '' && filled($item['ibpt_fonte'] ?? null)) {
                $agg['fonte'] = (string) $item['ibpt_fonte'];
            }
            if ($agg['chave'] === '' && filled($item['ibpt_chave'] ?? null)) {
                $agg['chave'] = (string) $item['ibpt_chave'];
            }
            if ($agg['versao'] === '' && filled($item['ibpt_versao'] ?? null)) {
                $agg['versao'] = (string) $item['ibpt_versao'];
            }
        }

        $agg['trib_fed'] = round($agg['trib_fed'], 2);
        $agg['trib_est'] = round($agg['trib_est'], 2);
        $agg['trib_mun'] = round($agg['trib_mun'], 2);
        $agg['trib_imp'] = round($agg['trib_imp'], 2);
        $agg['v_tot_trib'] = round($agg['trib_fed'] + $agg['trib_est'] + $agg['trib_mun'], 2);

        return $agg;
    }
}
