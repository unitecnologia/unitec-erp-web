<?php

namespace App\Support\Erp\Os;

use App\Models\OrdemServico;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilderContract;
use Illuminate\Support\Facades\Auth;

/**
 * Vínculo exato entre a OS e os lançamentos gravados por OsFaturamentoService:
 * documento "OS-123" / "OS-123-1" e histórico "OS 123 (FORMA)".
 *
 * Prefixo solto (LIKE 'OS-12%') casaria OS-120, OS-1234 etc. e mostraria pagamentos de outra OS.
 */
final class OrdemServicoFinanceiroVinculo
{
    public static function documentoBase(OrdemServico $ordem): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $ordem->numero) ?? '';

        return $digits !== '' ? 'OS-'.$digits : null;
    }

    public static function empresaId(OrdemServico $ordem): ?int
    {
        if ($ordem->empresa_id) {
            return (int) $ordem->empresa_id;
        }

        $sessao = session('erp_empresa_id', Auth::user()?->empresa_id);

        return filled($sessao) ? (int) $sessao : null;
    }

    /**
     * Restringe a query (contas a receber ou livro caixa) aos lançamentos da OS e da empresa.
     * OS sem número não casa com nada.
     */
    public static function aplicar(QueryBuilderContract $query, OrdemServico $ordem, bool $incluirParcelas = true): QueryBuilderContract
    {
        $documento = self::documentoBase($ordem);

        if ($documento === null) {
            return $query->whereRaw('1 = 0');
        }

        $numero = trim((string) $ordem->numero);
        $digits = substr($documento, 3);
        $empresaId = self::empresaId($ordem);

        return $query
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->where(function ($q) use ($documento, $numero, $digits, $incluirParcelas): void {
                $q->where('documento', $documento);

                if ($incluirParcelas) {
                    $q->orWhere('documento', 'like', self::escapeLike($documento).'-%');
                }

                foreach (array_unique([$numero, $digits]) as $n) {
                    $q->orWhere('historico', 'OS '.$n)
                        ->orWhere('historico', 'like', 'OS '.self::escapeLike($n).' %');
                }
            });
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
