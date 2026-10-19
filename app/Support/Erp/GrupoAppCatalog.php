<?php

namespace App\Support\Erp;

use App\Models\Grupo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Visibilidade de grupos nos apps (Força de Vendas, Mesas, etc.).
 * Fonte única: cadastro de Grupos com ativo + Mostrar no App.
 */
final class GrupoAppCatalog
{
    /**
     * @param  Builder<\App\Models\Grupo>  $query
     * @return Builder<\App\Models\Grupo>
     */
    public static function constrainVisivel(Builder $query): Builder
    {
        $query->where('ativo', true);

        if (Schema::hasColumn((new Grupo)->getTable(), 'mostrar_no_app')) {
            $query->where('mostrar_no_app', true);
        }

        return $query;
    }

    /**
     * @return Builder<\App\Models\Grupo>|null
     */
    public static function queryVisivel(): ?Builder
    {
        if (! Schema::hasTable('grupos')) {
            return null;
        }

        return self::constrainVisivel(Grupo::query());
    }

    /**
     * Payload de sync (Força / Mesas): mesmos campos e filtros.
     *
     * @return list<array{id: int, nome: string, ativo: bool, mostrar_no_app: bool, updated_at: string|null}>
     */
    public static function paraSync(): array
    {
        $query = self::queryVisivel();

        if ($query === null) {
            return [];
        }

        return $query
            ->orderBy('nome')
            ->get()
            ->map(static fn (Grupo $g): array => [
                'id' => (int) $g->id,
                'nome' => (string) $g->nome,
                'ativo' => (bool) $g->ativo,
                'mostrar_no_app' => (bool) ($g->mostrar_no_app ?? false),
                'updated_at' => optional($g->updated_at)?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function nomesVisiveis(): array
    {
        $query = self::queryVisivel();

        if ($query === null) {
            return [];
        }

        return $query
            ->orderBy('nome')
            ->pluck('nome')
            ->map(static fn ($n): string => trim((string) $n))
            ->filter(static fn (string $n): bool => $n !== '')
            ->values()
            ->all();
    }
}
