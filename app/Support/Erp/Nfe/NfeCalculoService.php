<?php



namespace App\Support\Erp\Nfe;



use App\Models\Empresa;

use App\Models\Product;

use App\Support\Erp\ErpMoney;



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

    public function calcular(array $rows, ?Empresa $empresa, ?string $clienteUf): array

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

            $total = round(max(0, ($qtd * $preco) - $desconto), 2);



            $cfop = filled($row['cfop'] ?? null)

                ? (string) $row['cfop']

                : $this->resolveCfop($product, $interestadual, $empresa);

            $cst = filled($row['cst'] ?? null)

                ? (string) $row['cst']

                : $this->resolveCst($product, $interestadual);

            $csosn = filled($row['csosn'] ?? null)

                ? (string) $row['csosn']

                : $this->resolveCsosn($product, $interestadual);



            $defaultAliqIcms = $this->resolveAliqIcms($product, $interestadual);

            $defaultAliqPis = (float) ($product?->aliq_pis ?? 0);

            $defaultAliqCof = (float) ($product?->aliq_cofins ?? 0);

            $defaultAliqIpi = (float) ($product?->aliq_ipi ?? 0);



            $baseIcms = $this->rowMoney($row, 'base_icms', $total);

            $aliqIcms = $this->rowMoney($row, 'aliq_icms', $defaultAliqIcms);

            $valorIcms = $this->rowMoney($row, 'valor_icms', round($baseIcms * $aliqIcms / 100, 2));



            $basePisCof = $this->rowMoney($row, 'base_pis_icms', $total);

            $aliqPis = $this->rowMoney($row, 'aliq_pis_icms', $defaultAliqPis);

            $valorPis = $this->rowMoney($row, 'valor_pis_icms', round($basePisCof * $aliqPis / 100, 2));

            $aliqCof = $this->rowMoney($row, 'aliq_cofins_icms', $defaultAliqCof);

            $valorCof = $this->rowMoney($row, 'valor_cofins_icms', round($basePisCof * $aliqCof / 100, 2));



            $baseIpi = $this->rowMoney($row, 'base_ipi', $total);

            $aliqIpi = $this->rowMoney($row, 'aliq_ipi', $defaultAliqIpi);

            $valorIpi = $this->rowMoney($row, 'valor_ipi', round($baseIpi * $aliqIpi / 100, 2));



            $valorDesoneracao = $this->rowMoney($row, 'valor_desoneracao', 0.0);



            $bcIbs = $this->rowMoney($row, 'bc_ibs', $total);

            $alqCbs = $this->rowMoney($row, 'alq_cbs', 0.0);

            $alqIbsMun = $this->rowMoney($row, 'alq_ibs_mun', 0.0);

            $alqIbsUf = $this->rowMoney($row, 'alq_ibs_uf', 0.0);

            $vCbs = $this->rowMoney($row, 'v_cbs', round($bcIbs * $alqCbs / 100, 2));

            $vIbsMun = $this->rowMoney($row, 'v_ibs_mun', round($bcIbs * $alqIbsMun / 100, 2));

            $vIbsUf = $this->rowMoney($row, 'v_ibs_uf', round($bcIbs * $alqIbsUf / 100, 2));



            $calculatedRows[] = [

                ...$row,

                'item' => $index + 1,

                'codigo' => filled($row['codigo'] ?? null) ? (string) $row['codigo'] : $product?->codigo,

                'cfop' => $cfop,

                'cst' => $cst,

                'csosn' => $csosn,

                'ncm' => $product?->ncm,

                'cest' => $product?->cest,

                'cod_barra' => $product?->codigo_barras,

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

                'base_icms' => $baseIcms,

                'aliq_icms' => $aliqIcms,

                'valor_icms' => $valorIcms,

                'motivo_desoneracao' => (string) ($row['motivo_desoneracao'] ?? ''),

                'base_desoneracao' => $this->rowMoney($row, 'base_desoneracao', 0.0),

                'desc_desoneracao' => $this->rowMoney($row, 'desc_desoneracao', 0.0),

                'valor_desoneracao' => $valorDesoneracao,

                'base_ipi' => $baseIpi,

                'aliq_ipi' => $aliqIpi,

                'valor_ipi' => $valorIpi,

                'cst_ipi' => $product?->cst_ipi,

                'cst_pis' => $product?->cst_saida ?? '01',

                'base_pis_icms' => $basePisCof,

                'aliq_pis_icms' => $aliqPis,

                'valor_pis_icms' => $valorPis,

                'cst_cofins' => $product?->cst_saida ?? '01',

                'base_cofins_icms' => $basePisCof,

                'aliq_cofins_icms' => $aliqCof,

                'valor_cofins_icms' => $valorCof,

                'class_trib' => (string) ($row['class_trib'] ?? ''),

                'cst_ibs_cbs' => (string) ($row['cst_ibs_cbs'] ?? ''),

                'v_ibs_mun' => $vIbsMun,

                'v_ibs_uf' => $vIbsUf,

                'v_cbs' => $vCbs,

                'bc_ibs' => $bcIbs,

                'alq_cbs' => $alqCbs,

                'alq_ibs_mun' => $alqIbsMun,

                'alq_ibs_uf' => $alqIbsUf,

            ];



            $totais['subtotal'] += $total;

            $totais['desconto'] += $desconto;

            $totais['frete'] += $frete;

            $totais['seguro'] += $seguro;

            $totais['outras'] += $outros;

            $totais['desoneracao'] += $valorDesoneracao;

            $totais['base_icms'] += $baseIcms;

            $totais['valor_icms'] += $valorIcms;

            $totais['base_ipi'] += $baseIpi;

            $totais['valor_ipi'] += $valorIpi;

            $totais['base_pis'] += $basePisCof;

            $totais['valor_pis'] += $valorPis;

            $totais['base_cofins'] += $basePisCof;

            $totais['valor_cofins'] += $valorCof;



            $cfopCounts[$cfop] = ($cfopCounts[$cfop] ?? 0) + 1;

        }



        $totais['total'] = round($totais['subtotal'] + $totais['frete'] + $totais['seguro'] + $totais['outras'], 2);

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

        ];

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


