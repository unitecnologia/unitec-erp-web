<?php

namespace App\Support\Erp\Dashboard;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpEmpresaScopeFilter;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ErpFinanceiroMetricas;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardGauges
{
    /** @var array<string, array<string, mixed>> */
    private static array $gaugeMemo = [];

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array<string, mixed>>
     */
    public static function build(?int $empresaId = null, int|array|null $empresaScope = null): array
    {
        $scope = $empresaScope ?? $empresaId;
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
        $empresa = is_array($scope)
            ? null
            : ($scope ? Empresa::query()->find((int) $scope) : ($empresaId ? Empresa::query()->find($empresaId) : null));

        $hoje = ErpFinanceiroMetricas::hoje();
        $inicio = $hoje->copy()->startOfMonth();
        $fim = $hoje;
        $gauges = [];

        // Visão geral da empresa (agrega os demais indicadores).
        $gauges[] = static::saudeEmpresa($empresa, $inicio, $fim, $scope);

        // Meta de vendas da empresa: só no dashboard se o valor estiver preenchido (> 0).
        $metaEmpresa = static::resolveMetaVendas($empresa, $scope);
        if ($metaEmpresa > 0) {
            $realizado = static::realizadoPdvMonitor($inicio, $fim, $scope);
            $gauges[] = static::metaVendas($empresa, $realizado, $metaEmpresa);
        }

        $gauges[] = static::recebimento($inicio, $fim, $scope);
        $gauges[] = static::margemLucro($inicio, $fim, $scope);
        $gauges[] = static::saudeEstoque();

        return $gauges;
    }

    /**
     * Gauge "Saúde do Estoque" (mesmo cálculo do dashboard ERP).
     *
     * @return array<string, mixed>
     */
    public static function saudeEstoqueGauge(): array
    {
        return static::saudeEstoque();
    }

    /**
     * Saúde da empresa (mesmo cálculo do gauge do dashboard ERP).
     * Usado pelo Painel Executivo para não divergir do /admin.
     *
     * @return array{percent: float, tone: string, label: string, short: string, message: string, factors: list<array<string, mixed>>}
     */
    public static function saudeSnapshot(?int $empresaId = null): array
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        $hoje = ErpFinanceiroMetricas::hoje();
        $inicio = $hoje->copy()->startOfMonth();
        $fim = $hoje;
        $scope = ($empresaId && $empresaId > 0) ? $empresaId : null;

        $gauge = static::saudeEmpresa($empresa, $inicio, $fim, $scope);
        $status = static::healthStatus((float) ($gauge['percent'] ?? 0));

        return [
            'percent' => (float) ($gauge['percent'] ?? 0),
            'tone' => (string) ($gauge['tone'] ?? $status['tone']),
            'label' => (string) ($status['label'] ?? $gauge['label'] ?? 'Saúde'),
            'short' => (string) ($status['short'] ?? ''),
            'message' => (string) ($status['message'] ?? ($gauge['meta_label'] ?? '')),
            'factors' => (array) ($gauge['detail']['factors'] ?? []),
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return list<array<string, mixed>>
     */
    public static function buildVendedores(?int $empresaId = null, int|array|null $empresaScope = null): array
    {
        try {
            if (! Schema::hasTable((new Vendedor)->getTable())) {
                return [];
            }

            $scope = $empresaScope ?? $empresaId;
            $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

            $hoje = ErpFinanceiroMetricas::hoje();
            $inicio = $hoje->copy()->startOfMonth();
            $fim = $hoje;

            $query = Vendedor::query()
                ->where('ativo', true)
                ->where('mobile_meta_venda', '>', 0)
                ->orderBy('nome');

            if (is_array($scope)) {
                $query->where(function ($builder) use ($scope): void {
                    $builder->whereIn('empresa_id', $scope)
                        ->orWhereHas('empresas', fn ($q) => $q->whereIn('empresas.id', $scope));
                });
            } elseif ($scope) {
                $query->where(function ($builder) use ($scope): void {
                    $builder->where('empresa_id', $scope)
                        ->orWhereHas('empresas', fn ($q) => $q->where('empresas.id', $scope));
                });
            } elseif ($empresaId) {
                $query->where(function ($builder) use ($empresaId): void {
                    $builder->where('empresa_id', $empresaId)
                        ->orWhereHas('empresas', fn ($q) => $q->where('empresas.id', $empresaId));
                });
            }

            $vendedores = $query->get(['id', 'codigo', 'nome', 'mobile_meta_venda']);

            if ($vendedores->isEmpty()) {
                return [];
            }

            $ids = $vendedores->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $realizados = static::realizadoPorVendedor($ids, $inicio, $fim);

            return $vendedores->map(function (Vendedor $vendedor) use ($realizados): array {
                $meta = (float) $vendedor->mobile_meta_venda;
                $realizado = (float) ($realizados[(int) $vendedor->id] ?? 0);
                $percent = $meta > 0 ? round(($realizado / $meta) * 100, 1) : 0.0;
                $primeiroNome = trim((string) strtok((string) $vendedor->nome, ' '));

                return [
                    'key' => 'vendedor_'.(int) $vendedor->id,
                    'label' => $primeiroNome !== '' ? $primeiroNome : (string) $vendedor->nome,
                    'full_name' => (string) $vendedor->nome,
                    'codigo' => (string) $vendedor->codigo,
                    'percent' => $percent,
                    'realizado' => $realizado,
                    'display_percent' => static::formatPercent($percent),
                    'value_label' => 'R$ '.ErpMoney::formatBr($realizado),
                    'meta_label' => 'Meta: R$ '.ErpMoney::formatBr($meta),
                    'stat_left_label' => 'Meta',
                    'stat_left' => static::formatCompact($meta),
                    'stat_right_label' => 'Real',
                    'stat_right' => static::formatCompact($realizado),
                    'tone' => static::toneByProgress($percent),
                    'detail' => null,
                    'compact' => true,
                ];
            })
                ->sortByDesc(fn (array $gauge): float => (float) ($gauge['realizado'] ?? 0))
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Realizado do mês por vendedor (mesma base do gauge "Meta Vendedores" do ERP).
     *
     * @param  list<int>  $vendedorIds
     * @return array<int, float>
     */
    public static function realizadoPorVendedor(array $vendedorIds, Carbon $inicio, Carbon $fim): array
    {
        $totais = array_fill_keys($vendedorIds, 0.0);

        if ($vendedorIds === []) {
            return $totais;
        }

        try {
            if (Schema::hasTable((new PdvVenda)->getTable())) {
                $rows = PdvVenda::query()
                    ->selectRaw('vendedor_id, SUM(total) as total')
                    ->whereIn('vendedor_id', $vendedorIds)
                    ->where('situacao', '!=', 'C')
                    ->where(function ($query) use ($inicio, $fim): void {
                        $query->where(function ($fechamento) use ($inicio, $fim): void {
                            $fechamento->whereNotNull('fechado_em')
                                ->whereDate('fechado_em', '>=', $inicio->toDateString())
                                ->whereDate('fechado_em', '<=', $fim->toDateString());
                        })->orWhere(function ($fallback) use ($inicio, $fim): void {
                            $fallback->whereNull('fechado_em')
                                ->whereDate('created_at', '>=', $inicio->toDateString())
                                ->whereDate('created_at', '<=', $fim->toDateString());
                        });
                    })
                    ->groupBy('vendedor_id')
                    ->pluck('total', 'vendedor_id');

                foreach ($rows as $id => $total) {
                    $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
                }
            }

            if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
                $rows = ForcaVendasOrder::query()
                    ->selectRaw('vendedor_id, SUM(total) as total')
                    ->whereIn('vendedor_id', $vendedorIds)
                    ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
                    ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                    ->where(function ($query) use ($inicio, $fim): void {
                        $query->where(function ($faturado) use ($inicio, $fim): void {
                            $faturado->whereNotNull('faturado_at')
                                ->whereDate('faturado_at', '>=', $inicio->toDateString())
                                ->whereDate('faturado_at', '<=', $fim->toDateString());
                        })->orWhere(function ($received) use ($inicio, $fim): void {
                            $received->whereNull('faturado_at')
                                ->whereNotNull('received_at')
                                ->whereDate('received_at', '>=', $inicio->toDateString())
                                ->whereDate('received_at', '<=', $fim->toDateString());
                        });
                    })
                    ->groupBy('vendedor_id')
                    ->pluck('total', 'vendedor_id');

                foreach ($rows as $id => $total) {
                    $totais[(int) $id] = round((float) ($totais[(int) $id] ?? 0) + (float) $total, 2);
                }
            }
        } catch (Throwable) {
            return $totais;
        }

        return $totais;
    }

    /**
     * Realizado de um vendedor no período (PDV + pedidos Força de Vendas).
     */
    public static function realizadoDoVendedor(int $vendedorId, Carbon $inicio, Carbon $fim): float
    {
        if ($vendedorId <= 0) {
            return 0.0;
        }

        return (float) (static::realizadoPorVendedor([$vendedorId], $inicio, $fim)[$vendedorId] ?? 0.0);
    }

    /**
     * @param  int|list<int>|null  $scope
     */
    private static function resolveMetaVendas(?Empresa $empresa, int|array|null $scope): float
    {
        if (is_array($scope)) {
            return (float) Empresa::query()->whereIn('id', $scope)->sum('param_meta_vendas_mensal');
        }

        return (float) ($empresa?->param_meta_vendas_mensal ?: 0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaVendas(?Empresa $empresa, float $realizado, ?float $metaOverride = null): array
    {
        $meta = $metaOverride ?? (float) ($empresa?->param_meta_vendas_mensal ?: 0);

        return static::metaGauge(
            key: 'meta_vendas',
            label: 'Meta de Vendas',
            meta: $meta,
            realizado: $realizado,
            emptyHint: 'Configure a Meta Vendas Mensal na Empresa',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaGauge(
        string $key,
        string $label,
        float $meta,
        float $realizado,
        string $emptyHint,
    ): array {
        $percent = $meta > 0 ? round(($realizado / $meta) * 100, 1) : 0.0;

        return [
            'key' => $key,
            'label' => $label,
            'percent' => $percent,
            'display_percent' => static::formatPercent($percent),
            'value_label' => 'R$ '.ErpMoney::formatBr($realizado),
            'meta_label' => $meta > 0
                ? 'Meta: R$ '.ErpMoney::formatBr($meta)
                : $emptyHint,
            'stat_left_label' => 'Meta',
            'stat_left' => $meta > 0 ? static::formatCompact($meta) : '—',
            'stat_right_label' => 'Real',
            'stat_right' => static::formatCompact($realizado),
            'tone' => static::toneByProgress($percent),
            'detail' => null,
        ];
    }

    /**
     * Regra A: títulos com vencimento no mês × valor já recebido desses títulos.
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function recebimento(Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        $memoKey = 'recebimento:'.$inicio->toDateString().':'.$fim->toDateString().':'.static::scopeMemoKey($empresaScope);
        if (isset(self::$gaugeMemo[$memoKey])) {
            return self::$gaugeMemo[$memoKey];
        }

        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return self::$gaugeMemo[$memoKey] = static::emptyGauge('recebimento', 'Recebimento', 'Sem contas a receber');
            }

            $query = ContaReceber::query()
                ->whereDate('vencimento', '>=', $inicio->toDateString())
                ->whereDate('vencimento', '<=', $fim->toDateString());

            ErpFinanceiroMetricas::applyEmpresaColumn($query, (new ContaReceber)->getTable(), $empresaScope);

            $row = $query
                ->selectRaw(
                    'COUNT(*) as qtd,'.
                    'COALESCE(SUM(CASE WHEN (valor - desconto + juros) > 0 THEN (valor - desconto + juros) ELSE 0 END), 0) as previsto,'.
                    'COALESCE(SUM(CASE WHEN valor_recebido > 0 THEN valor_recebido ELSE 0 END), 0) as recebido'
                )
                ->first();

            $qtd = (int) ($row->qtd ?? 0);
            if ($qtd === 0) {
                return self::$gaugeMemo[$memoKey] = static::emptyGauge('recebimento', 'Recebimento', 'Nenhum título no mês');
            }

            $previsto = round((float) ($row->previsto ?? 0), 2);
            $recebido = round((float) ($row->recebido ?? 0), 2);
            $percent = $previsto > 0 ? round(($recebido / $previsto) * 100, 1) : 0.0;

            return self::$gaugeMemo[$memoKey] = [
                'key' => 'recebimento',
                'label' => 'Recebimento',
                'percent' => $percent,
                'display_percent' => static::formatPercent($percent),
                'value_label' => 'R$ '.ErpMoney::formatBr($recebido),
                'meta_label' => 'Previsto: R$ '.ErpMoney::formatBr($previsto),
                'stat_left_label' => 'Prev.',
                'stat_left' => static::formatCompact($previsto),
                'stat_right_label' => 'Rec.',
                'stat_right' => static::formatCompact($recebido),
                'tone' => static::toneByProgress($percent),
                'detail' => null,
            ];
        } catch (Throwable) {
            return self::$gaugeMemo[$memoKey] = static::emptyGauge('recebimento', 'Recebimento', 'Erro ao calcular');
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array<string, mixed>
     */
    private static function margemLucro(Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        $memoKey = 'margem:'.$inicio->toDateString().':'.$fim->toDateString().':'.static::scopeMemoKey($empresaScope);
        if (isset(self::$gaugeMemo[$memoKey])) {
            return self::$gaugeMemo[$memoKey];
        }

        try {
            $receita = 0.0;
            $custo = 0.0;
            $unitCostSql = 'CASE'
                .' WHEN COALESCE(products.preco_custo, 0) > 0 THEN products.preco_custo'
                .' WHEN COALESCE(products.e_medio, 0) > 0 THEN products.e_medio'
                .' WHEN COALESCE(products.preco_compra, 0) > 0 THEN products.preco_compra'
                .' ELSE 0 END';

            // Vendas da retaguarda são a fonte canônica: incluem os espelhos
            // do PDV e os pedidos da Força de Vendas depois de faturados.
            if (Schema::hasTable((new VendaItem)->getTable())
                && Schema::hasTable((new Venda)->getTable())) {
                $vendaQuery = VendaItem::query()
                    ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
                    ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
                    ->whereNotIn('vendas.status', [Venda::STATUS_CANCELADO])
                    ->whereDate('vendas.data', '>=', $inicio->toDateString())
                    ->whereDate('vendas.data', '<=', $fim->toDateString());

                ErpEmpresaScopeFilter::applyColumn($vendaQuery, 'vendas', $empresaScope);

                $agg = $vendaQuery
                    ->selectRaw(
                        'COALESCE(SUM(venda_itens.total), 0) as receita,'.
                        'COALESCE(SUM(venda_itens.quantidade * ('.$unitCostSql.')), 0) as custo'
                    )
                    ->first();

                $receita += (float) ($agg->receita ?? 0);
                $custo += (float) ($agg->custo ?? 0);
            }

            // PDV sem espelho na retaguarda; os espelhados já entraram acima.
            if (Schema::hasTable((new PdvVendaItem)->getTable())) {
                $pdvQuery = PdvVendaItem::query()
                    ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_itens.pdv_venda_id')
                    ->leftJoin('products', 'products.id', '=', 'pdv_venda_itens.product_id')
                    ->where('pdv_vendas.situacao', '!=', 'C')
                    ->whereNull('pdv_vendas.venda_id')
                    ->where(function ($query) use ($inicio, $fim): void {
                        $query->where(function ($fechamento) use ($inicio, $fim): void {
                            $fechamento->whereNotNull('pdv_vendas.fechado_em')
                                ->whereDate('pdv_vendas.fechado_em', '>=', $inicio->toDateString())
                                ->whereDate('pdv_vendas.fechado_em', '<=', $fim->toDateString());
                        })->orWhere(function ($fallback) use ($inicio, $fim): void {
                            $fallback->whereNull('pdv_vendas.fechado_em')
                                ->whereDate('pdv_vendas.created_at', '>=', $inicio->toDateString())
                                ->whereDate('pdv_vendas.created_at', '<=', $fim->toDateString());
                        });
                    });

                if ($empresaScope !== null) {
                    $ids = is_array($empresaScope)
                        ? array_values(array_filter(array_map('intval', $empresaScope)))
                        : [(int) $empresaScope];
                    $ids = array_values(array_filter($ids, fn (int $id): bool => $id > 0));

                    if ($ids !== []) {
                        $pdvQuery->whereExists(function ($exists) use ($ids): void {
                            $exists->selectRaw('1')
                                ->from('pdv_caixa_sessoes')
                                ->whereColumn('pdv_caixa_sessoes.id', 'pdv_vendas.pdv_caixa_sessao_id')
                                ->whereIn('pdv_caixa_sessoes.empresa_id', $ids);
                        });
                    }
                }

                $agg = $pdvQuery
                    ->selectRaw(
                        'COALESCE(SUM(pdv_venda_itens.total), 0) as receita,'.
                        'COALESCE(SUM(pdv_venda_itens.quantidade * ('.$unitCostSql.')), 0) as custo'
                    )
                    ->first();

                $receita += (float) ($agg->receita ?? 0);
                $custo += (float) ($agg->custo ?? 0);
            }

            if ($receita <= 0) {
                return self::$gaugeMemo[$memoKey] = static::emptyGauge('margem', 'Margem de Lucro', 'Sem vendas no período');
            }

            $percent = round((($receita - $custo) / $receita) * 100, 1);
            $gaugeMax = 40.0;
            $needleTone = $gaugeMax > 0 ? ($percent / $gaugeMax) * 100 : $percent;

            return self::$gaugeMemo[$memoKey] = [
                'key' => 'margem',
                'label' => 'Margem de Lucro',
                'percent' => max(0, min(100, $percent)),
                'display_percent' => static::formatPercent($percent),
                'value_label' => 'Lucro: R$ '.ErpMoney::formatBr(max(0, $receita - $custo)),
                'meta_label' => 'Receita: R$ '.ErpMoney::formatBr($receita),
                'stat_left_label' => 'Rec.',
                'stat_left' => static::formatCompact($receita),
                'stat_right_label' => 'Lucro',
                'stat_right' => static::formatCompact(max(0, $receita - $custo)),
                'tone' => static::toneByProgress($needleTone),
                'detail' => null,
                'gauge_max' => $gaugeMax,
            ];
        } catch (Throwable) {
            return self::$gaugeMemo[$memoKey] = static::emptyGauge('margem', 'Margem de Lucro', 'Erro ao calcular');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function saudeEstoque(): array
    {
        if (isset(self::$gaugeMemo['estoque'])) {
            return self::$gaugeMemo['estoque'];
        }

        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return self::$gaugeMemo['estoque'] = static::emptyGauge('estoque', 'Estoque', 'Sem produtos');
            }

            $row = Product::query()
                ->where('ativo', true)
                ->selectRaw(
                    'COUNT(*) as total,'.
                    'COALESCE(SUM(CASE'
                    .' WHEN COALESCE(estoque, 0) <= 0 THEN 0'
                    .' WHEN COALESCE(estoque_minimo, 0) > 0 AND COALESCE(estoque, 0) < estoque_minimo THEN 0'
                    .' ELSE 1 END), 0) as ok,'.
                    'COALESCE(SUM(CASE'
                    .' WHEN COALESCE(estoque, 0) > 0 AND COALESCE(estoque_minimo, 0) > 0'
                    .' AND COALESCE(estoque, 0) < estoque_minimo THEN 1 ELSE 0 END), 0) as abaixo,'.
                    'COALESCE(SUM(CASE WHEN COALESCE(estoque, 0) <= 0 THEN 1 ELSE 0 END), 0) as zerado,'.
                    'COALESCE(SUM(CASE'
                    .' WHEN COALESCE(estoque_minimo, 0) > 0 AND COALESCE(estoque, 0) < estoque_minimo THEN 1'
                    .' ELSE 0 END), 0) as critico'
                )
                ->first();

            $total = (int) ($row->total ?? 0);
            if ($total === 0) {
                return self::$gaugeMemo['estoque'] = static::emptyGauge('estoque', 'Estoque', 'Nenhum produto ativo');
            }

            $ok = (int) ($row->ok ?? 0);
            $abaixo = (int) ($row->abaixo ?? 0);
            $zerado = (int) ($row->zerado ?? 0);
            $critico = (int) ($row->critico ?? 0);
            $percent = round(($ok / $total) * 100, 1);

            return self::$gaugeMemo['estoque'] = [
                'key' => 'estoque',
                'label' => 'Saúde do Estoque',
                'percent' => $percent,
                'display_percent' => static::formatPercent($percent),
                'value_label' => number_format($ok, 0, ',', '.').' OK · '
                    .number_format($critico, 0, ',', '.').' crítico'
                    .($zerado > 0 ? ' · '.number_format($zerado, 0, ',', '.').' zerados' : ''),
                'meta_label' => 'Produtos: '.number_format($total, 0, ',', '.'),
                'stat_left_label' => 'OK',
                'stat_left' => number_format($ok, 0, ',', '.'),
                'stat_right_label' => 'Crítico',
                'stat_right' => number_format($critico, 0, ',', '.'),
                'stat_right_tone' => $critico > 0 ? 'orange' : null,
                'tone' => static::toneByProgress($percent),
                'detail' => [
                    'total' => $total,
                    'ok' => $ok,
                    'abaixo' => $abaixo,
                    'zerado' => $zerado,
                    'critico' => $critico,
                ],
            ];
        } catch (Throwable) {
            return self::$gaugeMemo['estoque'] = static::emptyGauge('estoque', 'Saúde do Estoque', 'Erro ao calcular');
        }
    }

    /**
     * Índice único de saúde da empresa (média ponderada de indicadores internos).
     *
     * @return array<string, mixed>
     */
    private static function saudeEmpresa(?Empresa $empresa, Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        try {
            $factors = [
                static::factorCaixa($empresaScope),
                static::factorVendas($empresa, $inicio, $fim, $empresaScope),
                static::factorLucro($inicio, $fim, $empresaScope),
                static::factorEstoque(),
                static::factorRecebimento($inicio, $fim, $empresaScope),
                static::factorContasPagar($empresaScope),
                static::factorInadimplencia($empresaScope),
            ];

            $score = static::scoreFromFactors($factors);
            $status = static::healthStatus((float) $score['percent']);
            $label = is_array($empresaScope) ? 'Saúde do Grupo' : 'Saúde da Empresa';

            return [
                'key' => 'saude_empresa',
                'label' => $label,
                'percent' => $score['percent'],
                'display_percent' => static::formatPercent((float) $score['percent']),
                'value_label' => $status['label'],
                'meta_label' => $status['message'],
                'stat_left_label' => 'Status',
                'stat_left' => $status['short'],
                'stat_right_label' => 'Nota',
                'stat_right' => number_format((float) $score['percent'], 0, ',', '').'%',
                'tone' => $status['tone'],
                'clickable' => true,
                'detail' => [
                    'status' => $status['label'],
                    'message' => $status['message'],
                    'factors' => $score['factors'],
                    'modal_title' => $label.' — detalhe',
                ],
            ];
        } catch (Throwable) {
            $label = is_array($empresaScope) ? 'Saúde do Grupo' : 'Saúde da Empresa';

            return static::emptyGauge('saude_empresa', $label, 'Erro ao calcular');
        }
    }

    /**
     * @param  list<array{key?: string, label?: string, percent?: float, weight?: float, hint?: string}>  $factors
     * @return array{percent: float, factors: list<array<string, mixed>>}
     */
    public static function scoreFromFactors(array $factors): array
    {
        $weightSum = 0.0;
        $acc = 0.0;
        $out = [];

        foreach ($factors as $factor) {
            $weight = max(0.0, (float) ($factor['weight'] ?? 0));
            $percent = max(0.0, min(100.0, (float) ($factor['percent'] ?? 0)));
            $weightSum += $weight;
            $acc += $percent * $weight;
            $out[] = [
                'key' => (string) ($factor['key'] ?? ''),
                'label' => (string) ($factor['label'] ?? ''),
                'percent' => round($percent, 1),
                'weight' => $weight,
                'hint' => (string) ($factor['hint'] ?? ''),
                'tone' => static::toneByHealthScore($percent),
            ];
        }

        $final = $weightSum > 0 ? round($acc / $weightSum, 1) : 0.0;

        return [
            'percent' => $final,
            'factors' => $out,
        ];
    }

    /**
     * @return array{tone: string, label: string, short: string, message: string}
     */
    public static function healthStatus(float $percent): array
    {
        if ($percent >= 81) {
            return [
                'tone' => 'green',
                'label' => 'Empresa saudável',
                'short' => 'Saudável',
                'message' => 'Empresa saudável. Continue acompanhando os indicadores.',
            ];
        }

        if ($percent >= 61) {
            return [
                'tone' => 'lime',
                'label' => 'Atenção em alguns indicadores',
                'short' => 'Atenção',
                'message' => 'Atenção em alguns indicadores. Vale revisar o detalhe.',
            ];
        }

        if ($percent >= 41) {
            return [
                'tone' => 'orange',
                'label' => 'Situação preocupante',
                'short' => 'Alerta',
                'message' => 'Situação preocupante. Priorize caixa e inadimplência.',
            ];
        }

        return [
            'tone' => 'red',
            'label' => 'Situação crítica',
            'short' => 'Crítico',
            'message' => 'Situação crítica. Ação imediata recomendada.',
        ];
    }

    private static function toneByHealthScore(float $percent): string
    {
        if ($percent >= 81) {
            return 'green';
        }

        if ($percent >= 61) {
            return 'lime';
        }

        if ($percent >= 41) {
            return 'orange';
        }

        return 'red';
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorCaixa(int|array|null $empresaScope = null): array
    {
        try {
            $saldo = ErpFinanceiroMetricas::saldoCaixa(null, $empresaScope);
            $hoje = ErpFinanceiroMetricas::hoje();
            $obrigacoes = 0.0;

            if (Schema::hasTable((new ContaPagar)->getTable())) {
                // Inclui vencidos + a vencer em até 7 dias (pressão de caixa).
                $pagarQuery = ContaPagar::query()
                    ->where('saldo', '>', 0)
                    ->whereDate('vencimento', '<=', $hoje->copy()->addDays(7)->toDateString());

                ErpFinanceiroMetricas::applyEmpresaColumn($pagarQuery, (new ContaPagar)->getTable(), $empresaScope);
                $obrigacoes = (float) $pagarQuery->sum('saldo');
            }

            if ($obrigacoes <= 0.01) {
                $percent = $saldo >= 0 ? 100.0 : 20.0;
                $hint = $saldo >= 0
                    ? 'Sem obrigações urgentes · caixa R$ '.ErpMoney::formatBr($saldo)
                    : 'Caixa negativo sem obrigações próximas · R$ '.ErpMoney::formatBr($saldo);
            } elseif ($saldo >= $obrigacoes) {
                $percent = 100.0;
                $hint = 'Caixa cobre as obrigações de 7 dias · R$ '.ErpMoney::formatBr($saldo);
            } elseif ($saldo >= 0) {
                // Cobertura parcial: saldo / obrigações.
                $percent = max(5.0, min(99.0, round(($saldo / $obrigacoes) * 100, 1)));
                $hint = 'Caixa R$ '.ErpMoney::formatBr($saldo)
                    .' · a pagar (7 dias) R$ '.ErpMoney::formatBr($obrigacoes);
            } else {
                // Caixa negativo: nota baixa (0–25), nunca “falsa” cobertura positiva.
                $ratio = abs($saldo) / max($obrigacoes, 0.01);
                $percent = max(0.0, min(25.0, round(25.0 / (1 + $ratio), 1)));
                $hint = 'Caixa R$ '.ErpMoney::formatBr($saldo)
                    .' · a pagar (7 dias) R$ '.ErpMoney::formatBr($obrigacoes);
            }

            return [
                'key' => 'caixa',
                'label' => 'Caixa',
                'percent' => $percent,
                'weight' => 20,
                'hint' => $hint,
            ];
        } catch (Throwable) {
            return ['key' => 'caixa', 'label' => 'Caixa', 'percent' => 50.0, 'weight' => 20, 'hint' => 'Sem dados de caixa'];
        }
    }

    /**
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorVendas(?Empresa $empresa, Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        try {
            // Mesma base do KPI "Faturamento" / Executivo (vendas + PDV sem venda_id).
            $realizado = ErpDashboardSalesMetrics::faturamentoPeriodo($inicio, $fim, $empresaScope);
            $meta = static::resolveMetaVendas($empresa, $empresaScope);

            if ($meta > 0) {
                $percent = min(100.0, round(($realizado / $meta) * 100, 1));

                return [
                    'key' => 'vendas',
                    'label' => 'Vendas',
                    'percent' => $percent,
                    'weight' => 15,
                    'hint' => 'Real R$ '.ErpMoney::formatBr($realizado).' · Meta R$ '.ErpMoney::formatBr($meta),
                ];
            }

            $dias = max(1, (int) $inicio->diffInDays(ErpFinanceiroMetricas::hoje()) + 1);
            $inicioAnt = $inicio->copy()->subMonthNoOverflow()->startOfDay();
            $fimAnt = $inicioAnt->copy()->addDays($dias - 1)->endOfDay();
            $corte = $inicio->copy()->subDay()->endOfDay();
            if ($fimAnt->gt($corte)) {
                $fimAnt = $corte;
            }
            $anterior = ErpDashboardSalesMetrics::faturamentoPeriodo($inicioAnt, $fimAnt, $empresaScope);

            if ($anterior <= 0.01) {
                $percent = $realizado > 0 ? 80.0 : 50.0;
            } else {
                // 100% = igualou o mesmo período do mês anterior (não é “nota escolar”).
                $percent = min(100.0, round(($realizado / $anterior) * 100, 1));
            }

            return [
                'key' => 'vendas',
                'label' => 'Vendas',
                'percent' => $percent,
                'weight' => 15,
                'hint' => 'Mês R$ '.ErpMoney::formatBr($realizado)
                    .' · mesmo período ant. R$ '.ErpMoney::formatBr($anterior),
            ];
        } catch (Throwable) {
            return ['key' => 'vendas', 'label' => 'Vendas', 'percent' => 50.0, 'weight' => 15, 'hint' => 'Sem dados de vendas'];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorLucro(Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        $gauge = static::margemLucro($inicio, $fim, $empresaScope);
        $margem = (float) ($gauge['percent'] ?? 0);
        // Margem de contribuição → nota: ~40% de margem ≈ 100 na saúde.
        $percent = max(0.0, min(100.0, round($margem * 2.5, 1)));

        return [
            'key' => 'lucro',
            'label' => 'Lucro',
            'percent' => $percent,
            'weight' => 15,
            'hint' => trim(
                (string) ($gauge['value_label'] ?? '')
                .' · margem '.($gauge['display_percent'] ?? number_format($margem, 1, ',', '.').'%')
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorEstoque(): array
    {
        $gauge = static::saudeEstoque();

        return [
            'key' => 'estoque',
            'label' => 'Estoque',
            'percent' => (float) ($gauge['percent'] ?? 0),
            'weight' => 10,
            'hint' => (string) ($gauge['value_label'] ?? ''),
        ];
    }

    /**
     * Recebimento dos títulos com vencimento no mês (recebido ÷ previsto).
     *
     * @param  int|list<int>|null  $empresaScope
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorRecebimento(Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): array
    {
        $gauge = static::recebimento($inicio, $fim, $empresaScope);
        $recebidoLabel = (string) ($gauge['value_label'] ?? 'R$ 0,00');
        $previstoLabel = (string) ($gauge['meta_label'] ?? 'Previsto: R$ 0,00');
        // meta_label vem como "Previsto: R$ X" — monta dica completa.
        $previstoValor = str_replace('Previsto: ', '', $previstoLabel);

        return [
            'key' => 'receber',
            'label' => 'Recebimento',
            'percent' => (float) ($gauge['percent'] ?? 0),
            'weight' => 15,
            'hint' => $recebidoLabel.' recebidos de '.$previstoValor.' com vencimento no mês',
        ];
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorContasPagar(int|array|null $empresaScope = null): array
    {
        try {
            if (! Schema::hasTable((new ContaPagar)->getTable())) {
                return ['key' => 'pagar', 'label' => 'Contas a pagar', 'percent' => 70.0, 'weight' => 10, 'hint' => 'Sem contas a pagar'];
            }

            $abertoQuery = ContaPagar::query()->where('saldo', '>', 0);
            ErpFinanceiroMetricas::applyEmpresaColumn($abertoQuery, (new ContaPagar)->getTable(), $empresaScope);
            $aberto = (float) $abertoQuery->sum('saldo');
            $vencido = (float) ErpFinanceiroMetricas::pagarVencido(null, $empresaScope)['valor'];

            if ($aberto <= 0.01) {
                return [
                    'key' => 'pagar',
                    'label' => 'Contas a pagar',
                    'percent' => 100.0,
                    'weight' => 10,
                    'hint' => 'Nenhuma conta em aberto',
                ];
            }

            $percent = max(0.0, min(100.0, round((1 - ($vencido / $aberto)) * 100, 1)));

            return [
                'key' => 'pagar',
                'label' => 'Contas a pagar',
                'percent' => $percent,
                'weight' => 10,
                'hint' => 'Vencido R$ '.ErpMoney::formatBr($vencido)
                    .' de R$ '.ErpMoney::formatBr($aberto).' em aberto',
            ];
        } catch (Throwable) {
            return ['key' => 'pagar', 'label' => 'Contas a pagar', 'percent' => 50.0, 'weight' => 10, 'hint' => 'Erro ao calcular'];
        }
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorInadimplencia(int|array|null $empresaScope = null): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return ['key' => 'inadimplencia', 'label' => 'Inadimplência', 'percent' => 70.0, 'weight' => 15, 'hint' => 'Sem contas a receber'];
            }

            $abertoQuery = ContaReceber::query()->where('saldo', '>', 0);
            ErpFinanceiroMetricas::applyEmpresaColumn($abertoQuery, (new ContaReceber)->getTable(), $empresaScope);
            $aberto = (float) $abertoQuery->sum('saldo');
            $vencido = (float) ErpFinanceiroMetricas::receberVencido(null, $empresaScope)['valor'];

            if ($aberto <= 0.01) {
                return [
                    'key' => 'inadimplencia',
                    'label' => 'Inadimplência',
                    'percent' => 100.0,
                    'weight' => 15,
                    'hint' => 'Nenhum título em aberto',
                ];
            }

            $percent = max(0.0, min(100.0, round((1 - ($vencido / $aberto)) * 100, 1)));

            return [
                'key' => 'inadimplencia',
                'label' => 'Inadimplência',
                'percent' => $percent,
                'weight' => 15,
                'hint' => 'Vencido R$ '.ErpMoney::formatBr($vencido)
                    .' de R$ '.ErpMoney::formatBr($aberto).' em aberto',
            ];
        } catch (Throwable) {
            return ['key' => 'inadimplencia', 'label' => 'Inadimplência', 'percent' => 50.0, 'weight' => 15, 'hint' => 'Erro ao calcular'];
        }
    }

    public static function realizadoPdvMonitor(Carbon $inicio, Carbon $fim, int|array|null $empresaScope = null): float
    {
        $total = 0.0;

        try {
            if (Schema::hasTable((new PdvVenda)->getTable())) {
                $pdvQuery = PdvVenda::query()
                    ->where('situacao', '!=', 'C')
                    ->where(function ($query) use ($inicio, $fim): void {
                        $query->where(function ($fechamento) use ($inicio, $fim): void {
                            $fechamento->whereNotNull('fechado_em')
                                ->whereDate('fechado_em', '>=', $inicio->toDateString())
                                ->whereDate('fechado_em', '<=', $fim->toDateString());
                        })->orWhere(function ($fallback) use ($inicio, $fim): void {
                            $fallback->whereNull('fechado_em')
                                ->whereDate('created_at', '>=', $inicio->toDateString())
                                ->whereDate('created_at', '<=', $fim->toDateString());
                        });
                    });

                ErpEmpresaScopeFilter::applyPdvSessao($pdvQuery, $empresaScope);

                $total += (float) $pdvQuery->sum('total');
            }

            if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
                $fvQuery = ForcaVendasOrder::query()
                    ->where('situacao', '!=', ForcaVendasOrder::SITUACAO_CANCELADO)
                    ->where('tipo', ForcaVendasOrder::TIPO_PEDIDO)
                    ->where(function ($query) use ($inicio, $fim): void {
                        $query->where(function ($faturado) use ($inicio, $fim): void {
                            $faturado->whereNotNull('faturado_at')
                                ->whereDate('faturado_at', '>=', $inicio->toDateString())
                                ->whereDate('faturado_at', '<=', $fim->toDateString());
                        })->orWhere(function ($received) use ($inicio, $fim): void {
                            $received->whereNull('faturado_at')
                                ->whereNotNull('received_at')
                                ->whereDate('received_at', '>=', $inicio->toDateString())
                                ->whereDate('received_at', '<=', $fim->toDateString());
                        });
                    });

                ErpEmpresaScopeFilter::applyColumn($fvQuery, (new ForcaVendasOrder)->getTable(), $empresaScope);

                $total += (float) $fvQuery->sum('total');
            }
        } catch (Throwable) {
            return round($total, 2);
        }

        return round($total, 2);
    }

    /**
     * Escala unificada dos velocímetros:
     * 0–20 vermelho, 20–40 laranja, 40–60 amarelo,
     * 60–80 verde-claro, 80–100+ verde.
     */
    private static function toneByProgress(float $percent): string
    {
        if ($percent < 20) {
            return 'red';
        }

        if ($percent < 40) {
            return 'orange';
        }

        if ($percent < 60) {
            return 'yellow';
        }

        if ($percent < 80) {
            return 'lime';
        }

        return 'green';
    }

    /**
     * @param  int|list<int>|null  $empresaScope
     */
    private static function scopeMemoKey(int|array|null $empresaScope): string
    {
        if ($empresaScope === null) {
            return 'all';
        }

        if (is_array($empresaScope)) {
            $ids = array_values(array_filter(array_map('intval', $empresaScope)));
            sort($ids);

            return 'g:'.implode(',', $ids);
        }

        return 'e:'.(int) $empresaScope;
    }

    private static function formatPercent(float $percent): string
    {
        return number_format($percent, 1, ',', '').'%';
    }

    private static function formatCompact(float $value): string
    {
        if (abs($value) >= 1000) {
            return number_format($value, 0, ',', '.');
        }

        return ErpMoney::formatBr($value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptyGauge(string $key, string $label, string $hint): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'percent' => 0.0,
            'display_percent' => '0,0%',
            'value_label' => '—',
            'meta_label' => $hint,
            'stat_left_label' => 'Meta',
            'stat_left' => '—',
            'stat_right_label' => 'Real',
            'stat_right' => '—',
            'tone' => 'slate',
            'detail' => null,
        ];
    }
}
