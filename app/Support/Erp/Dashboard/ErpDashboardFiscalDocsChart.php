<?php

namespace App\Support\Erp\Dashboard;

use App\Support\Erp\ErpSchema;
use App\Models\Nfe;
use App\Models\Nfse;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
            $nfceAut = static::countNfce($empresaScope, $inicio, $fim, autorizadas: true);
            $nfcePend = static::countNfce($empresaScope, $inicio, $fim, autorizadas: false);
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
     * @param  int|list<int>|null  $empresaScope
     */
    private static function countNfce(int|array|null $empresaScope, Carbon $inicio, Carbon $fim, bool $autorizadas): int
    {
        if (! ErpSchema::hasTable((new PdvVendaNfce)->getTable())) {
            return 0;
        }

        $query = PdvVendaNfce::query()
            ->whereHas('pdvVenda', function (Builder $venda) use ($inicio, $fim): void {
                $venda->whereDate('fechado_em', '>=', $inicio->toDateString())
                    ->whereDate('fechado_em', '<=', $fim->toDateString());
            });

        static::applyNfceEmpresaScope($query, $empresaScope);

        if ($autorizadas) {
            $query->whereIn('status', [
                PdvVendaNfce::STATUS_AUTORIZADA,
                PdvVendaNfce::STATUS_SIMULADA,
            ]);
        } else {
            $query->whereIn('status', [
                PdvVendaNfce::STATUS_PENDENTE,
                PdvVendaNfce::STATUS_CONTINGENCIA,
            ]);
        }

        return (int) $query->count();
    }

    /**
     * Mesmo critério da tela NFC-e: empresa_id no scope OU (nulo + sessão PDV).
     *
     * @param  int|list<int>|null  $empresaScope
     */
    private static function applyNfceEmpresaScope(Builder $query, int|array|null $empresaScope): void
    {
        if ($empresaScope === null) {
            return;
        }

        $ids = is_array($empresaScope)
            ? array_values(array_filter(array_map('intval', $empresaScope)))
            : [(int) $empresaScope];
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($ids): void {
            $outer->whereIn('empresa_id', $ids)
                ->orWhere(function (Builder $inner) use ($ids): void {
                    $inner->whereNull('empresa_id')
                        ->whereHas('pdvVenda.sessao', fn (Builder $sessao): Builder => $sessao
                            ->whereIn('empresa_id', $ids));
                });
        });
    }
}
