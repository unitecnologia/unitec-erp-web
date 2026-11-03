<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\PdvVenda;
use App\Models\Venda;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ErpDashboardRecentSales
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array<string, string>>
     */
    public static function list(int $limit = 6, int|array|null $empresaScope = null): array
    {
        try {
            $rows = static::rows($limit, $empresaScope);

            return $rows->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return Collection<int, array<string, string>>
     */
    private static function rows(int $limit, int|array|null $empresaScope = null): Collection
    {
        $items = collect();
        $fetchLimit = max($limit * 3, $limit);

        if (ErpSchema::hasTable((new PdvVenda)->getTable())) {
            foreach (static::fetchPdvRows($fetchLimit, $empresaScope) as $venda) {
                $momento = ErpTimezone::toLocal($venda->fechado_em ?? $venda->created_at);

                $items->push([
                    'id' => (string) $venda->numero,
                    'cliente' => $venda->person_nome ?: 'Consumidor',
                    'valor' => 'R$ '.ErpMoney::formatBr((float) $venda->total),
                    'data' => $momento->format('d/m H:i'),
                    'status' => 'PDV',
                    'sort_at' => $momento->timestamp,
                ]);
            }
        }

        if (ErpSchema::hasTable((new Venda)->getTable())) {
            $vendasQuery = Venda::query()
                ->select([
                    (new Venda)->getTable().'.id',
                    (new Venda)->getTable().'.numero',
                    (new Venda)->getTable().'.total',
                    (new Venda)->getTable().'.data',
                    (new Venda)->getTable().'.hora',
                    (new Venda)->getTable().'.status',
                    (new Venda)->getTable().'.cliente_id',
                ])
                ->with('cliente:id,nome_razao')
                ->whereNotIn('status', [Venda::STATUS_CANCELADO])
                ->where('tipo', '!=', Venda::TIPO_CUPOM)
                ->orderByDesc('data')
                ->orderByDesc('hora');

            ErpEmpresaScopeFilter::applyColumn($vendasQuery, (new Venda)->getTable(), $empresaScope);

            $vendas = $vendasQuery->limit($fetchLimit)->get();

            foreach ($vendas as $venda) {
                $data = $venda->data ? Carbon::parse($venda->data) : Carbon::today();
                $hora = filled($venda->hora) ? (string) $venda->hora : '00:00:00';
                $momento = ErpTimezone::toLocal($data->format('Y-m-d').' '.$hora);

                $items->push([
                    'id' => (string) $venda->numero,
                    'cliente' => $venda->cliente?->nome_razao ?? 'Cliente',
                    'valor' => 'R$ '.ErpMoney::formatBr($venda->total),
                    'data' => $momento->format('d/m H:i'),
                    'status' => Venda::statusLabels()[$venda->status] ?? ucfirst((string) $venda->status),
                    'sort_at' => $momento->timestamp,
                ]);
            }
        }

        return $items
            ->sortByDesc('sort_at')
            ->take($limit)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'cliente' => $row['cliente'],
                'valor' => $row['valor'],
                'data' => $row['data'],
                'status' => $row['status'],
            ])
            ->values();
    }

    /**
     * PDV recentes: join explícito na sessão (mesmos filtros comercial + empresa),
     * sem carregar colunas desnecessárias.
     *
     * @param  int|list<int>|null  $empresaScope
     * @return list<object>
     */
    private static function fetchPdvRows(int $fetchLimit, int|array|null $empresaScope): array
    {
        $v = DB::getTablePrefix().(new PdvVenda)->getTable();
        $s = DB::getTablePrefix().'pdv_caixa_sessoes';
        $p = DB::getTablePrefix().'people';
        $pdvTable = (new PdvVenda)->getTable();
        $hasPeople = ErpSchema::hasTable('people');

        $select = 'v.id, v.numero, v.total, v.fechado_em, v.created_at, v.person_id'
            .($hasPeople ? ', p.nome_razao as person_nome' : ', NULL as person_nome');

        $origemExcluidas = [
            PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
            PdvVenda::ORIGEM_OS_NFCE_PECAS,
        ];
        $origemPh = implode(',', array_fill(0, count($origemExcluidas), '?'));
        $bindings = $origemExcluidas;

        $comercial = "(v.origem IS NULL OR v.origem NOT IN ({$origemPh}))";
        $joinPerson = $hasPeople ? "LEFT JOIN {$p} p ON p.id = v.person_id" : '';

        if ($empresaScope !== null && ErpSchema::hasColumnActual($pdvTable, 'empresa_id')) {
            $ids = is_array($empresaScope)
                ? array_values(array_filter(array_map('intval', $empresaScope)))
                : (((int) $empresaScope > 0) ? [(int) $empresaScope] : []);
            if ($ids === []) {
                return [];
            }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT {$select} FROM {$v} v {$joinPerson} "
                ."WHERE {$comercial} AND v.situacao != 'C' AND v.empresa_id IN ({$ph}) "
                .'ORDER BY v.fechado_em DESC, v.created_at DESC LIMIT '.(int) $fetchLimit;
            foreach ($ids as $id) {
                $bindings[] = $id;
            }

            return DB::select($sql, $bindings);
        }

        if ($empresaScope !== null && ErpSchema::hasTable('pdv_caixa_sessoes') && ErpSchema::hasColumnActual('pdv_caixa_sessoes', 'empresa_id')) {
            $ids = is_array($empresaScope)
                ? array_values(array_filter(array_map('intval', $empresaScope)))
                : (((int) $empresaScope > 0) ? [(int) $empresaScope] : []);
            if ($ids === []) {
                return [];
            }
            $ph = implode(',', array_fill(0, count($ids), '?'));
            // Parte da sessão da empresa (índice empresa_id) e só então ordena vendas.
            $sql = "SELECT {$select} FROM {$s} s "
                ."STRAIGHT_JOIN {$v} v ON v.pdv_caixa_sessao_id = s.id "
                ."{$joinPerson} "
                ."WHERE s.empresa_id IN ({$ph}) AND {$comercial} AND v.situacao != 'C' "
                .'ORDER BY v.fechado_em DESC, v.created_at DESC LIMIT '.(int) $fetchLimit;
            $bindings = array_merge($ids, $origemExcluidas);

            return DB::select($sql, $bindings);
        }

        $sql = "SELECT {$select} FROM {$v} v {$joinPerson} "
            ."WHERE {$comercial} AND v.situacao != 'C' "
            .'ORDER BY v.fechado_em DESC, v.created_at DESC LIMIT '.(int) $fetchLimit;

        return DB::select($sql, $bindings);
    }
}
