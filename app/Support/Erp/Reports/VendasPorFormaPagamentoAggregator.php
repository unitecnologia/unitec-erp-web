<?php

namespace App\Support\Erp\Reports;

use App\Models\FormaPagamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpSchema;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Totais por forma real: mix (DINHEIRO + PIX) soma em cada forma, sem linha combinada.
 */
final class VendasPorFormaPagamentoAggregator
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>
     */
    public static function aggregate(
        CarbonInterface $de,
        CarbonInterface $ate,
        int|array|null $empresaScope = null,
        string $formaFiltro = '',
        bool $groupByEmpresa = false,
    ): array {
        if (! ErpSchema::hasTable((new Venda)->getTable())) {
            return [];
        }

        $hasEmpresa = ErpSchema::hasColumn((new Venda)->getTable(), 'empresa_id');
        $scope = $hasEmpresa ? $empresaScope : null;

        if (self::scopeBlocksAll($scope)) {
            return [];
        }

        $cadastro = self::formasCadastro();
        $aggregated = [];
        $deStr = $de->toDateString();
        $ateStr = $ate->toDateString();
        $groupEmpresa = $groupByEmpresa && $hasEmpresa;

        self::somarPagamentos($aggregated, $deStr, $ateStr, $scope, $groupEmpresa, $cadastro);
        self::somarCabecalhosSemPagamento($aggregated, $deStr, $ateStr, $scope, $groupEmpresa, $cadastro);

        $filtro = mb_strtoupper(trim($formaFiltro), 'UTF-8');
        $rows = array_values($aggregated);

        if ($filtro !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => mb_strtoupper((string) $row['forma'], 'UTF-8') === $filtro,
            ));
        }

        return $rows;
    }

    public static function formaBase(string $forma): string
    {
        $base = trim($forma);
        $pos = mb_strpos($base, ' (');

        if ($pos !== false) {
            $base = trim(mb_substr($base, 0, $pos));
        }

        return $base;
    }

    public static function isFormaMista(string $forma): bool
    {
        return str_contains($forma, ' / ');
    }

    /**
     * @param  Collection<int, FormaPagamento>|null  $cadastro
     */
    public static function rotuloFormaCadastro(string $formaBruta, ?Collection $cadastro = null): string
    {
        $base = trim($formaBruta);

        if ($base === '') {
            return 'NÃO INFORMADA';
        }

        $upper = mb_strtoupper($base, 'UTF-8');
        $cadastro ??= self::formasCadastro();

        foreach ($cadastro as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($descricao !== '' && $descricao === $upper) {
                return $descricao;
            }
        }

        return $upper;
    }

    /**
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    public static function codigoFormaCadastro(string $formaBruta, Collection $cadastro): int
    {
        $upper = mb_strtoupper(trim($formaBruta), 'UTF-8');

        foreach ($cadastro as $forma) {
            $descricao = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

            if ($descricao !== '' && $descricao === $upper) {
                return (int) ($forma->codigo ?: $forma->id);
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @return Collection<int, FormaPagamento>
     */
    public static function formasCadastro(): Collection
    {
        if (! ErpSchema::hasTable((new FormaPagamento)->getTable())) {
            return collect();
        }

        return FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descricao']);
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  int|list<int>|null  $empresaScope
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarPagamentos(
        array &$aggregated,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        if (! ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            || ! ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            return;
        }

        $query = PdvVendaPagamento::query()
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->join('vendas', 'vendas.id', '=', 'pdv_vendas.venda_id')
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereDate('vendas.data', '>=', $de)
            ->whereDate('vendas.data', '<=', $ate)
            ->whereNotNull('pdv_vendas.venda_id')
            ->where('pdv_venda_pagamentos.valor', '>', 0);

        ErpEmpresaScopeFilter::applyColumn($query, 'vendas', $empresaScope);

        $formaCol = self::sqlCol('pdv_venda_pagamentos', 'forma');
        $valorCol = self::sqlCol('pdv_venda_pagamentos', 'valor');
        $select = [
            DB::raw("{$formaCol} as forma"),
            DB::raw('COUNT(*) as qtd'),
            DB::raw("SUM({$valorCol}) as total"),
        ];

        if ($groupByEmpresa) {
            $select[] = 'vendas.empresa_id';
            $query->groupBy('vendas.empresa_id', 'pdv_venda_pagamentos.forma');
        } else {
            $query->groupBy('pdv_venda_pagamentos.forma');
        }

        foreach ($query->select($select)->get() as $row) {
            self::somar(
                $aggregated,
                (string) ($row->forma ?? ''),
                (float) $row->qtd,
                (float) $row->total,
                $groupByEmpresa ? (int) ($row->empresa_id ?? 0) : 0,
                $groupByEmpresa,
                $cadastro,
            );
        }
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  int|list<int>|null  $empresaScope
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somarCabecalhosSemPagamento(
        array &$aggregated,
        string $de,
        string $ate,
        int|array|null $empresaScope,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        $query = Venda::query()
            ->where('status', Venda::STATUS_FECHADO)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate);

        ErpEmpresaScopeFilter::applyColumn($query, (new Venda)->getTable(), $empresaScope);

        if (ErpSchema::hasTable((new PdvVendaPagamento)->getTable())
            && ErpSchema::hasTable((new PdvVenda)->getTable())
        ) {
            $query->whereNotExists(function ($exists): void {
                $exists->selectRaw('1')
                    ->from('pdv_vendas')
                    ->join('pdv_venda_pagamentos', 'pdv_venda_pagamentos.pdv_venda_id', '=', 'pdv_vendas.id')
                    ->whereColumn('pdv_vendas.venda_id', 'vendas.id');
            });
        }

        $formaCol = self::sqlCol((new Venda)->getTable(), 'forma_pagamento');
        $totalCol = self::sqlCol((new Venda)->getTable(), 'total');
        $select = [
            DB::raw("{$formaCol} as forma"),
            DB::raw('COUNT(*) as qtd'),
            DB::raw("SUM({$totalCol}) as total"),
        ];

        if ($groupByEmpresa) {
            $select[] = 'empresa_id';
            $query->groupBy('empresa_id', 'forma_pagamento');
        } else {
            $query->groupBy('forma_pagamento');
        }

        foreach ($query->select($select)->get() as $row) {
            self::somar(
                $aggregated,
                (string) ($row->forma ?? ''),
                (float) $row->qtd,
                (float) $row->total,
                $groupByEmpresa ? (int) ($row->empresa_id ?? 0) : 0,
                $groupByEmpresa,
                $cadastro,
            );
        }
    }

    /**
     * @param  array<string, array{empresa_id: int, forma: string, qtd: float, total: float, codigo: int}>  $aggregated
     * @param  Collection<int, FormaPagamento>  $cadastro
     */
    private static function somar(
        array &$aggregated,
        string $formaBruta,
        float $qtd,
        float $total,
        int $empresaId,
        bool $groupByEmpresa,
        Collection $cadastro,
    ): void {
        $base = self::formaBase($formaBruta);

        if (self::isFormaMista($base)) {
            return;
        }

        $label = self::rotuloFormaCadastro($base, $cadastro);
        $key = ($groupByEmpresa ? $empresaId.'|' : '').mb_strtoupper($label, 'UTF-8');
        $codigo = self::codigoFormaCadastro($base, $cadastro);

        if (! isset($aggregated[$key])) {
            $aggregated[$key] = [
                'empresa_id' => $groupByEmpresa ? $empresaId : 0,
                'forma' => $label,
                'qtd' => 0.0,
                'total' => 0.0,
                'codigo' => $codigo,
            ];
        }

        $aggregated[$key]['qtd'] += $qtd;
        $aggregated[$key]['total'] += $total;

        if ($codigo < $aggregated[$key]['codigo']) {
            $aggregated[$key]['codigo'] = $codigo;
            $aggregated[$key]['forma'] = $label;
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function scopeBlocksAll(int|array|null $empresaScope): bool
    {
        if (! is_array($empresaScope)) {
            return false;
        }

        $ids = array_values(array_filter(array_map('intval', $empresaScope), static fn (int $id): bool => $id > 0));

        return $ids === [];
    }

    private static function sqlCol(string $table, string $column): string
    {
        $table = str_replace('`', '``', DB::getTablePrefix().$table);
        $column = str_replace('`', '``', $column);

        return "`{$table}`.`{$column}`";
    }
}
