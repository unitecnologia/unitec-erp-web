<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contadores das abas NFC-e: 1× GROUP BY status sobre a query otimizada (STRAIGHT_JOIN / período).
 * Agrega status → abas (ex.: autorizada+simulada → Transmitidos).
 */
final class NfceListStatusCounts
{
    /**
     * @param  Builder<PdvVendaNfce>  $querySemAba  empresa/período/busca/chave, sem filtro de aba
     * @return array<string, string> chave da aba => "(N)"
     */
    public static function paraAbas(Builder $querySemAba): array
    {
        $tabs = [];
        foreach (array_keys(PdvVendaNfce::tabLabels()) as $tab) {
            $tabs[$tab] = 0;
        }

        $table = $querySemAba->getModel()->getTable();
        $grammar = $querySemAba->getConnection()->getQueryGrammar();
        // wrapTable aplica o prefixo (ex.: unitec_) — obrigatório com STRAIGHT_JOIN.
        $statusCol = $grammar->wrapTable($table).'.'.$grammar->wrap('status');

        $rows = (clone $querySemAba)->toBase()
            ->cloneWithout(['columns', 'orders', 'groups', 'havings', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw($statusCol.' as status_key, COUNT(*) as qtd')
            ->groupByRaw($statusCol)
            ->get();

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[(string) ($row->status_key ?? '')] = (int) ($row->qtd ?? 0);
        }

        foreach ($tabs as $tab => $_) {
            $qtd = 0;
            foreach (PdvVendaNfce::statusesForTab($tab) as $status) {
                $qtd += (int) ($byStatus[$status] ?? 0);
            }
            $tabs[$tab] = $qtd;
        }

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
