<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Escopo multiempresa da NFC-e: tela, comandos, relatório e impressões.
 * Empresa da NFC-e = pdv_venda_nfce.empresa_id; legado sem empresa usa a sessão de caixa da venda.
 * Sem empresa ativa não há escopo (nenhum registro).
 */
final class NfceEmpresaEscopo
{
    public static function empresaIdAtiva(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId ? (int) $empresaId : null;
    }

    /**
     * @param  Builder<PdvVendaNfce>  $query
     * @return Builder<PdvVendaNfce>
     */
    public static function aplicar(Builder $query, ?int $empresaId): Builder
    {
        if (! $empresaId) {
            return $query->whereRaw('1 = 0');
        }

        $tabela = $query->getModel()->getTable();

        return $query->where(function (Builder $outer) use ($empresaId, $tabela): void {
            $outer->where($tabela.'.empresa_id', $empresaId)
                ->orWhere(function (Builder $inner) use ($empresaId, $tabela): void {
                    $inner->whereNull($tabela.'.empresa_id')
                        ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                            ->where('empresa_id', $empresaId));
                });
        });
    }

    /**
     * @param  list<string>  $with
     */
    public static function find(?int $id, ?int $empresaId, array $with = []): ?PdvVendaNfce
    {
        if (! $id || $id <= 0) {
            return null;
        }

        return self::aplicar(PdvVendaNfce::query()->with($with), $empresaId)
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    public static function filtrarIds(array $ids, ?int $empresaId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $permitidos = self::aplicar(PdvVendaNfce::query(), $empresaId)
            ->whereKey($ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_filter($ids, fn (int $id): bool => in_array($id, $permitidos, true)));
    }

    /**
     * Empresa dona da venda PDV: sessão de caixa; sem sessão (regularização), a NFC-e.
     */
    public static function empresaIdDaVenda(PdvVenda $venda): ?int
    {
        $venda->loadMissing(['sessao', 'nfce']);

        if ($venda->sessao && filled($venda->sessao->empresa_id)) {
            return (int) $venda->sessao->empresa_id;
        }

        if ($venda->nfce && filled($venda->nfce->empresa_id)) {
            return (int) $venda->nfce->empresa_id;
        }

        return null;
    }

    public static function abortSeVendaForaDaEmpresa(PdvVenda $venda, ?int $empresaId): void
    {
        $dona = self::empresaIdDaVenda($venda);

        abort_unless($empresaId && $dona !== null && $dona === $empresaId, 403);
    }
}
