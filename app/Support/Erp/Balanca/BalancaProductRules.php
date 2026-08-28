<?php

namespace App\Support\Erp\Balanca;

use App\Models\Grupo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Críticas de cadastro para "Produto de Balança".
 * Quem vai à balança: flag produto_pesado.
 * Código no arquivo: codigo_barras (PLU curto ou EAN) — formato livre; só obriga preenchimento.
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

        $barrasMsg = self::criticaCodigoBarras((string) ($data['codigo_barras'] ?? ''));
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

    /**
     * Código de barras obrigatório para produto de balança; formato livre.
     */
    public static function criticaCodigoBarras(string $codigoBarras, string $unidade = ''): ?string
    {
        $raw = trim($codigoBarras);

        if ($raw === '' || Str::upper($raw) === 'SEM GTIN') {
            return 'Produto de Balança: informe o código de barras (PLU ou EAN do fabricante).';
        }

        return null;
    }
}
