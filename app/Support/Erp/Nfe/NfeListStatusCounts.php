<?php

namespace App\Support\Erp\Nfe;

use App\Models\Nfe;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contadores das abas NF-e: 1× GROUP BY status (sem hidratar linhas / sem XML).
 */
final class NfeListStatusCounts
{
    /**
     * @param  Builder<Nfe>  $querySemStatus  filtros de empresa/período/busca, sem status da aba
     * @return array<string, string> chave da aba => "(N)"
     */
    public static function paraAbas(Builder $querySemStatus): array
    {
        $tabs = [
            'todas' => 0,
            Nfe::STATUS_ABERTA => 0,
            Nfe::STATUS_TRANSMITIDA => 0,
            Nfe::STATUS_CANCELADA => 0,
            Nfe::STATUS_DUPLICIDADE => 0,
            Nfe::STATUS_INUTILIZADA => 0,
            Nfe::STATUS_DENEGADA => 0,
            Nfe::STATUS_CONTINGENCIA => 0,
        ];

        $table = $querySemStatus->getModel()->getTable();
        $grammar = $querySemStatus->getConnection()->getQueryGrammar();
        // wrapTable aplica o prefixo (ex.: unitec_).
        $statusCol = $grammar->wrapTable($table).'.'.$grammar->wrap('status');

        $rows = (clone $querySemStatus)->toBase()
            ->cloneWithout(['columns', 'orders', 'groups', 'havings', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw($statusCol.' as status_key, COUNT(*) as qtd')
            ->groupByRaw($statusCol)
            ->get();

        $total = 0;

        foreach ($rows as $row) {
            $key = (string) ($row->status_key ?? '');
            $qtd = (int) ($row->qtd ?? 0);
            $total += $qtd;

            if (array_key_exists($key, $tabs)) {
                $tabs[$key] = $qtd;
            }
        }

        $tabs['todas'] = $total;

        $out = [];
        foreach ($tabs as $key => $qtd) {
            $out[$key] = '('.self::formatar($qtd).')';
        }

        return $out;
    }

    private static function formatar(int $qtd): string
    {
        return number_format($qtd, 0, ',', '.');
    }
}
