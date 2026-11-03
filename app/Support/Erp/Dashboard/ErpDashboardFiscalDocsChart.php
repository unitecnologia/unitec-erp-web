<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ErpDashboardFiscalDocsChart
{
    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}
     */
    public static function data(int|array|null $empresaScope = null): array
    {
        return static::fromDatabase($empresaScope) ?? static::withoutZeroSlices([
            'labels' => ['NFe Aut.', 'NFe Pend.', 'NFCe Aut.', 'NFCe Pend.', 'NFSe Aut.', 'NFSe Pend.'],
            'values' => [0.0, 0.0, 0.0, 0.0, 0.0, 0.0],
            'colors' => ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b', '#7c3aed', '#c4b5fd'],
            'unit' => 'count',
            'empty' => true,
        ]);
    }

    /**
     * Remove categorias com quantidade 0 para a legenda não sugerir pendência inexistente.
     *
     * @param  array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}  $chart
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}
     */
    public static function withoutZeroSlices(array $chart): array
    {
        $labels = array_values($chart['labels'] ?? []);
        $values = array_values($chart['values'] ?? []);
        $colors = array_values($chart['colors'] ?? []);

        $keptLabels = [];
        $keptValues = [];
        $keptColors = [];

        foreach ($values as $i => $value) {
            if ((float) $value <= 0.0) {
                continue;
            }

            $keptLabels[] = (string) ($labels[$i] ?? '');
            $keptValues[] = (float) $value;
            $keptColors[] = (string) ($colors[$i] ?? '#64748b');
        }

        $chart['labels'] = $keptLabels;
        $chart['values'] = $keptValues;
        $chart['colors'] = $keptColors;
        $chart['empty'] = ($chart['empty'] ?? false) || $keptValues === [];

        return $chart;
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{labels: list<string>, values: list<float>, colors: list<string>, unit: string, empty?: bool}|null
     */
    private static function fromDatabase(int|array|null $empresaScope = null): ?array
    {
        try {
            if ($empresaScope === null) {
                $empresaId = ErpDashboardCertificadoAlert::resolveEmpresaId();
                $empresaScope = ($empresaId && $empresaId > 0) ? $empresaId : null;
            }

            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;

            [$nfeAut, $nfePend] = static::countNfeBuckets($empresaScope, $inicio, $fim);
            [$nfceAut, $nfcePend] = static::countNfceBuckets($empresaScope, $inicio, $fim);
            [$nfseAut, $nfsePend] = static::countNfseBuckets($empresaScope, $inicio, $fim);

            $empty = ($nfeAut + $nfePend + $nfceAut + $nfcePend + $nfseAut + $nfsePend) <= 0;

            return static::withoutZeroSlices([
                'labels' => ['NFe Aut.', 'NFe Pend.', 'NFCe Aut.', 'NFCe Pend.', 'NFSe Aut.', 'NFSe Pend.'],
                'values' => [
                    (float) $nfeAut,
                    (float) $nfePend,
                    (float) $nfceAut,
                    (float) $nfcePend,
                    (float) $nfseAut,
                    (float) $nfsePend,
                ],
                'colors' => ['#1d4ed8', '#93c5fd', '#0f766e', '#f59e0b', '#7c3aed', '#c4b5fd'],
                'unit' => 'count',
                'empty' => $empty,
            ]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Uma consulta: autorizadas (transmitida) e pendentes (aberta/contingência).
     * Canceladas/denegadas/duplicidade/inutilizadas ficam de fora.
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array{0: int, 1: int}
     */
    private static function countNfeBuckets(int|array|null $empresaScope, Carbon $inicio, Carbon $fim): array
    {
        if (! ErpSchema::hasTable((new Nfe)->getTable())) {
            return [0, 0];
        }

        $query = Nfe::query()
            ->whereDate('data_emissao', '>=', $inicio->toDateString())
            ->whereDate('data_emissao', '<=', $fim->toDateString())
            ->where(function ($builder): void {
                $builder->where('situacao', Nfe::SITUACAO_TRANSMITIDA)
                    ->orWhere('status', Nfe::STATUS_TRANSMITIDA)
                    ->orWhereIn('situacao', [Nfe::SITUACAO_ABERTA, Nfe::SITUACAO_CONTINGENCIA])
                    ->orWhereIn('status', [Nfe::STATUS_ABERTA, Nfe::STATUS_CONTINGENCIA]);
            });

        ErpFinanceiroMetricas::applyEmpresaColumn($query, (new Nfe)->getTable(), $empresaScope);

        $nfe = static::sqlTable((new Nfe)->getTable());
        // Autorizada tem prioridade se situacao/status divergirem (evita contar a mesma NF-e duas vezes).
        $row = $query->selectRaw(
            "SUM(CASE WHEN {$nfe}.situacao = ? OR {$nfe}.status = ? THEN 1 ELSE 0 END) as aut, "
            ."SUM(CASE WHEN NOT ({$nfe}.situacao = ? OR {$nfe}.status = ?) "
            ."AND ({$nfe}.situacao IN (?, ?) OR {$nfe}.status IN (?, ?)) THEN 1 ELSE 0 END) as pend",
            [
                Nfe::SITUACAO_TRANSMITIDA,
                Nfe::STATUS_TRANSMITIDA,
                Nfe::SITUACAO_TRANSMITIDA,
                Nfe::STATUS_TRANSMITIDA,
                Nfe::SITUACAO_ABERTA,
                Nfe::SITUACAO_CONTINGENCIA,
                Nfe::STATUS_ABERTA,
                Nfe::STATUS_CONTINGENCIA,
            ]
        )->first();

        return [(int) ($row->aut ?? 0), (int) ($row->pend ?? 0)];
    }

    /**
     * NFS-e por cabeçalho (sem duplicar via itens). Autorizada vs pendente (aberta/transmitida/contingência).
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array{0: int, 1: int}
     */
    private static function countNfseBuckets(int|array|null $empresaScope, Carbon $inicio, Carbon $fim): array
    {
        if (! ErpSchema::hasTable((new Nfse)->getTable())) {
            return [0, 0];
        }

        $query = Nfse::query()
            ->whereDate('data_emissao', '>=', $inicio->toDateString())
            ->whereDate('data_emissao', '<=', $fim->toDateString())
            ->whereIn('status', [
                Nfse::STATUS_AUTORIZADA,
                Nfse::STATUS_ABERTA,
                Nfse::STATUS_TRANSMITIDA,
                Nfse::STATUS_CONTINGENCIA,
            ]);

        ErpFinanceiroMetricas::applyEmpresaColumn($query, (new Nfse)->getTable(), $empresaScope);

        $nfse = static::sqlTable((new Nfse)->getTable());
        $row = $query->selectRaw(
            "SUM(CASE WHEN {$nfse}.status = ? THEN 1 ELSE 0 END) as aut, "
            ."SUM(CASE WHEN {$nfse}.status IN (?, ?, ?) THEN 1 ELSE 0 END) as pend",
            [
                Nfse::STATUS_AUTORIZADA,
                Nfse::STATUS_ABERTA,
                Nfse::STATUS_TRANSMITIDA,
                Nfse::STATUS_CONTINGENCIA,
            ]
        )->first();

        return [(int) ($row->aut ?? 0), (int) ($row->pend ?? 0)];
    }

    private static function sqlTable(string $table): string
    {
        return DB::getTablePrefix().$table;
    }

    /**
     * NFC-e autorizadas + pendentes em uma consulta, partindo de pdv_vendas
     * com intervalo datetime (sem DATE() na coluna) para usar o índice de fechamento.
     *
     * Critérios preservados:
     * - período por fechado_em (mesmo efeito de whereDate >= / <=);
     * - empresa_id no documento OU (empresa_id nulo + sessão PDV);
     * - status aut = autorizada/simulada; pend = pendente/contingencia.
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array{0: int, 1: int}
     */
    private static function countNfceBuckets(int|array|null $empresaScope, Carbon $inicio, Carbon $fim): array
    {
        if (! ErpSchema::hasTable((new PdvVendaNfce)->getTable())) {
            return [0, 0];
        }

        if (! ErpSchema::hasTable('pdv_vendas')) {
            return [0, 0];
        }

        $ids = static::normalizeEmpresaIds($empresaScope);
        $from = $inicio->copy()->startOfDay()->format('Y-m-d H:i:s');
        $toExclusive = $fim->copy()->startOfDay()->addDay()->format('Y-m-d H:i:s');

        $v = static::sqlTable('pdv_vendas');
        $n = static::sqlTable((new PdvVendaNfce)->getTable());
        $s = static::sqlTable('pdv_caixa_sessoes');

        $bindings = [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_SIMULADA,
            PdvVendaNfce::STATUS_PENDENTE,
            PdvVendaNfce::STATUS_CONTINGENCIA,
            $from,
            $toExclusive,
        ];

        // STRAIGHT_JOIN: força partir de pdv_vendas (faixa de fechado_em) em vez de
        // full scan em pdv_venda_nfce — vantagem comprovada no EXPLAIN da DEV.
        $sql = "SELECT "
            .'SUM(CASE WHEN n.status IN (?, ?) THEN 1 ELSE 0 END) as aut, '
            .'SUM(CASE WHEN n.status IN (?, ?) THEN 1 ELSE 0 END) as pend '
            ."FROM {$v} v "
            ."STRAIGHT_JOIN {$n} n ON n.pdv_venda_id = v.id "
            ."LEFT JOIN {$s} s ON s.id = v.pdv_caixa_sessao_id "
            .'WHERE v.fechado_em >= ? AND v.fechado_em < ?';

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND (n.empresa_id IN ({$placeholders})"
                ." OR (n.empresa_id IS NULL AND s.empresa_id IN ({$placeholders})))";
            foreach ($ids as $id) {
                $bindings[] = $id;
            }
            foreach ($ids as $id) {
                $bindings[] = $id;
            }
        }

        $row = DB::selectOne($sql, $bindings);

        return [(int) ($row->aut ?? 0), (int) ($row->pend ?? 0)];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<int>
     */
    private static function normalizeEmpresaIds(int|array|null $empresaScope): array
    {
        if ($empresaScope === null) {
            return [];
        }

        $ids = is_array($empresaScope)
            ? array_values(array_filter(array_map('intval', $empresaScope)))
            : [(int) $empresaScope];

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
