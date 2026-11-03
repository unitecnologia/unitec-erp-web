<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nº DAV na listagem NFC-e:
 * - emissão UNI: pdv_vendas.numero (comportamento histórico);
 * - histórico SCV (operacao=migracao_scv_hist): pedidos.numero via mapa DAV;
 * - SCV sem DAV na origem: "—".
 */
final class NfceListDavNumero
{
    public const OPERACAO_SCV_HIST = 'migracao_scv_hist';

    /** @var array<int, string>|null pdv_venda_id => pedidos.numero */
    private static ?array $cachePorPdvVendaId = null;

    /**
     * Pré-carrega números comerciais do SCV para a página (evita N+1).
     *
     * @param  iterable<int, PdvVendaNfce>  $records
     * @return array<int, string> pdv_venda_id => pedidos.numero
     */
    public static function aquecer(iterable $records): array
    {
        $pdvIds = [];

        foreach ($records as $record) {
            if (! $record instanceof PdvVendaNfce) {
                continue;
            }

            if ((string) $record->operacao !== self::OPERACAO_SCV_HIST) {
                continue;
            }

            $pdvId = (int) ($record->pdv_venda_id ?? 0);

            if ($pdvId > 0) {
                $pdvIds[$pdvId] = $pdvId;
            }
        }

        if ($pdvIds === []) {
            self::$cachePorPdvVendaId = self::$cachePorPdvVendaId ?? [];

            return [];
        }

        $carregados = self::buscarPorPdvVendaIds(array_values($pdvIds));
        self::$cachePorPdvVendaId = (self::$cachePorPdvVendaId ?? []) + $carregados;

        return $carregados;
    }

    /**
     * @param  list<int>  $pdvVendaIds
     * @return array<int, string>
     */
    public static function buscarPorPdvVendaIds(array $pdvVendaIds): array
    {
        $pdvVendaIds = array_values(array_unique(array_filter(
            array_map('intval', $pdvVendaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($pdvVendaIds === [] || ! self::mapaScvDisponivel()) {
            return [];
        }

        $rows = DB::table('migra_scv_id_map as m_dav')
            ->join('migra_scv_id_map as m_ped', function ($join): void {
                $join->on('m_ped.origem_id', '=', 'm_dav.origem_id')
                    ->where('m_ped.origem_tabela', '=', 'DAV')
                    ->where('m_ped.destino_tabela', '=', 'pedidos');
            })
            ->join('pedidos as ped', 'ped.id', '=', 'm_ped.destino_id')
            ->where('m_dav.origem_tabela', 'DAV')
            ->where('m_dav.destino_tabela', 'pdv_vendas')
            ->whereIn('m_dav.destino_id', $pdvVendaIds)
            ->whereNotNull('ped.numero')
            ->where('ped.numero', '<>', '')
            ->select([
                'm_dav.destino_id as pdv_venda_id',
                'ped.numero as dav_numero',
            ])
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $pdvId = (int) $row->pdv_venda_id;
            $numero = trim((string) $row->dav_numero);

            if ($pdvId > 0 && $numero !== '') {
                $map[$pdvId] = $numero;
            }
        }

        return $map;
    }

    /**
     * Texto da célula Nº DAV.
     *
     * @param  array<int, string>|null  $mapPorPdvVendaId  mapa opcional já aquecido
     */
    public static function texto(PdvVendaNfce $record, ?array $mapPorPdvVendaId = null): string
    {
        if ((string) $record->operacao === self::OPERACAO_SCV_HIST) {
            $pdvId = (int) ($record->pdv_venda_id ?? 0);
            $map = $mapPorPdvVendaId ?? self::$cachePorPdvVendaId ?? [];

            if ($pdvId > 0 && isset($map[$pdvId]) && trim($map[$pdvId]) !== '') {
                return trim($map[$pdvId]);
            }

            // Histórico SCV sem DAV comprovado na origem / sem mapa → não inventar.
            return '—';
        }

        $venda = $record->pdvVenda;
        $numero = $venda?->numero;

        return $numero !== null
            ? str_pad((string) $numero, 6, '0', STR_PAD_LEFT)
            : '—';
    }

    public static function limparCache(): void
    {
        self::$cachePorPdvVendaId = null;
    }

    private static function mapaScvDisponivel(): bool
    {
        try {
            return Schema::hasTable('migra_scv_id_map') && Schema::hasTable('pedidos');
        } catch (\Throwable) {
            return false;
        }
    }
}
