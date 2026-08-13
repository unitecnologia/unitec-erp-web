<?php

namespace App\Support\Erp\Balanca;

use App\Models\Grupo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Críticas de cadastro para "Produto de Balança".
 * PLU = codigo do produto (não id / não EAN-13).
 * Na balança o PLU tem até 6 dígitos; códigos menores são completados com zeros à esquerda.
 */
final class BalancaProductRules
{
    /** Campo PLU nos arquivos/balança (sempre 6; zeros à esquerda). */
    public const PLU_DIGITOS = 6;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string> campo => mensagem
     */
    public static function criticas(array $data): array
    {
        if (! filter_var($data['produto_pesado'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return [];
        }

        $messages = [];

        $grupoMsg = self::criticaGrupo((string) ($data['grupo'] ?? ''));
        if ($grupoMsg !== null) {
            $messages['grupo'] = $grupoMsg;
        }

        $codigoMsg = self::criticaCodigoPlu((string) ($data['codigo'] ?? ''));
        if ($codigoMsg !== null) {
            $messages['codigo'] = $codigoMsg;
        }

        $barrasMsg = self::criticaCodigoBarras(
            (string) ($data['codigo_barras'] ?? ''),
            (string) ($data['unidade'] ?? ''),
        );
        if ($barrasMsg !== null) {
            $messages['codigo_barras'] = $barrasMsg;
        }

        return $messages;
    }

    public static function maxDigitosPlu(): int
    {
        return self::PLU_DIGITOS;
    }

    public static function criticaGrupo(string $grupoNome): ?string
    {
        $grupoNome = trim($grupoNome);

        if ($grupoNome === '') {
            return 'Produto de Balança: informe um grupo marcado como balança.';
        }

        try {
            if (! Schema::hasTable('grupos') || ! Schema::hasColumn('grupos', 'balanca_marcado')) {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        $grupo = Grupo::query()
            ->whereRaw('UPPER(TRIM(nome)) = ?', [Str::upper($grupoNome)])
            ->first(['id', 'nome', 'balanca_marcado']);

        if (! $grupo) {
            return 'Produto de Balança: grupo "'.$grupoNome.'" não encontrado.';
        }

        if (! $grupo->balanca_marcado) {
            return 'Produto de Balança: o grupo "'.$grupo->nome.'" não está marcado como balança. '
                .'Marque "Bal. marcado" no cadastro do grupo.';
        }

        return null;
    }

    public static function criticaCodigoPlu(string $codigo, int $maxDigitos = self::PLU_DIGITOS): ?string
    {
        $digits = preg_replace('/\D/', '', $codigo) ?? '';
        $maxDigitos = self::PLU_DIGITOS;

        if ($digits === '') {
            return 'Produto de Balança: o código (PLU) é obrigatório.';
        }

        $len = strlen($digits);

        // EAN-13 colocado no código do produto em vez do PLU curto.
        if ($len === 13) {
            return 'Produto de Balança: o código (PLU) não pode ter 13 dígitos (isso é EAN). '
                .'Use até '.$maxDigitos.' dígitos (ex.: 40 → a balança grava 000040) e informe o EAN em Código de Barras.';
        }

        if ($len > $maxDigitos) {
            return 'Produto de Balança: o código pode ter no máximo '.$maxDigitos.' dígitos (atual: '.$len.'). '
                .'Códigos menores são completados com zeros à esquerda na balança (ex.: 40 → 000040).';
        }

        return null;
    }

    /**
     * Se informar código de barras em produto KG/UN de balança, exige EAN válido (8/12/13).
     */
    public static function criticaCodigoBarras(string $codigoBarras, string $unidade): ?string
    {
        $raw = trim($codigoBarras);

        if ($raw === '' || Str::upper($raw) === 'SEM GTIN') {
            return null;
        }

        $ean = preg_replace('/\D/', '', $raw) ?? '';
        $len = strlen($ean);

        // Sem unidade de peso/unidade explícita ainda valida formato básico.
        if (! in_array($len, [8, 12, 13], true)) {
            return 'Produto de Balança ('.$unidade.'): código de barras deve ter 8, 12 ou 13 dígitos. Atual: '.$len.'.';
        }

        // Caso típico de erro: EAN-13 incompleto / PLU colado no barras.
        $unidadeUp = Str::upper(trim($unidade));
        $isPesoOuUn = $unidadeUp === ''
            || str_contains($unidadeUp, 'KG')
            || in_array($unidadeUp, ['UN', 'UND', 'UNID', 'PC', 'G', 'GR', 'KILO', 'QUILO'], true);

        if ($isPesoOuUn && $len === 13 && str_starts_with($ean, BalancaEtiquetaLayout::DEFAULT_PREFIXO)) {
            // Etiqueta gerada pela balança (prefixo 2) não deve ser cadastrada como código de barras do produto.
            return 'Produto de Balança: não cadastre a etiqueta gerada pela balança (prefixo 2…) em Código de Barras. '
                .'Esse código é impresso na pesagem; use o EAN do fabricante ou deixe em branco.';
        }

        return null;
    }
}
