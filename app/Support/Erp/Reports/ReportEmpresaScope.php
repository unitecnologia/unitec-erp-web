<?php

namespace App\Support\Erp\Reports;

use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class ReportEmpresaScope
{
    public const VALUE_ATUAL = 'atual';

    public const VALUE_GRUPO = 'grupo';

    /**
     * Mostra o filtro só quando o usuário tem 2+ empresas liberadas.
     */
    public static function shouldShowFilter(): bool
    {
        return count(static::accessibleIds()) >= 2;
    }

    /**
     * Opções: Empresa Atual, Todas as Empresas do Grupo, e cada empresa liberada.
     *
     * @return array<string, string>
     */
    public static function filterOptions(): array
    {
        if (! static::shouldShowFilter()) {
            return [];
        }

        $empresas = static::empresasLiberadas();

        if ($empresas === []) {
            return [];
        }

        $options = [
            self::VALUE_ATUAL => 'Empresa Atual',
            self::VALUE_GRUPO => 'Todas as Empresas do Grupo',
        ];

        foreach ($empresas as $empresa) {
            $options[(string) $empresa->id] = static::labelEmpresa($empresa);
        }

        return $options;
    }

    /**
     * @return array{key: string, label: string, type: string, options: array<string, string>}|null
     */
    public static function filterField(): ?array
    {
        $options = static::filterOptions();

        if ($options === []) {
            return null;
        }

        return [
            'key' => 'empresa',
            'label' => 'Empresa',
            'type' => 'select',
            'options' => $options,
        ];
    }

    /**
     * IDs finais para whereIn — sempre ⊆ accessibleEmpresaIds().
     *
     * @return list<int>
     */
    public static function resolveIds(Request $request): array
    {
        $currentId = ErpContext::currentEmpresaId();
        $permitidoIds = static::accessibleIds();

        if ($currentId && $permitidoIds === []) {
            return [$currentId];
        }

        if (! static::shouldShowFilter()) {
            return $currentId ? [$currentId] : $permitidoIds;
        }

        if ($permitidoIds === []) {
            return $currentId ? [$currentId] : [];
        }

        $raw = trim((string) $request->query('empresa', self::VALUE_ATUAL));

        if ($raw === '' || $raw === self::VALUE_ATUAL) {
            if ($currentId && in_array($currentId, $permitidoIds, true)) {
                return [$currentId];
            }

            return [$permitidoIds[0]];
        }

        if ($raw === self::VALUE_GRUPO) {
            return $permitidoIds;
        }

        $requestedId = (int) $raw;

        if ($requestedId > 0 && in_array($requestedId, $permitidoIds, true)) {
            return [$requestedId];
        }

        if ($currentId && in_array($currentId, $permitidoIds, true)) {
            return [$currentId];
        }

        return [$permitidoIds[0]];
    }

    public static function isMultiEmpresa(Request $request): bool
    {
        return count(static::resolveIds($request)) > 1;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyToQuery(Builder $query, Request $request, string $column = 'empresa_id'): void
    {
        $ids = static::resolveIds($request);

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (count($ids) === 1) {
            $query->where($column, $ids[0]);

            return;
        }

        $query->whereIn($column, $ids);
    }

    public static function applyIfColumnExists(Builder $query, Request $request, string $table, string $column = 'empresa_id'): void
    {
        if (! Schema::hasColumn($table, $column)) {
            return;
        }

        static::applyToQuery($query, $request, $table.'.'.$column);
    }

    /**
     * Como applyToQuery, mas em escopo de 1 empresa inclui títulos legados (empresa_id null).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyToQueryAllowingNullForSingle(Builder $query, Request $request, string $column = 'empresa_id'): void
    {
        $ids = static::resolveIds($request);

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (count($ids) === 1) {
            $id = $ids[0];
            $query->where(function (Builder $inner) use ($column, $id): void {
                $inner->where($column, $id)->orWhereNull($column);
            });

            return;
        }

        $query->whereIn($column, $ids);
    }

    /**
     * @return array<int, string>
     */
    public static function labelsById(): array
    {
        return Empresa::query()
            ->get(['id', 'fantasia', 'nome', 'razao_social'])
            ->mapWithKeys(fn (Empresa $e): array => [(int) $e->id => static::labelEmpresa($e)])
            ->all();
    }

    /**
     * Texto para o cabeçalho do relatório.
     */
    public static function summaryLabel(Request $request): string
    {
        if (! static::shouldShowFilter()) {
            $empresa = ErpContext::currentEmpresa();

            return 'EMPRESA: '.mb_strtoupper(static::labelEmpresa($empresa), 'UTF-8');
        }

        $raw = trim((string) $request->query('empresa', self::VALUE_ATUAL));
        $ids = static::resolveIds($request);

        if ($raw === self::VALUE_GRUPO || count($ids) > 1) {
            $count = count($ids);

            return 'EMPRESA: TODAS AS EMPRESAS DO GRUPO'
                .' ('.$count.' '.($count === 1 ? 'EMPRESA' : 'EMPRESAS').')';
        }

        $id = $ids[0] ?? ErpContext::currentEmpresaId();
        $empresa = $id ? Empresa::query()->find($id) : ErpContext::currentEmpresa();

        return 'EMPRESA: '.mb_strtoupper(static::labelEmpresa($empresa), 'UTF-8');
    }

    /**
     * Valor selecionado válido para o form de filtros.
     */
    public static function selectedValue(Request $request): string
    {
        if (! static::shouldShowFilter()) {
            return self::VALUE_ATUAL;
        }

        $raw = trim((string) $request->query('empresa', self::VALUE_ATUAL));
        $options = static::filterOptions();

        if ($raw !== '' && array_key_exists($raw, $options)) {
            return $raw;
        }

        return self::VALUE_ATUAL;
    }

    /**
     * @return list<int>
     */
    public static function accessibleIds(): array
    {
        return array_values(array_filter(
            array_map('intval', ErpContext::accessibleEmpresaIds()),
            static fn (int $id): bool => $id > 0,
        ));
    }

    /**
     * Empresas ativas liberadas ao usuário (ordem estável).
     *
     * @return list<Empresa>
     */
    public static function empresasLiberadas(): array
    {
        $accessible = static::accessibleIds();

        if ($accessible === []) {
            return [];
        }

        return Empresa::query()
            ->where('ativo', true)
            ->whereIn('id', $accessible)
            ->orderBy('codigo')
            ->orderBy('nome')
            ->get()
            ->all();
    }

    public static function labelEmpresa(?Empresa $empresa): string
    {
        if (! $empresa) {
            return '—';
        }

        $nome = trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social));

        return $nome !== '' ? $nome : ('Empresa #'.$empresa->id);
    }
}
