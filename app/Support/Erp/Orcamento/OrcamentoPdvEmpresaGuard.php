<?php

namespace App\Support\Erp\Orcamento;

use App\Models\Empresa;
use App\Models\Orcamento;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Isolamento por empresa no F3 (Importar Orçamento) do PDV.
 *
 * Origens identificáveis sem alterar schema:
 * - Vendas Internas: vendas_internas_orders.empresa_id
 * - Força de Vendas: forca_vendas_orders.empresa_id
 *
 * Orçamentos nativos do ERP (sem esses vínculos) não têm empresa_id na tabela
 * orcamentos. Em instalação multiempresa eles ficam fora do F3; em instalação
 * com uma única empresa permanecem importáveis (compatível com o legado).
 */
final class OrcamentoPdvEmpresaGuard
{
    /**
     * Empresa da sessão autenticada (nunca de input do navegador).
     */
    public static function empresaIdSessao(): ?int
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        return $empresaId > 0 ? $empresaId : null;
    }

    public static function instalacaoMultiempresa(): bool
    {
        return Empresa::query()->count() > 1;
    }

    /**
     * @param  Builder<Orcamento>  $query
     * @return Builder<Orcamento>
     */
    public static function aplicarEscopoEmpresa(Builder $query, ?int $empresaId = null): Builder
    {
        $empresaId = $empresaId ?? self::empresaIdSessao();

        if ($empresaId === null || $empresaId <= 0) {
            return $query->whereRaw('0 = 1');
        }

        $permitirNativos = ! self::instalacaoMultiempresa();

        return $query->where(function (Builder $outer) use ($empresaId, $permitirNativos): void {
            $outer->where(function (Builder $comVinculo) use ($empresaId): void {
                $comVinculo
                    ->where(function (Builder $daEmpresa) use ($empresaId): void {
                        $daEmpresa
                            ->whereHas(
                                'vendasInternasOrder',
                                fn (Builder $q) => $q->where('empresa_id', $empresaId),
                            )
                            ->orWhereHas(
                                'forcaVendasOrder',
                                fn (Builder $q) => $q->where('empresa_id', $empresaId),
                            );
                    })
                    ->whereDoesntHave(
                        'vendasInternasOrder',
                        fn (Builder $q) => $q->whereNotNull('empresa_id')->where('empresa_id', '!=', $empresaId),
                    )
                    ->whereDoesntHave(
                        'forcaVendasOrder',
                        fn (Builder $q) => $q->whereNotNull('empresa_id')->where('empresa_id', '!=', $empresaId),
                    );
            });

            if ($permitirNativos) {
                $outer->orWhere(function (Builder $nativo): void {
                    $nativo
                        ->whereDoesntHave('vendasInternasOrder')
                        ->whereDoesntHave('forcaVendasOrder');
                });
            }
        });
    }

    /**
     * IDs de empresa encontrados nos vínculos VI/FV do orçamento.
     *
     * @return list<int>
     */
    public static function empresaIdsVinculadas(Orcamento $orcamento): array
    {
        $orcamento->loadMissing(['vendasInternasOrder:id,orcamento_id,empresa_id', 'forcaVendasOrder:id,orcamento_id,empresa_id']);

        $ids = [];

        $viEmpresaId = (int) ($orcamento->vendasInternasOrder?->empresa_id ?? 0);
        if ($viEmpresaId > 0) {
            $ids[] = $viEmpresaId;
        }

        $fvEmpresaId = (int) ($orcamento->forcaVendasOrder?->empresa_id ?? 0);
        if ($fvEmpresaId > 0) {
            $ids[] = $fvEmpresaId;
        }

        return array_values(array_unique($ids));
    }

    /**
     * @throws \RuntimeException
     */
    public static function assertImportavelParaEmpresa(Orcamento $orcamento, ?int $empresaId = null): void
    {
        $empresaId = $empresaId ?? self::empresaIdSessao();

        if ($empresaId === null || $empresaId <= 0) {
            throw new \RuntimeException(
                'Nenhuma empresa selecionada na sessão. Não é possível importar o orçamento.'
            );
        }

        $vinculadas = self::empresaIdsVinculadas($orcamento);

        if (count($vinculadas) > 1) {
            throw new \RuntimeException(
                'Este orçamento possui vínculos conflitantes de empresa e não pode ser importado.'
            );
        }

        if (count($vinculadas) === 1) {
            if ($vinculadas[0] !== $empresaId) {
                throw new \RuntimeException(
                    'Este orçamento pertence a outra empresa e não pode ser importado.'
                );
            }

            return;
        }

        // Sem vínculo VI/FV: nativo do ERP (sem empresa_id na tabela orcamentos).
        if (self::instalacaoMultiempresa()) {
            throw new \RuntimeException(
                'Este orçamento não possui empresa identificável e não pode ser importado nesta instalação multiempresa.'
            );
        }
    }

    public static function motivoBloqueioEmpresa(Orcamento $orcamento, ?int $empresaId = null): ?string
    {
        try {
            self::assertImportavelParaEmpresa($orcamento, $empresaId);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }
}
