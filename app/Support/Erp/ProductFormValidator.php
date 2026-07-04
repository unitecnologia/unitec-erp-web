<?php

namespace App\Support\Erp;

use App\Models\Empresa;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductFormValidator
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function validateBeforeSave(array $data, ?int $excludeProductId = null): void
    {
        $descricao = Str::upper(trim((string) ($data['descricao'] ?? '')));

        if ($descricao === '') {
            throw ValidationException::withMessages([
                'descricao' => 'Digite DESCRIÇÃO!',
            ]);
        }

        $precoVenda = (float) ($data['preco_venda'] ?? 0);

        if ($precoVenda <= 0) {
            throw ValidationException::withMessages([
                'preco_venda' => 'Digite o Preço de Venda!',
            ]);
        }

        $unidade = trim((string) ($data['unidade'] ?? ''));

        if ($unidade === '') {
            throw ValidationException::withMessages([
                'unidade' => 'Digite a Unidade!',
            ]);
        }

        self::validateCstIcms((string) ($data['cst_icms'] ?? ''));

        self::validateCestIfRequired($data);

        self::validateCombustivel($data);

        self::validateDuplicateBarcode($data, $excludeProductId);

        self::validateDuplicateReferencia($data, $excludeProductId);
    }

    public static function validateGradeStock(array $data, float $gradeTotalQty, bool $bloquearEstoqueNegativo = false): void
    {
        if (! $bloquearEstoqueNegativo) {
            return;
        }

        if (! ($data['is_grade'] ?? false) || ! ($data['contr_est_grade'] ?? false)) {
            return;
        }

        $estoque = BrDecimal::parse($data['estoque'] ?? 0, 3);

        if (round($gradeTotalQty, 3) !== round($estoque, 3)) {
            throw ValidationException::withMessages([
                'estoque' => 'Quantidade em Grade é diferente de Estoque Atual!',
            ]);
        }
    }

    protected static function validateCstIcms(string $cst): void
    {
        $cst = trim($cst);

        if (strlen($cst) !== 3) {
            throw ValidationException::withMessages([
                'cst_icms' => 'CST ICMS inválido.',
            ]);
        }

        $first = (int) $cst[0];
        $lastTwo = (int) substr($cst, 1, 2);

        if ($first < 0 || $first > 7) {
            throw ValidationException::withMessages([
                'cst_icms' => 'CST ICMS inválido.',
            ]);
        }

        if (! in_array($lastTwo, [0, 10, 20, 30, 40, 41, 50, 51, 60, 61, 70, 90], true)) {
            throw ValidationException::withMessages([
                'cst_icms' => 'CST ICMS inválido.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function validateCestIfRequired(array $data): void
    {
        $cst = trim((string) ($data['cst_icms'] ?? ''));
        $suffix = strlen($cst) >= 3 ? substr($cst, 1, 2) : '';

        if (! in_array($suffix, ['10', '30', '60', '61', '70'], true)) {
            return;
        }

        $cest = trim((string) ($data['cest'] ?? ''));

        if ($cest === '') {
            throw ValidationException::withMessages([
                'cest' => 'Informe o CEST!',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function validateCombustivel(array $data): void
    {
        if (! ($data['is_combustivel'] ?? false)) {
            return;
        }

        $sum = BrDecimal::parse($data['glp_pct'] ?? 0, 2)
            + BrDecimal::parse($data['gnn_pct'] ?? 0, 2)
            + BrDecimal::parse($data['gni_pct'] ?? 0, 2);

        if ($sum <= 0) {
            return;
        }

        if (round($sum, 2) !== 100.0) {
            throw ValidationException::withMessages([
                'glp_pct' => 'A soma das alíquotas de GLP, GNi e GNn deve ser 100%.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function validateDuplicateBarcode(array $data, ?int $excludeProductId): void
    {
        $barcode = preg_replace('/\D/', '', (string) ($data['codigo_barras'] ?? ''));

        if ($barcode === '' || strtoupper(trim((string) ($data['codigo_barras'] ?? ''))) === 'SEM GTIN') {
            return;
        }

        $existing = Product::query()
            ->where('codigo_barras', $barcode)
            ->when($excludeProductId, fn ($query) => $query->where('id', '!=', $excludeProductId))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'codigo_barras' => 'Já existe produto cadastrado com este CÓDIGO DE BARRAS! '
                    . $existing->codigo . '-' . $existing->descricao,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected static function validateDuplicateReferencia(array $data, ?int $excludeProductId): void
    {
        $referencia = trim((string) ($data['referencia'] ?? ''));

        if ($referencia === '') {
            return;
        }

        $existing = Product::query()
            ->where('referencia', $referencia)
            ->when($excludeProductId, fn ($query) => $query->where('id', '!=', $excludeProductId))
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'referencia' => 'Referência já cadastrada!',
            ]);
        }
    }

    public static function normalizeBarcodeForSave(array $data): array
    {
        $barcode = trim((string) ($data['codigo_barras'] ?? ''));

        if ($barcode === '') {
            $codigo = (int) preg_replace('/\D/', '', (string) ($data['codigo'] ?? '0'));
            $data['codigo_barras'] = $codigo > 0
                ? ProductEanGenerator::generate($codigo)
                : 'SEM GTIN';
        } elseif (strtoupper($barcode) === 'SEM GTIN') {
            $data['codigo_barras'] = 'SEM GTIN';
        } else {
            $data['codigo_barras'] = preg_replace('/\D/', '', $barcode);
        }

        if (trim((string) ($data['referencia'] ?? '')) === '') {
            $data['referencia'] = (string) ($data['codigo'] ?? '');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fiscalDefaultsFromEmpresa(?Empresa $empresa): array
    {
        $pad = static fn (?string $value, string $fallback, int $length): string => str_pad(
            substr(trim((string) ($value ?: $fallback)), 0, $length),
            $length,
            '0',
            STR_PAD_LEFT,
        );

        $cfopInterno = (string) ($empresa?->param_imp_cfop_venda ?? '5102');
        $cst = $pad($empresa?->param_imp_icms_cst ?? null, '041', 3);
        $csosn = $pad($empresa?->param_imp_csosn ?? null, '102', 3);

        return [
            'cfop_interno' => $cfopInterno,
            'origem' => 0,
            'cst_icms' => $cst,
            'csosn' => $csosn,
            'aliq_icms' => (float) ($empresa?->param_imp_icms_aliquota ?? 0),
            'cfop_externo' => self::cfopExternoFromInterno($cfopInterno),
            'cst_externo' => $cst,
            'csosn_externo' => $csosn,
            'aliq_icms_externo' => (float) ($empresa?->param_imp_icms_aliquota ?? 0),
            'cst_entrada' => $pad($empresa?->param_imp_pis_cst ?? null, '07', 2),
            'cst_saida' => $pad($empresa?->param_imp_cofins_cst ?? null, '07', 2),
            'aliq_pis' => (float) ($empresa?->param_imp_pis_aliquota ?? 0),
            'aliq_cofins' => (float) ($empresa?->param_imp_cofins_aliquota ?? 0),
            'cst_ipi' => $pad($empresa?->param_imp_ipi_cst ?? null, '53', 2),
            'aliq_ipi' => (float) ($empresa?->param_imp_ipi_aliquota ?? 0),
            'fcp_pct' => (float) ($empresa?->param_difal_fcp_pct ?? 0),
            'mva_pct' => 0,
            'mva_normal' => 0,
            'reducao_base_pct' => 0,
            'icms_diferido' => 0,
            'aliq_deson' => 0,
            'motivo_desoneracao' => null,
            'tipo_tributacao' => null,
            'tributacao_monofasica' => false,
            'cod_beneficio' => null,
            'cod_enq_ipi' => null,
            'glp_pct' => 0,
            'gnn_pct' => 0,
            'gni_pct' => 0,
            'peso_liq' => 0,
            'anp_code' => null,
            'issqn' => 0,
            'prefixo_balanca' => null,
        ];
    }

    protected static function cfopExternoFromInterno(string $cfopInterno): string
    {
        if (strlen($cfopInterno) === 4 && str_starts_with($cfopInterno, '5')) {
            return '6' . substr($cfopInterno, 1);
        }

        return '6102';
    }
}
