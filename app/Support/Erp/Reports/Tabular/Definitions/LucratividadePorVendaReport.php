<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\DevolucaoVenda;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\Dre\VendaItemCustoHistorico;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaReceitaRateio;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\ExpandableTabularReport;
use App\Support\Erp\Tef\TefMoney;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1ª etapa — Lucratividade por venda (motor tabular).
 *
 * Fonte: vendas fechadas (espelho PDV/TV/FV) + custo histórico D2.6 nos itens.
 * Sem fallback para custo cadastral atual. Canceladas fora. Devoluções finalizadas
 * abatem receita e CMV quando o custo da devolução também for confiável.
 */
class LucratividadePorVendaReport extends AbstractTabularReport implements ExpandableTabularReport
{
    public const TIPO_DETALHADO = 'detalhado';

    public const TIPO_RESUMIDO = 'resumido';

    private const DETAIL_LIMIT = 2000;

    private const DETAILS_PER_PAGE = 80;

    public function slug(): string
    {
        return 'lucratividade-por-venda';
    }

    public function title(): string
    {
        return 'LUCRATIVIDADE POR VENDA';
    }

    public function permission(): string
    {
        return 'vendas.print';
    }

    public function requiresExplicitGenerate(): bool
    {
        return true;
    }

    public function locksCurrentEmpresa(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [
            'numero' => 'VENDA',
            'data' => 'DATA',
            'cliente' => 'CLIENTE',
            'vendedor' => 'VENDEDOR',
            'total_liquido' => 'TOTAL LÍQUIDO',
            'custo_historico' => 'CUSTO HIST.',
            'lucro' => 'LUCRO R$',
            'margem' => 'MARGEM %',
        ];
    }

    /**
     * Colunas exclusivas do modo Resumido (uma linha de totais).
     *
     * @return array<string, string>
     */
    public function resumidoColumns(): array
    {
        return [
            'qtd_vendas' => 'QTD VENDAS',
            'total_liquido' => 'TOTAL LÍQUIDO',
            'custo_historico' => 'CUSTO HIST.',
            'lucro' => 'LUCRO R$',
            'margem' => 'MARGEM %',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'vendedor',
            'total_liquido',
            'custo_historico',
            'lucro',
            'margem',
        ];
    }

    public function numericColumns(): array
    {
        return ['qtd_vendas', 'total_liquido', 'custo_historico', 'lucro', 'margem'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 7,
            'data' => 7,
            'cliente' => 28,
            'vendedor' => 14,
            'qtd_vendas' => 12,
            'total_liquido' => 16,
            'custo_historico' => 16,
            'lucro' => 16,
            'margem' => 12,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'data', 'qtd_vendas', 'total_liquido', 'custo_historico', 'lucro', 'margem'];
    }

    /**
     * @return array<string, string>
     */
    public static function tipoOptions(): array
    {
        return [
            self::TIPO_DETALHADO => 'Detalhado',
            self::TIPO_RESUMIDO => 'Resumido',
        ];
    }

    public static function normalizeTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));

        return array_key_exists($tipo, self::tipoOptions()) ? $tipo : self::TIPO_DETALHADO;
    }

    public function filterFields(): array
    {
        $empresa = ErpContext::currentEmpresa();
        $empresaLabel = $empresa
            ? trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)))
            : '—';

        return $this->withColumnsField([
            ['key' => '_sec_filtros', 'label' => 'Filtros', 'type' => 'section'],
            [
                'key' => 'empresa_label',
                'label' => 'Empresa',
                'type' => 'static',
                'value' => $empresaLabel,
            ],
            ...$this->periodFilterFields(),
            [
                'key' => 'cliente_q',
                'label' => 'Cliente (código ou nome)',
                'type' => 'text',
                'placeholder' => 'Busca sob demanda',
            ],
            [
                'key' => 'vendedor',
                'label' => 'Vendedor',
                'type' => 'select',
                'options' => $this->vendedorOptions(),
            ],
            [
                'key' => 'tipo',
                'label' => 'Tipo de Relatório',
                'type' => 'select',
                'options' => self::tipoOptions(),
            ],
        ]);
    }

    public function build(Request $request): array
    {
        // Mesmo padrão da tela de filtros: dia atual (não início do mês).
        $hoje = ErpTimezone::toLocal()->startOfDay();
        [$de, $ate] = $this->periodFromRequest($request, $hoje, $hoje->copy());
        $deStr = $de->toDateString();
        $ateStr = $ate->toDateString();
        $tipo = self::normalizeTipo((string) $request->query('tipo', self::TIPO_DETALHADO));
        $colsRaw = $request->query('cols');
        $columns = $tipo === self::TIPO_RESUMIDO
            ? array_keys($this->resumidoColumns())
            : $this->resolveColumns(is_array($colsRaw) ? $colsRaw : null);
        $export = $request->boolean('export') || $request->boolean('csv') || $request->boolean('pdf');
        $filters = [
            'de' => $deStr,
            'ate' => $ateStr,
            'cliente_q' => trim((string) $request->query('cliente_q', '')),
            'vendedor' => (string) $request->query('vendedor', 'todos'),
            'tipo' => $tipo,
            'cols' => $tipo === self::TIPO_RESUMIDO
                ? array_keys($this->resumidoColumns())
                : $columns,
            'gerar' => '1',
        ];

        // Abertura sem gerar é redirecionada à tela compacta de filtros (controller).
        if (! $request->boolean('gerar')) {
            return [
                'rows' => [],
                'totals' => $this->emptyFooter($columns, blankLucro: true),
                'summary' => [],
                'filters' => array_merge($filters, ['gerar' => '0']),
                'columns' => $columns,
                'column_labels' => $tipo === self::TIPO_RESUMIDO ? $this->resumidoColumns() : [],
                'numeric_columns' => $this->numericColumns(),
                'meta' => [
                    'row_expand' => false,
                    'truncated' => false,
                    'total_count' => 0,
                    'detail_limit' => self::DETAIL_LIMIT,
                    'pending_generate' => true,
                    'tipo' => $tipo,
                    'empty_message' => 'Informe o período e clique em Gerar.',
                ],
            ];
        }

        // Visual: sem textos informativos entre o título e a tabela (só título + grade).
        $summary = [];

        $base = $this->baseSalesQuery($request, $deStr, $ateStr);

        $agg = (clone $base)->selectRaw('COUNT(*) as qtd, COALESCE(SUM('.static::sqlTable('vendas').'.total), 0) as bruto')->first();
        $qtd = (int) ($agg->qtd ?? 0);
        $bruto = TefMoney::normalize($agg->bruto ?? 0);

        $devUniverse = $this->sumDevolucoesReceitaSubquery($base);
        $receitaLiquidaUniverse = TefMoney::sub($bruto, $devUniverse);
        if (TefMoney::cmp($receitaLiquidaUniverse, '0') === -1) {
            $receitaLiquidaUniverse = '0.00';
        }

        if ($qtd === 0) {
            return [
                'rows' => [],
                'totals' => $this->emptyFooter($columns),
                'summary' => $summary,
                'filters' => $filters,
                'columns' => $columns,
                'column_labels' => $tipo === self::TIPO_RESUMIDO ? $this->resumidoColumns() : [],
                'numeric_columns' => $this->numericColumns(),
                'meta' => [
                    'row_expand' => false,
                    'truncated' => false,
                    'total_count' => 0,
                    'detail_limit' => self::DETAIL_LIMIT,
                    'tipo' => $tipo,
                ],
            ];
        }

        if ($tipo === self::TIPO_RESUMIDO) {
            return $this->buildResumido(
                $base,
                $qtd,
                $receitaLiquidaUniverse,
                $filters,
            );
        }

        $limit = self::DETAIL_LIMIT;
        $vendaIds = (clone $base)
            ->orderByDesc('vendas.data')
            ->orderByDesc('vendas.id')
            ->limit($limit)
            ->pluck('vendas.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $truncated = $qtd > count($vendaIds);

        // Totais de custo/lucro do rodapé: universo completo ou Indisponível (não mistura parcial da listagem).
        $universoCusto = $this->universeCustoLucro($base, $receitaLiquidaUniverse);

        $vendas = Venda::query()
            ->with([
                'cliente:id,nome_razao',
                'vendedor:id,nome',
            ])
            ->whereIn('id', $vendaIds)
            ->get(['id', 'numero', 'data', 'total', 'cliente_id', 'vendedor_id', 'vendedor_nome'])
            ->sortBy(fn (Venda $v): array => [
                -(strtotime((string) $v->data?->format('Y-m-d') ?: '1970-01-01')),
                -$v->id,
            ])
            ->values();

        $itensByVenda = $this->loadItensCustoByVendaIds($vendaIds, withProducts: $export);
        $devReceitaByVenda = $this->loadDevolucaoReceitaByVendaIds($vendaIds);
        $devItensByVenda = $this->loadDevolucaoItensCustoByVendaIds($vendaIds);

        $interactive = ! $export;
        $rows = [];

        foreach ($vendas as $venda) {
            $vid = (int) $venda->id;
            $brutoVenda = TefMoney::normalize($venda->total ?? 0);
            $devRec = TefMoney::normalize($devReceitaByVenda[$vid] ?? 0);
            $liquido = TefMoney::sub($brutoVenda, $devRec);
            if (TefMoney::cmp($liquido, '0') === -1) {
                $liquido = '0.00';
            }

            $resolved = LucratividadeVendaCustoResolver::resolver(
                $itensByVenda[$vid] ?? [],
                $devItensByVenda[$vid] ?? [],
            );

            if ($resolved['disponivel'] && $resolved['custo_liquido'] !== null) {
                $custoStr = $resolved['custo_liquido'];
                $lucroStr = TefMoney::sub($liquido, $custoStr);
                $margem = ((float) $liquido) > 0.0000001
                    ? (((float) $lucroStr) / (float) $liquido) * 100
                    : 0.0;
                $custoFmt = static::formatMoney((float) $custoStr);
                $lucroFmt = static::formatMoney((float) $lucroStr);
                $margemFmt = static::formatPercent($margem);
            } else {
                $custoFmt = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
                $lucroFmt = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
                $margemFmt = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            }

            $row = [
                'numero' => (string) $venda->numero,
                'data' => static::formatDate($venda->data),
                'cliente' => (string) ($venda->cliente?->nome_razao ?? 'CONSUMIDOR'),
                'vendedor' => $venda->vendedorNome(),
                'total_liquido' => static::formatMoney((float) $liquido),
                'custo_historico' => $custoFmt,
                'lucro' => $lucroFmt,
                'margem' => $margemFmt,
            ];

            if ($interactive) {
                $row['_expand'] = [
                    'venda_id' => $vid,
                    'mode' => 'produtos',
                ];
            }

            if ($export) {
                $row['_items'] = $this->mapExportItems(
                    $itensByVenda[$vid] ?? [],
                    (float) $brutoVenda,
                    $resolved['disponivel'],
                );
            }

            $rows[] = $row;
        }

        $footer = [];
        $labelPlaced = false;
        foreach ($columns as $column) {
            if ($column === 'total_liquido') {
                $footer[$column] = static::formatMoney((float) $receitaLiquidaUniverse);

                continue;
            }
            if ($column === 'custo_historico') {
                $footer[$column] = $universoCusto['disponivel']
                    ? static::formatMoney((float) $universoCusto['custo_liquido'])
                    : LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

                continue;
            }
            if ($column === 'lucro') {
                $footer[$column] = $universoCusto['disponivel']
                    ? static::formatMoney((float) $universoCusto['lucro'])
                    : LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

                continue;
            }
            if ($column === 'margem') {
                $footer[$column] = $universoCusto['disponivel']
                    ? static::formatPercent((float) $universoCusto['margem'])
                    : LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

                continue;
            }
            $footer[$column] = $labelPlaced ? '' : ('TOTAL ('.$qtd.')');
            $labelPlaced = true;
        }

        return [
            'rows' => $rows,
            'totals' => $footer,
            'summary' => $summary,
            'filters' => $filters,
            'columns' => $columns,
            'numeric_columns' => $this->numericColumns(),
            'meta' => [
                'row_expand' => $interactive,
                'row_expand_mode' => $interactive ? 'produtos' : '',
                'truncated' => $truncated,
                'total_count' => $qtd,
                'detail_limit' => $limit,
                'tipo' => self::TIPO_DETALHADO,
                'total_sum_formatted' => static::formatMoney((float) $receitaLiquidaUniverse),
            ],
        ];
    }

    /**
     * Totais do universo filtrado — sem listagem e sem truncamento.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildResumido(
        Builder $base,
        int $qtd,
        string $receitaLiquidaUniverse,
        array $filters,
    ): array {
        $columns = array_keys($this->resumidoColumns());
        $universoCusto = $this->universeCustoLucro($base, $receitaLiquidaUniverse);
        $row = $this->formatResumidoRow($qtd, $receitaLiquidaUniverse, $universoCusto);

        return [
            'rows' => [$row],
            'totals' => [],
            'summary' => [],
            'filters' => $filters,
            'columns' => $columns,
            'column_labels' => $this->resumidoColumns(),
            'numeric_columns' => $this->numericColumns(),
            'meta' => [
                'row_expand' => false,
                'truncated' => false,
                'total_count' => $qtd,
                'detail_limit' => 0,
                'tipo' => self::TIPO_RESUMIDO,
                'total_sum_formatted' => static::formatMoney((float) $receitaLiquidaUniverse),
            ],
        ];
    }

    /**
     * Monta a única linha do modo Resumido.
     * Sem custo completo → Indisponível em custo/lucro/margem (sem inventar lucro).
     *
     * @param  array{disponivel: bool, custo_liquido: string, lucro: string, margem: float, pendencia: string}  $universoCusto
     * @return array<string, string>
     */
    public function formatResumidoRow(int $qtd, string $receitaLiquida, array $universoCusto): array
    {
        $row = [
            'qtd_vendas' => static::formatQuantity((float) $qtd),
            'total_liquido' => static::formatMoney((float) $receitaLiquida),
        ];

        if ($universoCusto['disponivel']) {
            $row['custo_historico'] = static::formatMoney((float) $universoCusto['custo_liquido']);
            $row['lucro'] = static::formatMoney((float) $universoCusto['lucro']);
            $row['margem'] = static::formatPercent((float) $universoCusto['margem']);
        } else {
            $indisponivel = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $row['custo_historico'] = $indisponivel;
            $row['lucro'] = $indisponivel;
            $row['margem'] = $indisponivel;
        }

        return $row;
    }

    public function expandDetails(Request $request): array
    {
        $empty = [
            'rows' => [],
            'meta' => [
                'page' => 1,
                'per_page' => self::DETAILS_PER_PAGE,
                'total' => 0,
                'last_page' => 1,
                'has_more' => false,
                'mode' => 'produtos',
                'detail_columns' => $this->detailColumns(),
            ],
        ];

        $vendaId = (int) $request->query('venda_id', 0);
        if ($vendaId <= 0 || ! $request->boolean('gerar')) {
            return $empty;
        }

        $hoje = ErpTimezone::toLocal()->startOfDay();
        [$de, $ate] = $this->periodFromRequest($request, $hoje, $hoje->copy());
        $allowed = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString())
            ->where('vendas.id', $vendaId)
            ->exists();

        if (! $allowed) {
            return $empty;
        }

        $page = max(1, (int) $request->query('page', 1));
        $hasCusto = $this->schemaCustoVendaOk();

        $vendaTotal = (float) (Venda::query()->whereKey($vendaId)->value('total') ?? 0);

        $saleDisponivel = true;
        if ($hasCusto) {
            $itensCusto = DB::table('venda_itens')
                ->where('venda_id', $vendaId)
                ->get(['custo_situacao', 'custo_total']);
            $devItens = $this->loadDevolucaoItensCustoByVendaIds([$vendaId])[$vendaId] ?? [];
            $saleDisponivel = LucratividadeVendaCustoResolver::resolver(
                $itensCusto->all(),
                $devItens,
            )['disponivel'];
        } else {
            $saleDisponivel = false;
        }

        $query = VendaItem::query()
            ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
            ->where('venda_itens.venda_id', $vendaId)
            ->orderBy('venda_itens.id');

        $select = [
            'venda_itens.id',
            'products.codigo',
            'products.descricao',
            'products.grupo',
            'venda_itens.quantidade',
            'venda_itens.valor_item',
            'venda_itens.total',
        ];
        if ($hasCusto) {
            $select[] = 'venda_itens.custo_total';
            $select[] = 'venda_itens.custo_situacao';
        }

        // Rateio exige a venda inteira; depois pagina em memória (itens/venda são poucos).
        $all = $query->get($select);
        $totalCount = $all->count();
        $rateados = LucratividadeVendaReceitaRateio::totaisRateados(
            $vendaTotal,
            $all->map(static fn ($r) => $r->total ?? 0)->all(),
        );

        $slice = $all->slice(($page - 1) * self::DETAILS_PER_PAGE, self::DETAILS_PER_PAGE)->values();
        $rows = [];
        foreach ($slice as $offset => $row) {
            $globalIndex = (($page - 1) * self::DETAILS_PER_PAGE) + $offset;
            $rows[] = $this->formatDetailRow(
                $row,
                $rateados[$globalIndex] ?? TefMoney::normalize($row->total ?? 0),
                $hasCusto,
                $saleDisponivel,
            );
        }

        $lastPage = max(1, (int) ceil($totalCount / self::DETAILS_PER_PAGE));

        return [
            'rows' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => self::DETAILS_PER_PAGE,
                'total' => $totalCount,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage,
                'mode' => 'produtos',
                'detail_columns' => $this->detailColumns(),
            ],
        ];
    }

    /**
     * @return list<array{key: string, label: string, num: bool}>
     */
    private function detailColumns(): array
    {
        return [
            ['key' => 'codigo', 'label' => 'CÓDIGO', 'num' => false],
            ['key' => 'descricao', 'label' => 'DESCRIÇÃO', 'num' => false],
            ['key' => 'grupo', 'label' => 'GRUPO', 'num' => false],
            ['key' => 'qtd', 'label' => 'QTD', 'num' => true],
            ['key' => 'unitario', 'label' => 'UNIT.', 'num' => true],
            ['key' => 'total', 'label' => 'TOTAL ITEM', 'num' => true],
            ['key' => 'custo', 'label' => 'CUSTO HIST.', 'num' => true],
            ['key' => 'lucro', 'label' => 'LUCRO R$', 'num' => true],
            ['key' => 'margem', 'label' => 'MARGEM %', 'num' => true],
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @return array{disponivel: bool, custo_liquido: string, lucro: string, margem: float, pendencia: string}
     */
    private function universeCustoLucro(Builder $base, string $receitaLiquida): array
    {
        $vazio = [
            'disponivel' => false,
            'custo_liquido' => '0.00',
            'lucro' => '0.00',
            'margem' => 0.0,
            'pendencia' => '',
        ];

        if (! $this->schemaCustoVendaOk()) {
            $vazio['pendencia'] = 'Schema de custo histórico indisponível.';

            return $vazio;
        }

        $ids = (clone $base)->select('vendas.id');

        // Venda sem item OU com item elegível sem captura/ok incompleto.
        $vendaIncompleta = DB::table('vendas')
            ->whereIn('vendas.id', $ids)
            ->where(function ($q): void {
                $q->whereNotExists(function ($exists): void {
                    $exists->selectRaw('1')
                        ->from('venda_itens')
                        ->whereColumn('venda_itens.venda_id', 'vendas.id');
                })->orWhereExists(function ($exists): void {
                    $exists->selectRaw('1')
                        ->from('venda_itens')
                        ->whereColumn('venda_itens.venda_id', 'vendas.id')
                        ->where(function ($inner): void {
                            $inner->whereNull('venda_itens.custo_situacao')
                                ->orWhere('venda_itens.custo_situacao', '')
                                ->orWhere('venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_INDISPONIVEL)
                                ->orWhere(function ($ok): void {
                                    $ok->where('venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_OK)
                                        ->whereNull('venda_itens.custo_total');
                                });
                        });
                })->orWhere(function ($q2): void {
                    // Tem itens, nenhum ok e nenhum nao_aplicavel (status estranho).
                    $q2->whereExists(function ($exists): void {
                        $exists->selectRaw('1')->from('venda_itens')->whereColumn('venda_itens.venda_id', 'vendas.id');
                    })->whereNotExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('venda_itens')
                            ->whereColumn('venda_itens.venda_id', 'vendas.id')
                            ->where('venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_OK)
                            ->whereNotNull('venda_itens.custo_total');
                    })->whereNotExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('venda_itens')
                            ->whereColumn('venda_itens.venda_id', 'vendas.id')
                            ->where('venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_NAO_APLICAVEL);
                    });
                });
            })
            ->limit(1)
            ->exists();

        if ($vendaIncompleta) {
            $vazio['pendencia'] = 'Há vendas sem custo histórico completo no período.';

            return $vazio;
        }

        if (Schema::hasTable('devolucoes_venda') && Schema::hasTable('devolucao_venda_itens')) {
            // Bloqueia se houver devolução finalizada sem itens (com valor) ou com item sem custo confiável.
            // Itens só nao_aplicavel não disparam o EXISTS de custo ruim.
            $devIncompleta = DB::table('devolucoes_venda')
                ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
                ->whereIn('venda_id', $ids)
                ->where(function ($q): void {
                    $q->where(function ($semItens): void {
                        $semItens->where('total', '>', 0)
                            ->whereNotExists(function ($exists): void {
                                $exists->selectRaw('1')
                                    ->from('devolucao_venda_itens')
                                    ->whereColumn('devolucao_venda_itens.devolucao_venda_id', 'devolucoes_venda.id');
                            });
                    })->orWhereExists(function ($exists): void {
                        $exists->selectRaw('1')
                            ->from('devolucao_venda_itens')
                            ->whereColumn('devolucao_venda_itens.devolucao_venda_id', 'devolucoes_venda.id')
                            ->where(function ($inner): void {
                                $inner->whereNull('devolucao_venda_itens.custo_situacao')
                                    ->orWhere('devolucao_venda_itens.custo_situacao', '')
                                    ->orWhere('devolucao_venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_INDISPONIVEL)
                                    ->orWhere(function ($ok): void {
                                        $ok->where('devolucao_venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_OK)
                                            ->whereNull('devolucao_venda_itens.custo_total');
                                    });
                            });
                    });
                })
                ->limit(1)
                ->exists();

            if ($devIncompleta) {
                $vazio['pendencia'] = 'Há devoluções sem custo histórico completo.';

                return $vazio;
            }
        }

        $vi = static::sqlTable('venda_itens');
        $custoVendas = TefMoney::normalize(DB::table('venda_itens')
            ->whereIn('venda_id', $ids)
            ->where('custo_situacao', VendaItemCustoHistorico::SITUACAO_OK)
            ->whereNotNull('custo_total')
            ->selectRaw("COALESCE(SUM({$vi}.custo_total), 0) as t")
            ->value('t') ?? 0);

        $custoDev = '0.00';
        if (Schema::hasTable('devolucao_venda_itens') && Schema::hasTable('devolucoes_venda')) {
            $di = static::sqlTable('devolucao_venda_itens');
            $custoDev = TefMoney::normalize(DB::table('devolucao_venda_itens')
                ->join('devolucoes_venda', 'devolucoes_venda.id', '=', 'devolucao_venda_itens.devolucao_venda_id')
                ->where('devolucoes_venda.situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
                ->whereIn('devolucoes_venda.venda_id', $ids)
                ->where('devolucao_venda_itens.custo_situacao', VendaItemCustoHistorico::SITUACAO_OK)
                ->whereNotNull('devolucao_venda_itens.custo_total')
                ->selectRaw("COALESCE(SUM({$di}.custo_total), 0) as t")
                ->value('t') ?? 0);
        }

        $custoLiq = TefMoney::sub($custoVendas, $custoDev);
        if (TefMoney::cmp($custoLiq, '0') === -1) {
            $custoLiq = '0.00';
        }
        $lucro = TefMoney::sub($receitaLiquida, $custoLiq);
        $margem = ((float) $receitaLiquida) > 0.0000001
            ? (((float) $lucro) / (float) $receitaLiquida) * 100
            : 0.0;

        return [
            'disponivel' => true,
            'custo_liquido' => $custoLiq,
            'lucro' => $lucro,
            'margem' => $margem,
            'pendencia' => '',
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     */
    private function sumDevolucoesReceitaSubquery(Builder $base): string
    {
        if (! Schema::hasTable('devolucoes_venda')) {
            return '0.00';
        }

        $d = static::sqlTable('devolucoes_venda');
        $total = (float) (DB::table('devolucoes_venda')
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereNotNull('venda_id')
            ->whereIn('venda_id', (clone $base)->select('vendas.id'))
            ->selectRaw("COALESCE(SUM({$d}.total), 0) as t")
            ->value('t') ?? 0);

        return TefMoney::normalize($total);
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, list<object>>
     */
    private function loadItensCustoByVendaIds(array $vendaIds, bool $withProducts = false): array
    {
        $map = [];
        foreach ($vendaIds as $id) {
            $map[$id] = [];
        }
        if ($vendaIds === [] || ! Schema::hasTable('venda_itens')) {
            return $map;
        }

        $select = ['venda_itens.venda_id', 'venda_itens.quantidade', 'venda_itens.valor_item', 'venda_itens.total'];
        if ($this->schemaCustoVendaOk()) {
            $select[] = 'venda_itens.custo_situacao';
            $select[] = 'venda_itens.custo_total';
        }

        $query = DB::table('venda_itens')->whereIn('venda_itens.venda_id', $vendaIds)->orderBy('venda_itens.id');
        if ($withProducts) {
            $query->leftJoin('products', 'products.id', '=', 'venda_itens.product_id');
            $select[] = 'products.codigo';
            $select[] = 'products.descricao';
            $select[] = 'products.grupo';
        }

        foreach ($query->get($select) as $row) {
            $map[(int) $row->venda_id][] = $row;
        }

        return $map;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, float|string>
     */
    private function loadDevolucaoReceitaByVendaIds(array $vendaIds): array
    {
        if ($vendaIds === [] || ! Schema::hasTable('devolucoes_venda')) {
            return [];
        }

        $out = [];
        DB::table('devolucoes_venda')
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereIn('venda_id', $vendaIds)
            ->selectRaw('venda_id, COALESCE(SUM(total), 0) as total_dev')
            ->groupBy('venda_id')
            ->get()
            ->each(function ($row) use (&$out): void {
                $out[(int) $row->venda_id] = $row->total_dev;
            });

        return $out;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, list<object>>
     */
    private function loadDevolucaoItensCustoByVendaIds(array $vendaIds): array
    {
        $map = [];
        foreach ($vendaIds as $id) {
            $map[$id] = [];
        }

        if ($vendaIds === []
            || ! Schema::hasTable('devolucoes_venda')
            || ! Schema::hasTable('devolucao_venda_itens')
        ) {
            return $map;
        }

        $hasCusto = Schema::hasColumn('devolucao_venda_itens', 'custo_situacao')
            && Schema::hasColumn('devolucao_venda_itens', 'custo_total');

        $select = [
            'devolucoes_venda.venda_id',
            'devolucao_venda_itens.id',
        ];
        if ($hasCusto) {
            $select[] = 'devolucao_venda_itens.custo_situacao';
            $select[] = 'devolucao_venda_itens.custo_total';
        }

        $rows = DB::table('devolucao_venda_itens')
            ->join('devolucoes_venda', 'devolucoes_venda.id', '=', 'devolucao_venda_itens.devolucao_venda_id')
            ->where('devolucoes_venda.situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereIn('devolucoes_venda.venda_id', $vendaIds)
            ->get($select);

        foreach ($rows as $row) {
            $vid = (int) $row->venda_id;
            if (! $hasCusto) {
                $map[$vid][] = (object) [
                    'custo_situacao' => null,
                    'custo_total' => null,
                ];

                continue;
            }
            $map[$vid][] = $row;
        }

        $devsComReceita = $this->loadDevolucaoReceitaByVendaIds($vendaIds);
        foreach ($devsComReceita as $vid => $total) {
            if (TefMoney::cmp(TefMoney::normalize($total), '0') === 1 && ($map[$vid] ?? []) === []) {
                $map[$vid][] = (object) [
                    'custo_situacao' => null,
                    'custo_total' => null,
                ];
            }
        }

        return $map;
    }

    /**
     * @param  list<object>  $itens
     * @return list<array<string, string>>
     */
    private function mapExportItems(array $itens, float $vendaTotal, bool $saleDisponivel): array
    {
        $rateados = LucratividadeVendaReceitaRateio::totaisRateados(
            $vendaTotal,
            array_map(static fn ($row) => $row->total ?? 0, $itens),
        );

        $out = [];
        foreach ($itens as $i => $row) {
            $out[] = $this->formatDetailRow(
                $row,
                $rateados[$i] ?? TefMoney::normalize($row->total ?? 0),
                $this->schemaCustoVendaOk(),
                $saleDisponivel,
            );
        }

        return $out;
    }

    /**
     * @param  object  $row
     * @return array<string, string>
     */
    private function formatDetailRow(object $row, string $totalRateado, bool $hasCusto, bool $saleDisponivel): array
    {
        $base = [
            'codigo' => (string) ($row->codigo ?? ''),
            'descricao' => (string) ($row->descricao ?? ''),
            'grupo' => (string) ($row->grupo ?? ''),
            'qtd' => self::formatQuantity((float) ($row->quantidade ?? 0)),
            'unitario' => self::formatMoney((float) ($row->valor_item ?? 0)),
            'total' => self::formatMoney((float) $totalRateado),
        ];

        if (! $hasCusto) {
            $base['custo'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $base['lucro'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $base['margem'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

            return $base;
        }

        $agg = LucratividadeVendaCustoResolver::agregar([[
            'custo_situacao' => $row->custo_situacao ?? null,
            'custo_total' => $row->custo_total ?? null,
        ]]);

        if (! $agg['completo']) {
            $base['custo'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $base['lucro'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $base['margem'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

            return $base;
        }

        $base['custo'] = self::formatMoney((float) $agg['custo']);

        // Sem lucro numérico se a venda (ou devolução) não tem custo confiável no fechamento.
        if (! $saleDisponivel) {
            $base['lucro'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            $base['margem'] = LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;

            return $base;
        }

        $lucro = TefMoney::sub($totalRateado, $agg['custo']);
        $margem = ((float) $totalRateado) > 0.0000001
            ? (((float) $lucro) / (float) $totalRateado) * 100
            : 0.0;
        $base['lucro'] = self::formatMoney((float) $lucro);
        $base['margem'] = self::formatPercent($margem);

        return $base;
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function baseSalesQuery(Request $request, string $de, string $ate): Builder
    {
        $query = Venda::query()
            ->where('vendas.status', Venda::STATUS_FECHADO)
            ->whereBetween('vendas.data', [$de, $ate]);

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId && Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $query->where('vendas.empresa_id', $empresaId);
        } elseif (Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $query->whereRaw('1 = 0');
        }

        $vendedorId = (int) $request->query('vendedor', 0);
        if ($vendedorId > 0) {
            $query->where('vendas.vendedor_id', $vendedorId);
        }

        $clienteQ = trim((string) $request->query('cliente_q', ''));
        if ($clienteQ !== '') {
            $query->whereHas('cliente', function (Builder $q) use ($clienteQ): void {
                $like = '%'.$clienteQ.'%';
                $q->where(function (Builder $inner) use ($clienteQ, $like): void {
                    $inner->where('nome_razao', 'like', $like)
                        ->orWhere('apelido_fantasia', 'like', $like);
                    if (ctype_digit($clienteQ)) {
                        $inner->orWhere('codigo', $clienteQ)->orWhere('id', (int) $clienteQ);
                    } else {
                        $inner->orWhere('codigo', 'like', $like);
                    }
                });
            });
        }

        return $query;
    }

    private function schemaCustoVendaOk(): bool
    {
        return Schema::hasTable('venda_itens')
            && Schema::hasColumn('venda_itens', 'custo_situacao')
            && Schema::hasColumn('venda_itens', 'custo_total');
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function emptyFooter(array $columns, bool $blankLucro = false): array
    {
        $footer = [];
        $labelPlaced = false;
        foreach ($columns as $column) {
            if ($column === 'total_liquido') {
                $footer[$column] = static::formatMoney(0.0);

                continue;
            }
            if (in_array($column, ['custo_historico', 'lucro', 'margem'], true)) {
                $footer[$column] = $blankLucro
                    ? LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL
                    : static::formatMoney(0.0);

                continue;
            }
            $footer[$column] = $labelPlaced ? '' : 'TOTAL (0)';
            $labelPlaced = true;
        }

        return $footer;
    }

    /**
     * @return array<string, string>
     */
    private function vendedorOptions(): array
    {
        $options = ['todos' => 'Todos'];
        $empresaId = ErpContext::currentEmpresaId();
        $query = Vendedor::query()
            ->where('ativo', true)
            ->where('efetua_venda', true)
            ->orderBy('nome');

        if ($empresaId) {
            $query->whereHas('empresas', fn ($q) => $q->where('empresas.id', $empresaId));
        }

        $query->limit(500)->get(['id', 'nome', 'codigo'])->each(function (Vendedor $v) use (&$options): void {
            $codigo = trim((string) ($v->codigo ?? ''));
            $nome = trim((string) ($v->nome ?? ''));
            $options[(string) $v->id] = trim(($codigo !== '' ? $codigo.' - ' : '').$nome) ?: ('#'.$v->id);
        });

        return $options;
    }
}
