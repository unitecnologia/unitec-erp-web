<?php

namespace App\Support\Erp;

use App\Models\PdvVenda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class ErpEmpresaScopeFilter
{
    /**
     * @param  int|list<int>|null  $scope
     */
    public static function applyColumn(Builder $query, string $table, int|array|null $scope): void
    {
        if ($scope === null) {
            return;
        }

        if (! Schema::hasColumn($table, 'empresa_id')) {
            return;
        }

        if (is_array($scope)) {
            $ids = array_values(array_filter(array_map('intval', $scope)));

            if ($ids === []) {
                return;
            }

            $query->whereIn($table.'.empresa_id', $ids);

            return;
        }

        if ($scope <= 0) {
            return;
        }

        $query->where($table.'.empresa_id', $scope);
    }

    /**
     * @param  int|list<int>|null  $scope
     */
    public static function applyPdvSessao(Builder $query, int|array|null $scope): void
    {
        if ($scope === null) {
            return;
        }

        $pdvTable = (new PdvVenda)->getTable();

        if (Schema::hasColumn($pdvTable, 'empresa_id')) {
            if (is_array($scope)) {
                $ids = array_values(array_filter(array_map('intval', $scope)));

                if ($ids !== []) {
                    $query->whereIn($pdvTable.'.empresa_id', $ids);
                }
            } elseif ($scope > 0) {
                $query->where($pdvTable.'.empresa_id', $scope);
            }

            return;
        }

        if (! Schema::hasTable('pdv_caixa_sessoes') || ! Schema::hasColumn('pdv_caixa_sessoes', 'empresa_id')) {
            return;
        }

        $query->whereHas('sessao', function (Builder $sessao) use ($scope): void {
            if (is_array($scope)) {
                $ids = array_values(array_filter(array_map('intval', $scope)));

                if ($ids !== []) {
                    $sessao->whereIn('empresa_id', $ids);
                }
            } elseif ($scope > 0) {
                $sessao->where('empresa_id', $scope);
            }
        });
    }

    /**
     * Filtro transitório em vendas quando a coluna empresa_id ainda não existir.
     */
    public static function applyVendaOrigemFallback(Builder $query, int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $query->where(function (Builder $scoped) use ($empresaId): void {
            $scoped->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao->where('empresa_id', $empresaId))
                ->orWhereHas('forcaVendasOrder', fn (Builder $order): Builder => $order->where('empresa_id', $empresaId));
        });
    }
}
