<?php

namespace App\Support\Erp\NotaFornecedor;

use App\Support\Erp\Fiscal\CfopEntradaResolver;
use DOMDocument;
use DOMElement;

/**
 * Parser fiscal de itens da NF-e de entrada.
 * Reutilizado pelo sync de nota_fornecedor_itens e pelo DANFE — sem regra fiscal no serviço de impressão.
 */
final class NotaFornecedorFiscalSnapshotParser
{
    /**
     * @return list<array{
     *     n_item: int,
     *     c_prod: string,
     *     c_ean: string,
     *     descricao: string,
     *     ncm: string,
     *     cest: string,
     *     cfop: string,
     *     unidade: string,
     *     quantidade: float,
     *     valor_unitario: float,
     *     valor_total: float,
     *     valor_desconto: float,
     *     inf_ad_prod: string,
     *     fiscal_snapshot: array<string, mixed>,
     *     danfe: array<string, mixed>
     * }>|null
     */
    public function parseItens(string $xml): ?array
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            return null;
        }

        $infNfe = $dom->getElementsByTagName('infNFe')->item(0);

        if (! $infNfe instanceof DOMElement) {
            return null;
        }

        $itens = [];
        $index = 0;

        foreach ($infNfe->getElementsByTagName('det') as $det) {
            if (! $det instanceof DOMElement) {
                continue;
            }

            $index++;
            $prod = $det->getElementsByTagName('prod')->item(0);
            $imposto = $det->getElementsByTagName('imposto')->item(0);

            if (! $prod instanceof DOMElement) {
                continue;
            }

            $impostoEl = $imposto instanceof DOMElement ? $imposto : null;
            $icms = $this->extractIcms($impostoEl);
            $ipi = $this->extractIpi($impostoEl);
            $pis = $this->extractPis($impostoEl);
            $cofins = $this->extractCofins($impostoEl);
            $ibscbs = $this->extractIbscbs($impostoEl);

            $qCom = $this->decimal($this->child($prod, 'qCom'));
            $vUnCom = $this->decimal($this->child($prod, 'vUnCom'));
            $vProd = $this->decimal($this->child($prod, 'vProd'));
            $vDesc = $this->decimal($this->child($prod, 'vDesc'));
            $ean = preg_replace('/\D/', '', $this->child($prod, 'cEAN') ?: $this->child($prod, 'cEANTrib') ?: '') ?? '';
            $nItemAttr = trim((string) ($det->getAttribute('nItem') ?: ''));
            $nItem = $nItemAttr !== '' ? (int) $nItemAttr : $index;
            $cfopXml = $this->child($prod, 'CFOP');
            $cest = preg_replace('/\D/', '', $this->child($prod, 'CEST') ?: '') ?? '';
            $infAdProd = trim($this->child($det, 'infAdProd'));
            $temSt = $this->itemTemSt($icms, $cfopXml);

            $snapshot = [
                'origem' => $icms['origem'],
                'icms' => [
                    'cst' => $icms['cst_puro'],
                    'csosn' => $icms['csosn'],
                    'cst_completo' => $icms['cst'],
                    'mod_bc' => $icms['mod_bc'],
                    'p_red_bc' => $icms['p_red_bc'],
                    'v_bc' => $icms['v_bc'],
                    'p_icms' => $icms['p_icms'],
                    'v_icms' => $icms['v_icms'],
                    'v_bc_st' => $icms['v_bc_st'],
                    'v_icms_st' => $icms['v_icms_st'],
                    'p_mva_st' => $icms['p_mva_st'],
                ],
                'ipi' => $ipi,
                'pis' => $pis,
                'cofins' => $cofins,
                'ibscbs' => $ibscbs,
                'inf_ad_prod' => $infAdProd,
                'cest' => $cest,
            ];

            $itens[] = [
                'n_item' => $nItem,
                'c_prod' => $this->child($prod, 'cProd') ?: '',
                'c_ean' => $ean,
                'descricao' => mb_strtoupper($this->child($prod, 'xProd') ?: '', 'UTF-8'),
                'ncm' => $this->child($prod, 'NCM'),
                'cest' => $cest,
                'cfop' => $cfopXml,
                'unidade' => $this->child($prod, 'uCom') ?: 'UN',
                'quantidade' => $qCom,
                'valor_unitario' => $vUnCom,
                'valor_total' => $vProd,
                'valor_desconto' => $vDesc,
                'inf_ad_prod' => $infAdProd,
                'fiscal_snapshot' => $snapshot,
                'danfe' => [
                    'item' => (string) $nItem,
                    'codigo' => $this->child($prod, 'cProd') ?: '—',
                    'ean' => $ean,
                    'descricao' => mb_strtoupper($this->child($prod, 'xProd') ?: '—', 'UTF-8'),
                    'info_adicionais' => $infAdProd,
                    'ncm' => $this->child($prod, 'NCM'),
                    'cest' => $cest,
                    'cst' => $icms['cst'],
                    'cfop' => $cfopXml,
                    'un' => $this->child($prod, 'uCom') ?: 'UN',
                    'quant_num' => $qCom,
                    'valor_unit_num' => $vUnCom,
                    'valor_total_num' => $vProd,
                    'desconto_num' => $vDesc,
                    'icms' => $icms,
                    'ipi' => $ipi,
                    'pis' => $pis,
                    'cofins' => $cofins,
                    'ibscbs' => $ibscbs,
                    'tem_st' => $temSt,
                    'tipo_icms' => $this->resolveTipoIcms($icms, $temSt),
                    'lotes' => $this->extractRastros($prod),
                ],
            ];
        }

        return $itens === [] ? null : $itens;
    }

    /**
     * Compara dois snapshots fiscais (ordem de chaves irrelevante).
     *
     * @param  array<string, mixed>|null  $a
     * @param  array<string, mixed>|null  $b
     */
    public function snapshotsIguais(?array $a, ?array $b): bool
    {
        return $this->normalizeForCompare($a) === $this->normalizeForCompare($b);
    }

    /**
     * Rateia snapshot pela quantidade devolvida.
     * Mantém percentuais; arredonda valores monetários em 2 casas.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    public function ratearSnapshot(array $snapshot, float $qtdOriginal, float $qtdDevolvida): array
    {
        $qtdOriginal = round(max(0.0, $qtdOriginal), 4);
        $qtdDevolvida = round(max(0.0, $qtdDevolvida), 4);

        if ($qtdOriginal <= 0.0 || $qtdDevolvida <= 0.0) {
            return $snapshot;
        }

        $fator = $qtdDevolvida / $qtdOriginal;
        $out = $snapshot;

        foreach (['icms', 'ipi', 'pis', 'cofins', 'ibscbs'] as $bloco) {
            if (! isset($out[$bloco]) || ! is_array($out[$bloco])) {
                continue;
            }

            $out[$bloco] = $this->ratearBlocoMonetario($out[$bloco], $fator);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $bloco
     * @return array<string, mixed>
     */
    private function ratearBlocoMonetario(array $bloco, float $fator): array
    {
        $percentuais = [
            'p_red_bc', 'p_icms', 'p_mva_st', 'p_ipi', 'p_pis', 'p_cofins',
            'p_ibs', 'p_ibs_uf', 'p_ibs_mun', 'p_cbs', 'p_red_ibs', 'p_red_cbs',
        ];

        foreach ($bloco as $key => $value) {
            if (! is_numeric($value)) {
                continue;
            }

            if (in_array($key, $percentuais, true) || str_starts_with((string) $key, 'p_')) {
                continue;
            }

            if (str_starts_with((string) $key, 'v_') || in_array($key, ['v_bc', 'base'], true)) {
                $bloco[$key] = round((float) $value * $fator, 2);
            }
        }

        return $bloco;
    }

    /**
     * @return array{
     *     cst: string,
     *     cst_puro: string,
     *     csosn: string,
     *     origem: int,
     *     mod_bc: string,
     *     p_red_bc: float,
     *     v_bc: float,
     *     p_icms: float,
     *     v_icms: float,
     *     v_bc_st: float,
     *     v_icms_st: float,
     *     p_mva_st: float
     * }
     */
    public function extractIcms(?DOMElement $imposto): array
    {
        $empty = [
            'cst' => '',
            'cst_puro' => '',
            'csosn' => '',
            'origem' => 0,
            'mod_bc' => '',
            'p_red_bc' => 0.0,
            'v_bc' => 0.0,
            'p_icms' => 0.0,
            'v_icms' => 0.0,
            'v_bc_st' => 0.0,
            'v_icms_st' => 0.0,
            'p_mva_st' => 0.0,
        ];

        if (! $imposto) {
            return $empty;
        }

        $icms = $imposto->getElementsByTagName('ICMS')->item(0);

        if (! $icms instanceof DOMElement) {
            return $empty;
        }

        foreach ($icms->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $cst = $this->child($child, 'CST');
            $csosn = $this->child($child, 'CSOSN');
            $orig = $this->child($child, 'orig');
            $cstOuCsosn = $cst !== '' ? $cst : $csosn;

            return [
                'cst' => $orig !== '' ? $orig.$cstOuCsosn : $cstOuCsosn,
                'cst_puro' => $cst,
                'csosn' => $csosn,
                'origem' => (int) ($orig !== '' ? $orig : 0),
                'mod_bc' => $this->child($child, 'modBC'),
                'p_red_bc' => $this->decimal($this->child($child, 'pRedBC')),
                'v_bc' => $this->decimal($this->child($child, 'vBC')),
                'p_icms' => $this->decimal($this->child($child, 'pICMS')),
                'v_icms' => $this->decimal($this->child($child, 'vICMS')),
                'v_bc_st' => $this->decimal($this->child($child, 'vBCST')),
                'v_icms_st' => $this->decimal($this->child($child, 'vICMSST')),
                'p_mva_st' => $this->decimal($this->child($child, 'pMVAST')),
            ];
        }

        return $empty;
    }

    /**
     * @return array{cst: string, p_ipi: float, v_ipi: float, v_bc: float}
     */
    public function extractIpi(?DOMElement $imposto): array
    {
        $empty = ['cst' => '', 'p_ipi' => 0.0, 'v_ipi' => 0.0, 'v_bc' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $ipi = $imposto->getElementsByTagName('IPI')->item(0);

        if (! $ipi instanceof DOMElement) {
            return $empty;
        }

        $ipiTrib = $ipi->getElementsByTagName('IPITrib')->item(0);
        $ipiNt = $ipi->getElementsByTagName('IPINT')->item(0);
        $node = $ipiTrib instanceof DOMElement ? $ipiTrib : ($ipiNt instanceof DOMElement ? $ipiNt : null);

        if (! $node instanceof DOMElement) {
            return $empty;
        }

        return [
            'cst' => $this->child($node, 'CST'),
            'p_ipi' => $this->decimal($this->child($node, 'pIPI')),
            'v_ipi' => $this->decimal($this->child($node, 'vIPI')),
            'v_bc' => $this->decimal($this->child($node, 'vBC')),
        ];
    }

    /**
     * @return array{cst: string, v_bc: float, p_pis: float, v_pis: float}
     */
    public function extractPis(?DOMElement $imposto): array
    {
        $empty = ['cst' => '', 'v_bc' => 0.0, 'p_pis' => 0.0, 'v_pis' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $pis = $imposto->getElementsByTagName('PIS')->item(0);

        if (! $pis instanceof DOMElement) {
            return $empty;
        }

        foreach ($pis->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            return [
                'cst' => $this->child($child, 'CST'),
                'v_bc' => $this->decimal($this->child($child, 'vBC')),
                'p_pis' => $this->decimal($this->child($child, 'pPIS')),
                'v_pis' => $this->decimal($this->child($child, 'vPIS')),
            ];
        }

        return $empty;
    }

    /**
     * @return array{cst: string, v_bc: float, p_cofins: float, v_cofins: float}
     */
    public function extractCofins(?DOMElement $imposto): array
    {
        $empty = ['cst' => '', 'v_bc' => 0.0, 'p_cofins' => 0.0, 'v_cofins' => 0.0];

        if (! $imposto) {
            return $empty;
        }

        $cofins = $imposto->getElementsByTagName('COFINS')->item(0);

        if (! $cofins instanceof DOMElement) {
            return $empty;
        }

        foreach ($cofins->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            return [
                'cst' => $this->child($child, 'CST'),
                'v_bc' => $this->decimal($this->child($child, 'vBC')),
                'p_cofins' => $this->decimal($this->child($child, 'pCOFINS')),
                'v_cofins' => $this->decimal($this->child($child, 'vCOFINS')),
            ];
        }

        return $empty;
    }

    /**
     * @return array{
     *     cst: string,
     *     c_class_trib: string,
     *     v_bc: float,
     *     v_ibs: float,
     *     v_cbs: float,
     *     p_ibs: float,
     *     p_ibs_uf: float,
     *     v_ibs_uf: float,
     *     p_ibs_mun: float,
     *     v_ibs_mun: float,
     *     p_cbs: float,
     *     p_red_ibs: float,
     *     p_red_cbs: float
     * }
     */
    public function extractIbscbs(?DOMElement $imposto): array
    {
        $empty = [
            'cst' => '',
            'c_class_trib' => '',
            'v_bc' => 0.0,
            'v_ibs' => 0.0,
            'v_cbs' => 0.0,
            'p_ibs' => 0.0,
            'p_ibs_uf' => 0.0,
            'v_ibs_uf' => 0.0,
            'p_ibs_mun' => 0.0,
            'v_ibs_mun' => 0.0,
            'p_cbs' => 0.0,
            'p_red_ibs' => 0.0,
            'p_red_cbs' => 0.0,
        ];

        if (! $imposto) {
            return $empty;
        }

        $ibscbs = $imposto->getElementsByTagName('IBSCBS')->item(0);

        if (! $ibscbs instanceof DOMElement) {
            return $empty;
        }

        $g = $ibscbs->getElementsByTagName('gIBSCBS')->item(0);
        $root = $g instanceof DOMElement ? $g : $ibscbs;

        $gIbsUf = $root->getElementsByTagName('gIBSUF')->item(0);
        $gIbsMun = $root->getElementsByTagName('gIBSMun')->item(0);
        $gCbs = $root->getElementsByTagName('gCBS')->item(0);

        $pIbsUf = $gIbsUf instanceof DOMElement ? $this->decimal($this->child($gIbsUf, 'pIBSUF')) : 0.0;
        $vIbsUf = $gIbsUf instanceof DOMElement ? $this->decimal($this->child($gIbsUf, 'vIBSUF')) : 0.0;
        $pIbsMun = $gIbsMun instanceof DOMElement ? $this->decimal($this->child($gIbsMun, 'pIBSMun')) : 0.0;
        $vIbsMun = $gIbsMun instanceof DOMElement ? $this->decimal($this->child($gIbsMun, 'vIBSMun')) : 0.0;
        $pCbs = $gCbs instanceof DOMElement ? $this->decimal($this->child($gCbs, 'pCBS')) : 0.0;
        $vCbs = $gCbs instanceof DOMElement ? $this->decimal($this->child($gCbs, 'vCBS')) : 0.0;

        $vIbs = $this->decimal($this->child($root, 'vIBS'));
        if ($vIbs <= 0.0) {
            $vIbs = $vIbsUf + $vIbsMun;
        }

        $pRedIbs = 0.0;
        $pRedCbs = 0.0;
        if ($gIbsUf instanceof DOMElement) {
            $gRed = $gIbsUf->getElementsByTagName('gRed')->item(0);
            if ($gRed instanceof DOMElement) {
                $pRedIbs = $this->decimal($this->child($gRed, 'pRedAliq'));
            }
        }
        if ($gCbs instanceof DOMElement) {
            $gRed = $gCbs->getElementsByTagName('gRed')->item(0);
            if ($gRed instanceof DOMElement) {
                $pRedCbs = $this->decimal($this->child($gRed, 'pRedAliq'));
            }
        }

        return [
            'cst' => $this->child($ibscbs, 'CST') ?: $this->child($root, 'CST'),
            'c_class_trib' => $this->child($ibscbs, 'cClassTrib') ?: $this->child($root, 'cClassTrib'),
            'v_bc' => $this->decimal($this->child($root, 'vBC') ?: $this->child($root, 'vBCIBSCBS')),
            'v_ibs' => $vIbs,
            'v_cbs' => $vCbs,
            'p_ibs' => $pIbsUf + $pIbsMun,
            'p_ibs_uf' => $pIbsUf,
            'v_ibs_uf' => $vIbsUf,
            'p_ibs_mun' => $pIbsMun,
            'v_ibs_mun' => $vIbsMun,
            'p_cbs' => $pCbs,
            'p_red_ibs' => $pRedIbs,
            'p_red_cbs' => $pRedCbs,
        ];
    }

    /**
     * @param  array{cst?: string, cst_puro?: string, v_bc_st?: float, v_icms_st?: float}  $icmsVals
     */
    public function itemTemSt(array $icmsVals, string $cfopXml): bool
    {
        if (((float) ($icmsVals['v_icms_st'] ?? 0)) > 0.0 || ((float) ($icmsVals['v_bc_st'] ?? 0)) > 0.0) {
            return true;
        }

        $cstPuro = preg_replace('/\D/', '', (string) ($icmsVals['cst_puro'] ?? '')) ?? '';

        if ($cstPuro === '') {
            $cstFull = preg_replace('/\D/', '', (string) ($icmsVals['cst'] ?? '')) ?? '';
            if (strlen($cstFull) >= 3 && in_array(substr($cstFull, -3), ['201', '202', '203', '500'], true)) {
                $cstPuro = substr($cstFull, -3);
            } elseif (strlen($cstFull) >= 2) {
                $cstPuro = substr($cstFull, -2);
            } else {
                $cstPuro = $cstFull;
            }
        }

        if (in_array($cstPuro, ['10', '30', '60', '70', '201', '202', '203', '500'], true)) {
            return true;
        }

        return CfopEntradaResolver::isCfopSaidaSt($cfopXml);
    }

    /**
     * @param  array{cst?: string, cst_puro?: string}  $icmsVals
     */
    public function resolveTipoIcms(array $icmsVals, bool $temSt): string
    {
        if ($temSt) {
            return 'st';
        }

        $cstPuro = preg_replace('/\D/', '', (string) ($icmsVals['cst_puro'] ?? '')) ?? '';

        if ($cstPuro === '') {
            $cstFull = preg_replace('/\D/', '', (string) ($icmsVals['cst'] ?? '')) ?? '';
            $cstPuro = strlen($cstFull) >= 2 ? substr($cstFull, -2) : $cstFull;
        }

        if (in_array($cstPuro, ['40', '41', '50'], true)) {
            return 'isento';
        }

        if (in_array($cstPuro, ['300', '400'], true) || in_array(substr($cstPuro, -3), ['300', '400'], true)) {
            return 'isento';
        }

        return 'normal';
    }

    /**
     * @return list<array<string, string>>
     */
    public function extractRastros(DOMElement $prod): array
    {
        $lotes = [];

        foreach ($prod->getElementsByTagName('rastro') as $rastro) {
            if (! $rastro instanceof DOMElement) {
                continue;
            }

            $lotes[] = [
                'lote' => $this->child($rastro, 'nLote'),
                'qtd' => $this->child($rastro, 'qLote'),
                'fabricacao' => $this->child($rastro, 'dFab'),
                'validade' => $this->child($rastro, 'dVal'),
            ];
        }

        return $lotes;
    }

    public function child(DOMElement $parent, string $tag): string
    {
        $nodes = $parent->getElementsByTagName($tag);

        if ($nodes->length === 0) {
            return '';
        }

        return trim((string) $nodes->item(0)?->textContent);
    }

    public function decimal(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        return (float) str_replace(',', '.', $value);
    }

    /**
     * @param  mixed  $value
     */
    private function normalizeForCompare(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[(string) $k] = $this->normalizeValue($v);
            }
            ksort($normalized);

            return json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '';
        }

        return json_encode($this->normalizeValue($value), JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) ?: '';
    }

    private function normalizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[(string) $k] = $this->normalizeValue($v);
            }
            ksort($out);

            return $out;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value) && str_contains((string) $value, '.'))) {
            return round((float) $value, 4);
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }

        return $value;
    }
}
