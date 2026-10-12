<?php

namespace App\Support\Erp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Visibilidade de produtos nos apps (Força de Vendas, Vendas Internas, Mesas).
 * Não altera disponibilidade no ERP/PDV.
 */
final class ProductAppCatalog
{
    /**
     * Produto elegível ao catálogo do app: ativo e com "Mostrar no App".
     *
     * @param  Builder<\App\Models\Product>  $query
     * @return Builder<\App\Models\Product>
     */
    public static function constrainVisivel(Builder $query): Builder
    {
        return $query
            ->where('ativo', true)
            ->where(function (Builder $q): void {
                $q->whereNull('mostrar_no_app')->orWhere('mostrar_no_app', true);
            });
    }

    /**
     * Pull de sync: catálogo visível; com $since também envia tombstones
     * (inativos/ocultos alterados) para o app remover do SQLite local.
     *
     * @param  Builder<\App\Models\Product>  $query
     * @return Builder<\App\Models\Product>
     */
    public static function constrainParaSync(Builder $query, ?Carbon $since = null): Builder
    {
        return $query->where(function (Builder $outer) use ($since): void {
            $outer->where(function (Builder $visivel): void {
                self::constrainVisivel($visivel);
            });

            if ($since !== null) {
                $outer->orWhere(function (Builder $tomb) use ($since): void {
                    $tomb->where('updated_at', '>', $since)
                        ->where(function (Builder $hidden): void {
                            $hidden->where('ativo', false)
                                ->orWhere('mostrar_no_app', false);
                        });
                });
            }
        });
    }
}
