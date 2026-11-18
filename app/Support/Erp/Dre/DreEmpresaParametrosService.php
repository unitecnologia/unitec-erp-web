<?php

namespace App\Support\Erp\Dre;

use App\Models\DreEmpresaParametro;
use App\Models\Empresa;
use App\Support\Fiscal\RegimeCrtMapper;
use Illuminate\Support\Facades\Schema;

/**
 * Parâmetros de tributos da DRE por empresa.
 */
final class DreEmpresaParametrosService
{
    /**
     * @return array{
     *   exibir_tributos: bool,
     *   calcular_estimativa_simples: bool,
     *   aliquota_simples_pct: ?string,
     *   regime: string,
     *   crt: int,
     *   permite_estimativa_simples: bool
     * }
     */
    public function obter(int $empresaId): array
    {
        $regime = '';
        $crt = 0;
        if ($empresaId > 0 && Schema::hasTable('empresas')) {
            $emp = Empresa::query()->whereKey($empresaId)->first(['id', 'regime_tributario']);
            $regime = RegimeCrtMapper::normalize((string) ($emp?->regime_tributario ?? 'simples'));
            $crt = RegimeCrtMapper::toCrt($regime, false);
        }

        $defaults = [
            'exibir_tributos' => false,
            'calcular_estimativa_simples' => false,
            'aliquota_simples_pct' => null,
            'regime' => $regime !== '' ? $regime : 'simples',
            'crt' => $crt > 0 ? $crt : 1,
            'permite_estimativa_simples' => $crt === 1,
        ];

        if ($empresaId <= 0 || ! Schema::hasTable('dre_empresa_parametros')) {
            return $defaults;
        }

        $row = DreEmpresaParametro::query()->where('empresa_id', $empresaId)->first();
        if ($row === null) {
            return $defaults;
        }

        $aliq = $row->aliquota_simples_pct;
        $aliqStr = $aliq === null || $aliq === '' ? null : number_format((float) $aliq, 4, '.', '');

        return [
            'exibir_tributos' => (bool) $row->exibir_tributos,
            'calcular_estimativa_simples' => (bool) $row->calcular_estimativa_simples,
            'aliquota_simples_pct' => $aliqStr,
            'regime' => $defaults['regime'],
            'crt' => $defaults['crt'],
            'permite_estimativa_simples' => $defaults['permite_estimativa_simples'],
        ];
    }

    public function salvar(
        int $empresaId,
        bool $exibirTributos,
        bool $calcularEstimativa,
        ?string $aliquotaPct,
    ): void {
        if ($empresaId <= 0 || ! Schema::hasTable('dre_empresa_parametros')) {
            return;
        }

        $params = $this->obter($empresaId);
        if (! $params['permite_estimativa_simples']) {
            $calcularEstimativa = false;
        }

        $aliq = null;
        if ($aliquotaPct !== null && trim($aliquotaPct) !== '') {
            $n = (float) str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $aliquotaPct) ?? '');
            if ($n < 0) {
                $n = 0;
            }
            if ($n > 100) {
                $n = 100;
            }
            $aliq = number_format($n, 4, '.', '');
        }

        DreEmpresaParametro::query()->updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'exibir_tributos' => $exibirTributos,
                'calcular_estimativa_simples' => $calcularEstimativa,
                'aliquota_simples_pct' => $aliq,
            ],
        );
    }
}
