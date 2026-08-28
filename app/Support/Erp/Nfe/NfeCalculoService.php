<?php

namespace App\Support\Erp\Nfe;

use App\Models\Empresa;

use App\Models\Product;

use App\Support\Erp\ErpMoney;
use App\Support\Erp\Fiscal\IbptLookupService;

final class NfeCalculoService

{

    /**

     * @param  array<int, array<string, mixed>>  $rows

     * @return array{

     *     rows: array<int, array<string, mixed>>,

     *     totais: array<string, float>,

     *     cfop: ?int

     * }

     */

    public function calcular(array $rows, ?Empresa $empresa, ?string $clienteUf, ?array $impostoCalcHint = null): array

    {

        $empresaUf = strtoupper((string) ($empresa?->uf ?? ''));

        $clienteUf = strtoupper((string) ($clienteUf ?? $empresaUf));

        $interestadual = $clienteUf !== '' && $empresaUf !== '' && $clienteUf !== $empresaUf;

        $totais = $this->emptyTotais();

        $cfopCounts = [];

        $calculatedRows = [];

        foreach ($rows as $index => $row) {

            $productId = (int) ($row['product_id'] ?? 0);

            $product = $productId > 0 ? Product::query()->find($productId) : null;

            $qtd = $this->parseQuantity($row['quantidade'] ?? 1);

            $preco = $this->parseMoney($row['valor_unitario'] ?? 0);

            $desconto = $this->rowMoney($row, 'desconto', 0.0);

            $frete = $this->rowMoney($row, 'frete', 0.0);

            $seguro = $this->rowMoney($row, 'seguro', 0.0);

            $outros = $this->rowMoney($row, 'outros', 0.0);

            $bruto = round($qtd * $preco, 3);
            $total = round(max(0, $bruto + $outros - $desconto), 3);

            $ncmIbpt = (string) ($product?->ncm ?: ($row['ncm'] ?? ''));
            $ibpt = (new IbptLookupService)->calcularParaBase(
                $ncmIbpt,
                $total,
                (int) ($product?->origem ?? ($row['origem'] ?? 0)),
            );

            $cfop = filled($row['cfop'] ?? null)

                ? (string) $row['cfop']

                : $this->resolveCfop($product, $interestadual, $empresa);

            ['cst' => $cst, 'csosn' => $csosn] = $this->resolveIcmsForRow($row, $product, $interestadual, $empresa);

            $defaultAliqIcms = $this->resolveAliqIcms($product, $interestadual);

            $defaultAliqPis = (float) ($product?->aliq_pis ?? 0);

            $defaultAliqCof = (float) ($product?->aliq_cofins ?? 0);

            $defaultAliqIpi = (float) ($product?->aliq_ipi ?? 0);

            $defaultBaseIcms = $total;
            if (
                $this->isEmpresaSimples($empresa)
                && $this->csosnSemBaseIcms($csosn)
                && $defaultAliqIcms <= 0
            ) {
                $defaultBaseIcms = 0.0;
            }

            $baseIcms = $this->rowMoney($row, 'base_icms', $defaultBaseIcms);

            $editedField = ($impostoCalcHint !== null && (int) ($impostoCalcHint['index'] ?? -1) === $index)
                ? (string) ($impostoCalcHint['field'] ?? '')
                : null;

            [$aliqIcms, $valorIcms] = $this->resolveTaxPair(
                $baseIcms,
                $row,
                'aliq_icms',
                'valor_icms',
                $defaultAliqIcms,
                $editedField,
            );

            [$aliqPis, $valorPis] = $this->resolveTaxPair(
                $baseIcms,
                $row,
                'aliq_pis_icms',
                'valor_pis_icms',
                $defaultAliqPis,
                $editedField,
            );

            [$aliqCof, $valorCof] = $this->resolveTaxPair(
                $baseIcms,
                $row,
                'aliq_cofins_icms',
                'valor_cofins_icms',
                $defaultAliqCof,
                $editedField,
            );

            [$aliqIpi, $valorIpi] = $this->resolveTaxPair(
                $baseIcms,
                $row,
                'aliq_ipi',
                'valor_ipi',
                $defaultAliqIpi,
                $editedField,
            );

            $valorDesoneracao = $this->rowMoney($row, 'valor_desoneracao', 0.0);

            $bcIbs = $this->rowMoney($row, 'bc_ibs', $total);

            $alqCbs = $this->rowMoney($row, 'alq_cbs', (float) ($product?->aliq_cbs ?? 0));

            $alqIbsMun = $this->rowMoney($row, 'alq_ibs_mun', (float) ($product?->aliq_ibs_mun ?? 0));

            $alqIbsUf = $this->rowMoney($row, 'alq_ibs_uf', (float) ($product?->aliq_ibs_uf ?? 0));

            $redIbs = (float) ($product?->reducao_ibs ?? 0);
            $redCbs = (float) ($product?->reducao_cbs ?? 0);
            $alqCbsEfet = $redCbs > 0 ? $alqCbs * (1 - ($redCbs / 100)) : $alqCbs;
            $alqIbsMunEfet = $redIbs > 0 ? $alqIbsMun * (1 - ($redIbs / 100)) : $alqIbsMun;
            $alqIbsUfEfet = $redIbs > 0 ? $alqIbsUf * (1 - ($redIbs / 100)) : $alqIbsUf;

            $vCbs = $this->rowMoney($row, 'v_cbs', round($bcIbs * $alqCbsEfet / 100, 2));

            $vIbsMun = $this->rowMoney($row, 'v_ibs_mun', round($bcIbs * $alqIbsMunEfet / 100, 2));

            $vIbsUf = $this->rowMoney($row, 'v_ibs_uf', round($bcIbs * $alqIbsUfEfet / 100, 2));

            $calculatedRows[] = [

                ...$row,

                'item' => $index + 1,

                'codigo' => filled($row['codigo'] ?? null) ? (string) $row['codigo'] : $product?->codigo,

                'referencia' => filled($row['referencia'] ?? null)
                    ? (string) $row['referencia']
                    : trim((string) ($product?->referencia ?? '')),

                'cfop' => $cfop,

                'cst' => $cst,

                'csosn' => $csosn,

                'ncm' => $product?->ncm,

                'cest' => $product?->cest,

                'cod_barra' => filled($row['cod_barra'] ?? null)
                    ? (string) $row['cod_barra']
                    : (string) ($product?->codigo_barras ?? ''),

                'unidade' => filled($row['unidade'] ?? null)

                    ? mb_strtoupper((string) $row['unidade'], 'UTF-8')

                    : mb_strtoupper((string) ($product?->unidade ?: 'UN'), 'UTF-8'),

                'descricao' => $row['descricao'] ?? $product?->descricao ?? '',

                'info_adicionais' => (string) ($row['info_adicionais'] ?? ''),

                'quantidade' => $qtd,

                'valor_unitario' => $preco,

                'desconto' => $desconto,

                'frete' => $frete,

                'seguro' => $seguro,

                'outros' => $outros,

                'total' => $total,
                'trib_fed' => $ibpt['trib_fed'],
                'trib_est' => $ibpt['trib_est'],
                'trib_mun' => $ibpt['trib_mun'],
                'trib_imp' => $ibpt['trib_imp'],
                'ibpt_fonte' => $ibpt['fonte'],
                'ibpt_chave' => $ibpt['chave'],
                'ibpt_versao' => $ibpt['versao'],
                'base_icms' => $baseIcms,

                'aliq_icms' => $aliqIcms,

                'valor_icms' => $valorIcms,

                'motivo_desoneracao' => (string) ($row['motivo_desoneracao'] ?? ''),

                'base_desoneracao' => $this->rowMoney($row, 'base_desoneracao', 0.0),

                'desc_desoneracao' => $this->rowMoney($row, 'desc_desoneracao', 0.0),

                'valor_desoneracao' => $valorDesoneracao,

                'base_ipi' => $baseIcms,

                'aliq_ipi' => $aliqIpi,

                'valor_ipi' => $valorIpi,

                'cst_ipi' => filled($row['cst_ipi'] ?? null)
                    ? (string) $row['cst_ipi']
                    : ($product?->cst_ipi ?? '99'),

                'cst_pis' => filled($row['cst_pis'] ?? null)
                    ? (string) $row['cst_pis']
                    : ($product?->cst_saida ?? '01'),

                'base_pis_icms' => $baseIcms,

                'aliq_pis_icms' => $aliqPis,

                'valor_pis_icms' => $valorPis,

                'cst_cofins' => filled($row['cst_cofins'] ?? null)
                    ? (string) $row['cst_cofins']
                    : ($product?->cst_cofins ?? $product?->cst_saida ?? '01'),

                'base_cofins_icms' => $baseIcms,

                'aliq_cofins_icms' => $aliqCof,

                'valor_cofins_icms' => $valorCof,

                'class_trib' => (string) ($row['class_trib'] ?? $product?->cclass_trib ?? ''),

                'cst_ibs_cbs' => (string) ($row['cst_ibs_cbs'] ?? $product?->iva_cst ?? ''),

                'v_ibs_mun' => $vIbsMun,

                'v_ibs_uf' => $vIbsUf,

                'v_cbs' => $vCbs,

                'bc_ibs' => $bcIbs,

                'alq_cbs' => $alqCbs,

                'alq_ibs_mun' => $alqIbsMun,

                'alq_ibs_uf' => $alqIbsUf,

            ];

            $totais['subtotal'] += $bruto;

            $totais['desconto'] += $desconto;

            $totais['frete'] += $frete;

            $totais['seguro'] += $seguro;

            $totais['outras'] += $outros;

            $totais['desoneracao'] += $valorDesoneracao;

            $totais['base_icms'] += $baseIcms;

            $totais['valor_icms'] += $valorIcms;

            $totais['base_ipi'] += $baseIcms;

            $totais['valor_ipi'] += $valorIpi;

            $totais['base_pis'] += $baseIcms;

            $totais['valor_pis'] += $valorPis;

            $totais['base_cofins'] += $baseIcms;

            $totais['valor_cofins'] += $valorCof;

            $baseSt = $this->rowMoney($row, 'base_icms_st', 0.0);
            $valorSt = $this->rowMoney($row, 'valor_icms_st', 0.0);

            $totais['base_st'] += $baseSt;
            $totais['valor_st'] += $valorSt;

            $totais['trib_fed'] += $ibpt['trib_fed'];
            $totais['trib_est'] += $ibpt['trib_est'];
            $totais['trib_mun'] += $ibpt['trib_mun'];
            $totais['trib_imp'] += $ibpt['trib_imp'];
            if (($totais['ibpt_fonte'] ?? '') === '' && filled($ibpt['fonte'] ?? null)) {
                $totais['ibpt_fonte'] = (string) $ibpt['fonte'];
                $totais['ibpt_chave'] = (string) ($ibpt['chave'] ?? '');
                $totais['ibpt_versao'] = (string) ($ibpt['versao'] ?? '');
            }

            $cfopCounts[$cfop] = ($cfopCounts[$cfop] ?? 0) + 1;

        }

        $totais['subtotal'] = round($totais['subtotal'], 2);
        $totais['desconto'] = round($totais['desconto'], 2);
        $totais['frete'] = round($totais['frete'], 2);
        $totais['seguro'] = round($totais['seguro'], 2);
        $totais['outras'] = round($totais['outras'], 2);
        $totais['total'] = round(
            $totais['subtotal'] - $totais['desconto'] + $totais['frete'] + $totais['seguro'] + $totais['outras'],
            2,
        );
        $totais['trib_fed'] = round((float) $totais['trib_fed'], 2);
        $totais['trib_est'] = round((float) $totais['trib_est'], 2);
        $totais['trib_mun'] = round((float) $totais['trib_mun'], 2);
        $totais['trib_imp'] = round((float) $totais['trib_imp'], 2);
        $totais['v_tot_trib'] = round((float) $totais['trib_fed'] + (float) $totais['trib_est'] + (float) $totais['trib_mun'], 2);
        $totais['ibpt_texto'] = (new IbptLookupService)->formatarTextoLei12741([
            'trib_fed' => $totais['trib_fed'],
            'trib_est' => $totais['trib_est'],
            'trib_mun' => $totais['trib_mun'],
            'v_tot_trib' => $totais['v_tot_trib'],
            'fonte' => (string) ($totais['ibpt_fonte'] ?? ''),
            'chave' => (string) ($totais['ibpt_chave'] ?? ''),
            'versao' => (string) ($totais['ibpt_versao'] ?? ''),
        ]);

        arsort($cfopCounts);

        $cfopDominante = $cfopCounts !== [] ? (int) array_key_first($cfopCounts) : null;

        return [

            'rows' => $calculatedRows,

            'totais' => $totais,

            'cfop' => $cfopDominante,

        ];

    }

    /**

     * @return array<string, float>

     */

    public function emptyTotais(): array

    {

        return [

            'subtotal' => 0.0,

            'desconto' => 0.0,

            'frete' => 0.0,

            'seguro' => 0.0,

            'outras' => 0.0,

            'desoneracao' => 0.0,

            'base_icms' => 0.0,

            'valor_icms' => 0.0,

            'base_st' => 0.0,

            'valor_st' => 0.0,

            'base_ipi' => 0.0,

            'valor_ipi' => 0.0,

            'base_pis' => 0.0,

            'valor_pis' => 0.0,

            'base_cofins' => 0.0,

            'valor_cofins' => 0.0,

            'total' => 0.0,

            'trib_fed' => 0.0,

            'trib_est' => 0.0,

            'trib_mun' => 0.0,

            'trib_imp' => 0.0,

            'v_tot_trib' => 0.0,

            'ibpt_fonte' => '',

            'ibpt_chave' => '',

            'ibpt_versao' => '',

            'ibpt_texto' => '',

        ];

    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{cst: string, csosn: string}
     */
    protected function resolveIcmsForRow(array $row, ?Product $product, bool $interestadual, ?Empresa $empresa): array
    {
        if ($this->isEmpresaSimples($empresa)) {
            if (filled($row['cst'] ?? null)) {
                $cstNorm = preg_replace('/\D/', '', (string) $row['cst']) ?: '';
                $csosnNorm = preg_replace('/\D/', '', (string) ($row['csosn'] ?? '')) ?: '';

                if (
                    $cstNorm !== ''
                    && $cstNorm !== $csosnNorm
                    && ! $this->cstMatchesProductIcms($row['cst'], $product)
                ) {
                    return ['cst' => '', 'csosn' => trim((string) $row['cst'])];
                }
            }

            if (filled($row['csosn'] ?? null)) {
                return ['cst' => '', 'csosn' => (string) $row['csosn']];
            }

            return [
                'cst' => '',
                'csosn' => (string) ($this->resolveCsosn($product, $interestadual) ?: '102'),
            ];
        }

        $cst = filled($row['cst'] ?? null)
            ? (string) $row['cst']
            : $this->resolveCst($product, $interestadual);

        $csosn = filled($row['csosn'] ?? null)
            ? (string) $row['csosn']
            : (string) ($this->resolveCsosn($product, $interestadual) ?? '');

        return ['cst' => $cst, 'csosn' => $csosn];
    }

    protected function isEmpresaSimples(?Empresa $empresa): bool
    {
        if ($empresa === null) {
            return true;
        }

        return strtolower((string) ($empresa->regime_tributario ?? 'simples')) === 'simples';
    }

    protected function csosnSemBaseIcms(string $csosn): bool
    {
        $norm = str_pad(preg_replace('/\D/', '', $csosn) ?? '', 3, '0', STR_PAD_LEFT);

        return in_array($norm, ['102', '103', '300', '400', '500'], true);
    }

    protected function cstMatchesProductIcms(mixed $rowCst, ?Product $product): bool
    {
        if ($product === null) {
            return false;
        }

        $rowDigits = preg_replace('/\D/', '', (string) $rowCst) ?: '';
        $productDigits = preg_replace('/\D/', '', (string) ($product->cst_icms ?? '')) ?: '';

        if ($rowDigits === '' || $productDigits === '') {
            return false;
        }

        return ltrim($rowDigits, '0') === ltrim($productDigits, '0')
            || str_pad($rowDigits, 3, '0', STR_PAD_LEFT) === str_pad($productDigits, 3, '0', STR_PAD_LEFT);
    }

    protected function resolveCfop(?Product $product, bool $interestadual, ?Empresa $empresa): string

    {

        if ($interestadual) {

            return (string) ($product?->cfop_externo ?: $empresa?->param_imp_cfop_venda ?? '6102');

        }

        return (string) ($product?->cfop_interno ?: $empresa?->param_imp_cfop_venda ?? '5102');

    }

    protected function resolveCst(?Product $product, bool $interestadual): string

    {

        if ($interestadual) {

            return (string) ($product?->cst_externo ?: $product?->cst_icms ?: '00');

        }

        return (string) ($product?->cst_icms ?: '00');

    }

    protected function resolveCsosn(?Product $product, bool $interestadual): ?string

    {

        if ($interestadual) {

            return $product?->csosn_externo ?: $product?->csosn;

        }

        return $product?->csosn;

    }

    protected function resolveAliqIcms(?Product $product, bool $interestadual): float

    {

        if ($interestadual) {

            return (float) ($product?->aliq_icms_externo ?: $product?->aliq_icms ?: 0);

        }

        return (float) ($product?->aliq_icms ?? 0);

    }

    /**

     * @param  array<string, mixed>  $row

     */

    private function rowMoney(array $row, string $key, float $default): float

    {

        if (array_key_exists($key, $row) && $row[$key] !== '' && $row[$key] !== null) {

            return $this->parseMoney($row[$key]);

        }

        return $default;

    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: float, 1: float}
     */
    private function resolveTaxPair(
        float $base,
        array $row,
        string $aliqKey,
        string $valorKey,
        float $defaultAliq,
        ?string $editedField,
    ): array {
        if ($editedField === $valorKey && $base > 0) {
            $valor = round($this->rowMoney($row, $valorKey, 0.0), 2);
            $aliq = round($valor / $base * 100, 2);

            return [$aliq, $valor];
        }

        $aliq = $this->rowMoney($row, $aliqKey, $defaultAliq);
        $valor = round($base * $aliq / 100, 2);

        return [$aliq, $valor];
    }

    private function parseQuantity(mixed $value): float

    {

        if (is_string($value)) {

            return max(0.0001, ErpMoney::parseBr($value, 4));

        }

        return max(0.0001, (float) ($value ?? 1));

    }

    private function parseMoney(mixed $value): float

    {

        if (is_string($value)) {

            return max(0, ErpMoney::parseBr($value, 4));

        }

        return max(0, (float) ($value ?? 0));

    }

}

