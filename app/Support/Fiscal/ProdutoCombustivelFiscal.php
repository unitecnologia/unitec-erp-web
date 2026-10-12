<?php

namespace App\Support\Fiscal;

use App\Models\Product;
use Unitec\FiscalEngine\Dto\CombustivelDto;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Monta e valida o grupo prod/comb a partir do cadastro do produto (is_combustivel).
 */
final class ProdutoCombustivelFiscal
{
    public const ANP_GLP = '210203001';

    /**
     * Retorna o DTO do grupo comb quando o produto é combustível; null caso contrário.
     * Lança se a flag estiver marcada e faltar dado obrigatório para emissão.
     */
    public static function fromProduct(
        ?Product $product,
        string $ufCons,
        string $descricaoItem = '',
    ): ?CombustivelDto {
        if ($product === null || ! (bool) ($product->is_combustivel ?? false)) {
            return null;
        }

        $label = self::labelProduto($product, $descricaoItem);
        self::assertReadyForEmission($product, $label);

        $anp = self::anpDigits((string) ($product->anp_code ?? ''));
        $desc = self::resolveDescAnp($product, $descricaoItem);
        $uf = strtoupper(trim($ufCons));
        if (strlen($uf) !== 2) {
            throw new FiscalEngineException(
                "Produto \"{$label}\": UF de consumo inválida para o grupo de combustível (comb)."
            );
        }

        $pGlp = null;
        $pGNn = null;
        $pGNi = null;

        if ($anp === self::ANP_GLP) {
            $pGlp = round((float) ($product->glp_pct ?? 0), 4);
            $pGNn = round((float) ($product->gnn_pct ?? 0), 4);
            $pGNi = round((float) ($product->gni_pct ?? 0), 4);
        }

        return new CombustivelDto(
            cProdANP: $anp,
            descANP: $desc,
            ufCons: $uf,
            pGLP: $pGlp,
            pGNn: $pGNn,
            pGNi: $pGNi,
        );
    }

    public static function assertReadyForEmission(Product $product, ?string $label = null): void
    {
        if (! (bool) ($product->is_combustivel ?? false)) {
            return;
        }

        $label = $label ?? self::labelProduto($product);
        $anp = self::anpDigits((string) ($product->anp_code ?? ''));

        if (strlen($anp) !== 9) {
            throw new FiscalEngineException(
                "Produto \"{$label}\" está marcado como Combustível, mas o Código ANP (9 dígitos) não foi informado na aba Combustível."
            );
        }

        $glp = round((float) ($product->glp_pct ?? 0), 2);
        $gnn = round((float) ($product->gnn_pct ?? 0), 2);
        $gni = round((float) ($product->gni_pct ?? 0), 2);
        $sum = round($glp + $gnn + $gni, 2);

        if ($anp === self::ANP_GLP) {
            if ($sum !== 100.0) {
                throw new FiscalEngineException(
                    "Produto \"{$label}\" (GLP / ANP ".self::ANP_GLP."): informe GLP%, GNn% e GNi% na aba Combustível com soma igual a 100%."
                );
            }

            return;
        }

        if ($sum > 0) {
            throw new FiscalEngineException(
                "Produto \"{$label}\": percentuais de GLP/GNn/GNi só são permitidos para o código ANP ".self::ANP_GLP.' (GLP). Remova-os ou altere o Código ANP.'
            );
        }
    }

    public static function anpDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    private static function resolveDescAnp(Product $product, string $descricaoItem): string
    {
        $desc = trim($descricaoItem);
        if ($desc === '') {
            $desc = trim((string) ($product->descricao ?? ''));
        }
        if ($desc === '') {
            $desc = 'COMBUSTIVEL';
        }

        $desc = preg_replace('/\s+/', ' ', $desc) ?? $desc;
        $desc = mb_substr($desc, 0, 95, 'UTF-8');

        if (mb_strlen($desc, 'UTF-8') < 2) {
            $desc = 'COMBUSTIVEL';
        }

        return $desc;
    }

    private static function labelProduto(Product $product, string $fallback = ''): string
    {
        $nome = trim((string) ($product->descricao ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        $id = (int) ($product->id ?? 0);

        return $id > 0 ? "ID {$id}" : 'sem identificação';
    }
}
