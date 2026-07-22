<?php

namespace App\Support\Erp\Dashboard;

use App\Models\CaixaLancamento;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\Product;
use App\Models\Vendedor;
use App\Support\Erp\ErpMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ErpDashboardGauges
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(?int $empresaId = null): array
    {
        $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        $inicio = Carbon::today()->startOfMonth();
        $fim = Carbon::today()->endOfMonth();
        $gauges = [];

        // Visão geral da empresa (agrega os demais indicadores).
        $gauges[] = static::saudeEmpresa($empresa, $inicio, $fim);

        // Meta de vendas da empresa: só no dashboard se o valor estiver preenchido (> 0).
        $metaEmpresa = (float) ($empresa?->param_meta_vendas_mensal ?: 0);
        if ($metaEmpresa > 0) {
            $realizado = static::realizadoPdvMonitor($inicio, $fim);
            $gauges[] = static::metaVendas($empresa, $realizado);
        }

        $gauges[] = static::recebimento($inicio, $fim);
        $gauges[] = static::margemLucro($inicio, $fim);
        $gauges[] = static::saudeEstoque();

        return $gauges;
    }

    /**
     * Gauges individuais: colaborador com Meta Venda Mensal preenchida (> 0).
     *
     * @return list<array<string, mixed>>
     */
    public static function buildVendedores(?int $empresaId = null): array
    {
        try {
            if (! Schema::hasTable((new Vendedor)->getTable())) {
                return [];
            }

            $empresaId ??= ErpDashboardCertificadoAlert::resolveEmpresaId();

            $inicio = Carbon::today()->startOfMonth();
            $fim = Carbon::today()->endOfMonth();

            $query = Vendedor::query()
                ->where('ativo', true)
                ->where('mobile_meta_venda', '>', 0)
                ->orderBy('nome');

            if ($empresaId) {
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
     * @return array<string, mixed>
     */
    private static function metaVendas(?Empresa $empresa, float $realizado): array
    {
        $meta = (float) ($empresa?->param_meta_vendas_mensal ?: 0);

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
     * @return array<string, mixed>
     */
    private static function recebimento(Carbon $inicio, Carbon $fim): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return static::emptyGauge('recebimento', 'Recebimento', 'Sem contas a receber');
            }

            $rows = ContaReceber::query()
                ->whereDate('vencimento', '>=', $inicio->toDateString())
                ->whereDate('vencimento', '<=', $fim->toDateString())
                ->get(['valor', 'desconto', 'juros', 'valor_recebido']);

            if ($rows->isEmpty()) {
                return static::emptyGauge('recebimento', 'Recebimento', 'Nenhum título no mês');
            }

            $previsto = 0.0;
            $recebido = 0.0;

            foreach ($rows as $row) {
                $face = (float) $row->valor - (float) $row->desconto + (float) $row->juros;
                $previsto += max(0, $face);
                $recebido += max(0, (float) $row->valor_recebido);
            }

            $percent = $previsto > 0 ? round(($recebido / $previsto) * 100, 1) : 0.0;

            return [
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
            return static::emptyGauge('recebimento', 'Recebimento', 'Erro ao calcular');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function margemLucro(Carbon $inicio, Carbon $fim): array
    {
        try {
            $receita = 0.0;
            $custo = 0.0;

            if (Schema::hasTable((new PdvVendaItem)->getTable())) {
                $itens = PdvVendaItem::query()
                    ->select([
                        'pdv_venda_itens.quantidade',
                        'pdv_venda_itens.total',
                        'products.preco_custo',
                        'products.e_medio',
                        'products.preco_compra',
                    ])
                    ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_itens.pdv_venda_id')
                    ->leftJoin('products', 'products.id', '=', 'pdv_venda_itens.product_id')
                    ->where('pdv_vendas.situacao', '!=', 'C')
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
                    })
                    ->get();

                foreach ($itens as $item) {
                    $receita += (float) $item->total;
                    $unitCost = static::productUnitCost(
                        $item->preco_custo,
                        $item->e_medio,
                        $item->preco_compra,
                    );
                    $custo += ((float) $item->quantidade) * $unitCost;
                }
            }

            if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
                $orders = ForcaVendasOrder::query()
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
                    ->get(['payload', 'total']);

                $productIds = [];
                foreach ($orders as $order) {
                    foreach ((array) ($order->payload['itens'] ?? []) as $item) {
                        $pid = (int) ($item['product_id'] ?? 0);
                        if ($pid > 0) {
                            $productIds[$pid] = true;
                        }
                    }
                }

                $costs = Product::query()
                    ->whereIn('id', array_keys($productIds))
                    ->get(['id', 'preco_custo', 'e_medio', 'preco_compra'])
                    ->keyBy('id');

                foreach ($orders as $order) {
                    $itens = (array) ($order->payload['itens'] ?? []);
                    if ($itens === []) {
                        continue;
                    }

                    foreach ($itens as $item) {
                        $qty = (float) ($item['quantidade'] ?? 0);
                        $totalItem = (float) ($item['total'] ?? 0);
                        if ($totalItem <= 0) {
                            $preco = (float) ($item['preco'] ?? $item['preco_unitario'] ?? 0);
                            $desc = (float) ($item['desconto'] ?? 0);
                            $totalItem = max(0, ($qty * $preco) - $desc);
                        }
                        $receita += $totalItem;
                        $product = $costs->get((int) ($item['product_id'] ?? 0));
                        $unitCost = static::productUnitCost(
                            $product?->preco_custo,
                            $product?->e_medio,
                            $product?->preco_compra,
                        );
                        $custo += $qty * $unitCost;
                    }
                }
            }

            if ($receita <= 0) {
                return static::emptyGauge('margem', 'Margem de Lucro', 'Sem vendas no período');
            }

            $percent = round((($receita - $custo) / $receita) * 100, 1);
            $gaugeMax = 40.0;
            $needleTone = $gaugeMax > 0 ? ($percent / $gaugeMax) * 100 : $percent;

            return [
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
            return static::emptyGauge('margem', 'Margem de Lucro', 'Erro ao calcular');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function saudeEstoque(): array
    {
        try {
            if (! Schema::hasTable((new Product)->getTable())) {
                return static::emptyGauge('estoque', 'Estoque', 'Sem produtos');
            }

            $rows = Product::query()
                ->where('ativo', true)
                ->get(['estoque', 'estoque_minimo']);

            $total = $rows->count();
            if ($total === 0) {
                return static::emptyGauge('estoque', 'Estoque', 'Nenhum produto ativo');
            }

            $ok = 0;
            $abaixo = 0;
            $zerado = 0;

            foreach ($rows as $product) {
                $qty = (float) $product->estoque;
                $min = max(0.0, (float) ($product->estoque_minimo ?? 0));

                if ($qty <= 0) {
                    $zerado++;
                } elseif ($qty < $min) {
                    $abaixo++;
                } else {
                    $ok++;
                }
            }

            $percent = round(($ok / $total) * 100, 1);

            return [
                'key' => 'estoque',
                'label' => 'Saúde do Estoque',
                'percent' => $percent,
                'display_percent' => static::formatPercent($percent),
                'value_label' => number_format($ok, 0, ',', '.').' OK · '
                    .number_format($abaixo, 0, ',', '.').' abaixo · '
                    .number_format($zerado, 0, ',', '.').' zerados',
                'meta_label' => 'Produtos: '.number_format($total, 0, ',', '.'),
                'stat_left_label' => 'OK',
                'stat_left' => number_format($ok, 0, ',', '.'),
                'stat_right_label' => 'Total',
                'stat_right' => number_format($total, 0, ',', '.'),
                'tone' => static::toneByProgress($percent),
                'detail' => [
                    'total' => $total,
                    'ok' => $ok,
                    'abaixo' => $abaixo,
                    'zerado' => $zerado,
                ],
            ];
        } catch (Throwable) {
            return static::emptyGauge('estoque', 'Saúde do Estoque', 'Erro ao calcular');
        }
    }

    /**
     * Índice único de saúde da empresa (média ponderada de indicadores internos).
     *
     * @return array<string, mixed>
     */
    private static function saudeEmpresa(?Empresa $empresa, Carbon $inicio, Carbon $fim): array
    {
        try {
            $factors = [
                static::factorCaixa(),
                static::factorVendas($empresa, $inicio, $fim),
                static::factorLucro($inicio, $fim),
                static::factorEstoque(),
                static::factorRecebimento($inicio, $fim),
                static::factorContasPagar(),
                static::factorInadimplencia(),
            ];

            $score = static::scoreFromFactors($factors);
            $status = static::healthStatus((float) $score['percent']);

            return [
                'key' => 'saude_empresa',
                'label' => 'Saúde da Empresa',
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
                ],
            ];
        } catch (Throwable) {
            return static::emptyGauge('saude_empresa', 'Saúde da Empresa', 'Erro ao calcular');
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
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorCaixa(): array
    {
        try {
            $saldo = 0.0;
            if (Schema::hasTable((new CaixaLancamento)->getTable())) {
                $saldo = (float) CaixaLancamento::query()->sum('entrada')
                    - (float) CaixaLancamento::query()->sum('saida');
            }

            $obrigacoes = 0.0;
            if (Schema::hasTable((new ContaPagar)->getTable())) {
                $limite = Carbon::today()->addDays(7)->toDateString();
                $obrigacoes = (float) ContaPagar::query()
                    ->where('saldo', '>', 0)
                    ->whereDate('vencimento', '<=', $limite)
                    ->sum('saldo');
            }

            if ($obrigacoes <= 0.01) {
                $percent = $saldo >= 0 ? 100.0 : 35.0;
                $hint = $saldo >= 0
                    ? 'Sem obrigações urgentes · caixa R$ '.ErpMoney::formatBr($saldo)
                    : 'Caixa negativo sem obrigações próximas';
            } else {
                $percent = max(0.0, min(100.0, round(($saldo / $obrigacoes) * 100, 1)));
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
    private static function factorVendas(?Empresa $empresa, Carbon $inicio, Carbon $fim): array
    {
        try {
            $realizado = static::realizadoPdvMonitor($inicio, $fim);
            $meta = (float) ($empresa?->param_meta_vendas_mensal ?: 0);

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

            $dias = max(1, (int) $inicio->diffInDays(Carbon::today()) + 1);
            $inicioAnt = $inicio->copy()->subMonthNoOverflow()->startOfDay();
            $fimAnt = $inicioAnt->copy()->addDays($dias - 1)->endOfDay();
            $corte = $inicio->copy()->subDay()->endOfDay();
            if ($fimAnt->gt($corte)) {
                $fimAnt = $corte;
            }
            $anterior = static::realizadoPdvMonitor($inicioAnt, $fimAnt);

            if ($anterior <= 0.01) {
                $percent = $realizado > 0 ? 80.0 : 50.0;
            } else {
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
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorLucro(Carbon $inicio, Carbon $fim): array
    {
        $gauge = static::margemLucro($inicio, $fim);
        $margem = (float) ($gauge['percent'] ?? 0);
        $percent = max(0.0, min(100.0, round($margem * 2.5, 1)));

        return [
            'key' => 'lucro',
            'label' => 'Lucro',
            'percent' => $percent,
            'weight' => 15,
            'hint' => (string) ($gauge['value_label'] ?? ('Margem '.($gauge['display_percent'] ?? ''))),
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
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorRecebimento(Carbon $inicio, Carbon $fim): array
    {
        $gauge = static::recebimento($inicio, $fim);

        return [
            'key' => 'receber',
            'label' => 'Contas a receber',
            'percent' => (float) ($gauge['percent'] ?? 0),
            'weight' => 15,
            'hint' => (string) ($gauge['meta_label'] ?? ''),
        ];
    }

    /**
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorContasPagar(): array
    {
        try {
            if (! Schema::hasTable((new ContaPagar)->getTable())) {
                return ['key' => 'pagar', 'label' => 'Contas a pagar', 'percent' => 70.0, 'weight' => 10, 'hint' => 'Sem contas a pagar'];
            }

            $aberto = (float) ContaPagar::query()->where('saldo', '>', 0)->sum('saldo');
            $vencido = (float) ContaPagar::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', Carbon::today()->toDateString())
                ->sum('saldo');

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
     * @return array{key: string, label: string, percent: float, weight: float, hint: string}
     */
    private static function factorInadimplencia(): array
    {
        try {
            if (! Schema::hasTable((new ContaReceber)->getTable())) {
                return ['key' => 'inadimplencia', 'label' => 'Inadimplência', 'percent' => 70.0, 'weight' => 15, 'hint' => 'Sem contas a receber'];
            }

            $aberto = (float) ContaReceber::query()->where('saldo', '>', 0)->sum('saldo');
            $vencido = (float) ContaReceber::query()
                ->where('saldo', '>', 0)
                ->whereDate('vencimento', '<', Carbon::today()->toDateString())
                ->sum('saldo');

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

    public static function realizadoPdvMonitor(Carbon $inicio, Carbon $fim): float
    {
        $total = 0.0;

        try {
            if (Schema::hasTable((new PdvVenda)->getTable())) {
                $total += (float) PdvVenda::query()
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
                    ->sum('total');
            }

            if (Schema::hasTable((new ForcaVendasOrder)->getTable())) {
                $total += (float) ForcaVendasOrder::query()
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
                    ->sum('total');
            }
        } catch (Throwable) {
            return round($total, 2);
        }

        return round($total, 2);
    }

    private static function productUnitCost(mixed $precoCusto, mixed $eMedio, mixed $precoCompra): float
    {
        foreach ([$precoCusto, $eMedio, $precoCompra] as $value) {
            $n = (float) $value;
            if ($n > 0) {
                return $n;
            }
        }

        return 0.0;
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
