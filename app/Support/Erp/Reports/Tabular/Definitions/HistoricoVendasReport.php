<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\DevolucaoVenda;
use App\Models\FormaPagamento;
use App\Models\Nfe;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaCustoResolver;
use App\Support\Erp\Reports\LucratividadePorVenda\LucratividadeVendaMargemBatch;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use App\Support\Erp\Reports\Tabular\ChartableTabularReport;
use App\Support\Erp\Reports\Tabular\Concerns\BuildsReportChartScope;
use App\Support\Erp\Reports\Tabular\ExpandableTabularReport;
use App\Support\Erp\Reports\VendasPorFormaPagamentoAggregator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricoVendasReport extends AbstractTabularReport implements ChartableTabularReport, ExpandableTabularReport
{
    use BuildsReportChartScope;

    private const DETAIL_LIMIT = 3000;

    private const DETAILS_PER_PAGE = 80;

    /** @var list<string> */
    private const CHART_COLORS = [
        '#1e5a9e',
        '#15803d',
        '#b45309',
        '#7c3aed',
        '#be123c',
        '#0f766e',
        '#0369a1',
        '#a16207',
    ];

    public function slug(): string
    {
        return 'historico-vendas';
    }

    public function title(): string
    {
        return 'RELATÓRIO DE VENDAS GERAL';
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

    public function paperOrientation(): string
    {
        return 'portrait';
    }

    public function columns(): array
    {
        return [
            'numero' => 'NÚMERO',
            'data' => 'DATA',
            'cliente' => 'CLIENTE',
            'cidade' => 'CIDADE',
            'vendedor' => 'VENDEDOR',
            'forma' => 'FORMA PAGTO',
            'tipo' => 'TIPO',
            'plataforma' => 'ORIGEM',
            'doc_fiscal' => 'DOC. FISCAL',
            'status' => 'STATUS',
            'total' => 'TOTAL',
            'pct_lucro' => '% LUCRO',
            'grupo' => 'GRUPO',
            'qtd' => 'QTD',
            'dia' => 'DIA',
        ];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'data',
            'cliente',
            'vendedor',
            'forma',
            'plataforma',
            'status',
            'total',
        ];
    }

    public function numericColumns(): array
    {
        return ['total', 'qtd', 'pct_lucro'];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 7,
            'data' => 8,
            'cliente' => 28,
            'cidade' => 12,
            'vendedor' => 14,
            'forma' => 12,
            'tipo' => 8,
            'plataforma' => 8,
            'doc_fiscal' => 10,
            'status' => 8,
            'total' => 10,
            'pct_lucro' => 8,
            'grupo' => 28,
            'qtd' => 6,
            'dia' => 10,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'data', 'status', 'total', 'pct_lucro', 'plataforma', 'forma'];
    }

    public function filterFields(): array
    {
        $empresa = ErpContext::currentEmpresa();
        $empresaLabel = $empresa
            ? trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)))
            : '—';

        return [
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
                'key' => 'cidade_q',
                'label' => 'Cidade do cliente',
                'type' => 'text',
                'placeholder' => 'Nome da cidade',
            ],
            [
                'key' => 'grupo',
                'label' => 'Grupo de produtos',
                'type' => 'select',
                'options' => $this->grupoOptions(),
            ],
            [
                'key' => 'caixa',
                'label' => 'Caixa (PDV)',
                'type' => 'select',
                'options' => $this->caixaOptions(),
            ],
            [
                'key' => 'forma',
                'label' => 'Forma de pagamento',
                'type' => 'select',
                'options' => $this->formaOptions(),
            ],
            [
                'key' => 'usuario',
                'label' => 'Usuário responsável (PDV)',
                'type' => 'select',
                'options' => $this->usuarioOptions(),
            ],
            [
                'key' => 'numero_q',
                'label' => 'Nº venda / documento fiscal',
                'type' => 'text',
                'placeholder' => 'Número da venda, NF-e ou NFC-e',
            ],
            [
                'key' => 'origem',
                'label' => 'Origem da venda',
                'type' => 'select',
                'options' => ['todos' => 'Todas'] + Venda::plataformaLabels(),
            ],
            [
                'key' => 'status',
                'label' => 'Situação',
                'type' => 'select',
                'options' => ['todos' => 'Todos'] + Venda::statusLabels(),
            ],
            [
                'key' => 'doc_tipo',
                'label' => 'Tipo de documento fiscal',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todos',
                    'nfe' => 'Com NF-e',
                    'nfce' => 'Com NFC-e',
                    'sem' => 'Sem documento fiscal',
                ],
            ],
            ['key' => '_sec_params', 'label' => 'Parâmetros', 'type' => 'section'],
            [
                'key' => 'exibir_produtos',
                'label' => 'Exibir produtos vendidos',
                'type' => 'checkbox',
            ],
            [
                'key' => 'mostrar_resumo',
                'label' => 'Mostrar resumo',
                'type' => 'checkbox',
            ],
            [
                'key' => 'apenas_resumo',
                'label' => 'Exibir apenas resumo',
                'type' => 'checkbox',
            ],
            [
                'key' => 'exibir_formas',
                'label' => 'Exibir formas de pagamento (por venda)',
                'type' => 'checkbox',
            ],
            [
                'key' => 'exibir_grafico',
                'label' => 'Exibir análise gráfica diária',
                'type' => 'checkbox',
            ],
            [
                'key' => 'exibir_resumo_hora',
                'label' => 'Exibir resumo por hora',
                'type' => 'checkbox',
            ],
            [
                'key' => 'exibir_pct_lucro',
                'label' => 'Exibir % de lucro',
                'type' => 'checkbox',
            ],
            ['key' => '_sec_group', 'label' => 'Agrupamento', 'type' => 'section'],
            [
                'key' => 'agrupar',
                'label' => 'Agrupar por',
                'type' => 'select',
                'options' => [
                    'nenhum' => 'Nenhum',
                    'cliente' => 'Cliente',
                    'cidade' => 'Cidade do cliente',
                    'vendedor' => 'Vendedor',
                    'forma' => 'Forma de pagamento',
                    'dia' => 'Dia',
                ],
            ],
        ];
    }

    public function chartConfig(): array
    {
        return [
            'type' => 'bar',
            'show_empresa_select' => false,
            'empresas' => [],
            'default_empresa' => 'todas',
            'supports_yoy' => false,
            'supports_granularity' => false,
            'default_granularity' => 'day',
            'title' => 'Análise Gráfica Diária',
        ];
    }

    public function chartData(Request $request): array
    {
        if (! $request->boolean('gerar') || ! $request->boolean('exibir_grafico')) {
            return [
                'labels' => [],
                'datasets' => [],
                'meta' => [
                    'cards' => [],
                    'period_label' => '',
                ],
            ];
        }

        $payload = $this->buildDailyChartPayload($request);

        return [
            'labels' => $payload['labels'],
            'datasets' => [$this->barDataset('Total do dia', $payload['values'])],
            'meta' => [
                'title' => 'Análise Gráfica Diária',
                'de' => $payload['de'],
                'ate' => $payload['ate'],
                'period_label' => $payload['period_label'],
                'granularity' => 'day',
                'clamped' => $payload['clamped'],
                'cards' => $this->chartSummaryCards($payload['labels'], [[
                    'data' => $payload['values'],
                ]]),
            ],
        ];
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $filters = $this->filtersSnapshot($request, $de, $ate);

        if (! $request->boolean('gerar')) {
            return [
                'rows' => [],
                'totals' => [],
                'summary' => [
                    'EMPRESA: '.$this->empresaAtualLabel(),
                    'PERÍODO: '.$this->periodLabel($de, $ate),
                    'AGUARDANDO GERAÇÃO',
                ],
                'filters' => $filters,
                'columns' => $this->defaultColumns(),
                'numeric_columns' => $this->numericColumns(),
                'meta' => [
                    'pending_message' => 'Defina os filtros e clique em Gerar Relatório. Nenhuma consulta de vendas foi executada.',
                    'show_chart' => false,
                    'row_expand' => false,
                    'truncated' => false,
                ],
                'extra_sections' => [],
            ];
        }

        $agrupar = $this->agruparFromRequest($request);
        $apenasResumo = $request->boolean('apenas_resumo');
        $mostrarResumo = $request->boolean('mostrar_resumo') || $apenasResumo;
        $exibirProdutos = $request->boolean('exibir_produtos') && $agrupar === 'nenhum' && ! $apenasResumo;
        $exibirFormas = $request->boolean('exibir_formas');
        $exibirGrafico = $request->boolean('exibir_grafico');
        $exibirResumoHora = $request->boolean('exibir_resumo_hora');
        $exibirPctLucro = $request->boolean('exibir_pct_lucro') && $agrupar === 'nenhum' && ! $apenasResumo;
        $export = $request->boolean('pdf') || $request->boolean('csv');

        $base = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString());
        $totals = $this->aggregateUniverseTotals(clone $base);
        $summary = $this->buildHeaderSummary($request, $de, $ate, $agrupar);
        $hourlySummary = $exibirResumoHora
            ? $this->buildHourlySummary(clone $base, $totals)
            : null;

        // Análises exclusivas: diária e/ou por hora — sem listagem/resumo comercial.
        if ($exibirGrafico || $exibirResumoHora) {
            $dailyChart = $exibirGrafico ? $this->buildDailyChartPayload($request) : null;

            return [
                'rows' => [],
                'totals' => [],
                'summary' => $summary,
                'filters' => $filters,
                'columns' => ['dia', 'total'],
                'column_labels' => ['dia' => 'DIA', 'total' => 'TOTAL'],
                'numeric_columns' => ['total'],
                'meta' => [
                    'hide_main_table' => true,
                    'chart_only' => true,
                    'daily_chart' => $dailyChart,
                    'hourly_summary' => $hourlySummary,
                    'show_chart' => $exibirGrafico,
                    'row_expand' => false,
                    'truncated' => false,
                    'total_count' => $totals['qtd'],
                    'total_sum_formatted' => static::formatMoney($totals['total']),
                    'empty_message' => $exibirResumoHora && ! $exibirGrafico
                        ? 'Resumo por hora gerado.'
                        : 'Análise gráfica gerada.',
                ],
                'extra_sections' => [],
            ];
        }

        $totaisDetalhe = $this->aggregateTotaisDetalhe(clone $base, $totals);
        $trocoPdv = $this->aggregatePdvTrocoTotal(clone $base);
        $devolucoes = $this->aggregateDevolucoesPeriodo($de->toDateString(), $ate->toDateString());

        // Resumo consolidado de meios ≠ detalhe por venda (exibir_formas).
        $incluirFormasResumo = $mostrarResumo && $agrupar !== 'forma';

        $extraSections = $this->buildCommercialSummarySections(
            $request,
            clone $base,
            $totals,
            $totaisDetalhe,
            $trocoPdv,
            $devolucoes,
            $mostrarResumo,
            $incluirFormasResumo,
        );

        $sectionMeta = [
            'extra_sections_before' => $extraSections !== [],
            'hide_main_table' => $apenasResumo,
            'daily_chart' => null,
            'hourly_summary' => null,
        ];

        if ($apenasResumo) {
            return [
                'rows' => [],
                'totals' => [],
                'summary' => $summary,
                'filters' => $filters,
                'columns' => ['numero', 'total'],
                'column_labels' => ['numero' => 'RESUMO', 'total' => 'TOTAL'],
                'numeric_columns' => ['total'],
                'meta' => array_merge($sectionMeta, [
                    'show_chart' => $exibirGrafico,
                    'row_expand' => false,
                    'truncated' => false,
                    'total_count' => $totals['qtd'],
                    'total_sum_formatted' => static::formatMoney($totals['total']),
                    'empty_message' => 'Resumo gerado. Detalhamento oculto pelo parâmetro “Exibir apenas resumo”.',
                ]),
                'extra_sections' => $extraSections,
            ];
        }

        if ($agrupar === 'forma') {
            $section = $this->formasPagamentoSection(clone $base, $request, $totals, $trocoPdv);
            $rows = $section['rows'] ?? [];
            $columns = $section['columns'] ?? ['forma', 'valor', 'percentual'];
            $labels = $section['column_labels'] ?? [];
            $numeric = $section['numeric_columns'] ?? ['valor', 'percentual'];
            $footer = $section['totals'] ?? [];

            return [
                'rows' => $rows,
                'totals' => $footer,
                'summary' => array_merge($summary, [
                    'AGRUPAMENTO: FORMA DE PAGAMENTO',
                    'TOTAL COMERCIAL (vendas.total): '.static::formatMoney($totals['total']).' | QTD VENDAS: '.$totals['qtd'],
                ]),
                'filters' => $filters,
                'columns' => $columns,
                'column_labels' => $labels,
                'numeric_columns' => $numeric,
                'meta' => array_merge($sectionMeta, [
                    'show_chart' => $exibirGrafico,
                    'row_expand' => false,
                    'truncated' => false,
                    'total_count' => $totals['qtd'],
                    'total_sum_formatted' => static::formatMoney($totals['total']),
                ]),
                'extra_sections' => $extraSections,
            ];
        }

        if (in_array($agrupar, ['cliente', 'cidade', 'vendedor', 'dia'], true)) {
            return $this->buildGrouped($request, $base, $filters, $summary, $agrupar, $totals, $extraSections, $exibirGrafico, $export, $sectionMeta);
        }

        return $this->buildDetailList(
            $request,
            $base,
            $filters,
            $summary,
            $totals,
            $extraSections,
            $exibirProdutos,
            $exibirFormas,
            $exibirGrafico,
            $exibirPctLucro,
            $export,
            $mostrarResumo,
            $sectionMeta,
        );
    }

    public function expandDetails(Request $request): array
    {
        $vendaId = (int) $request->query('venda_id', 0);
        if ($vendaId <= 0 || ! $request->boolean('gerar')) {
            return ['rows' => [], 'meta' => ['page' => 1, 'per_page' => self::DETAILS_PER_PAGE, 'total' => 0, 'last_page' => 1, 'has_more' => false]];
        }

        [$de, $ate] = $this->periodFromRequest($request);
        $allowed = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString())
            ->where('vendas.id', $vendaId)
            ->exists();

        if (! $allowed) {
            return ['rows' => [], 'meta' => ['page' => 1, 'per_page' => self::DETAILS_PER_PAGE, 'total' => 0, 'last_page' => 1, 'has_more' => false]];
        }

        $page = max(1, (int) $request->query('page', 1));
        $query = VendaItem::query()
            ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
            ->where('venda_itens.venda_id', $vendaId)
            ->orderBy('venda_itens.id');

        $totalCount = (clone $query)->count();
        $rows = $query
            ->forPage($page, self::DETAILS_PER_PAGE)
            ->get([
                'venda_itens.id',
                'products.codigo',
                'products.descricao',
                'products.grupo',
                'venda_itens.quantidade',
                'venda_itens.valor_item',
                'venda_itens.total',
            ])
            ->map(function ($row): array {
                return [
                    'codigo' => (string) ($row->codigo ?? ''),
                    'descricao' => (string) ($row->descricao ?? ''),
                    'grupo' => (string) ($row->grupo ?? ''),
                    'qtd' => self::formatQuantity((float) $row->quantidade),
                    'unitario' => self::formatMoney((float) $row->valor_item),
                    'total' => self::formatMoney((float) $row->total),
                ];
            })
            ->all();

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
            ],
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $summary
     * @param  array{qtd: int, total: float}  $totals
     * @param  list<array<string, mixed>>  $extraSections
     * @return array<string, mixed>
     */
    private function buildDetailList(
        Request $request,
        Builder $base,
        array $filters,
        array $summary,
        array $totals,
        array $extraSections,
        bool $exibirProdutos,
        bool $exibirFormas,
        bool $exibirGrafico,
        bool $exibirPctLucro,
        bool $export,
        bool $mostrarResumo,
        array $sectionMeta = [],
    ): array {
        $columns = $this->resolveDetailColumns($request, $exibirPctLucro);
        $limit = self::DETAIL_LIMIT;
        $isCsv = $request->boolean('csv');

        $idsQuery = (clone $base)->orderByDesc('vendas.data')->orderByDesc('vendas.id');
        $vendaIds = $idsQuery->limit($limit)->pluck('vendas.id')->map(fn ($id): int => (int) $id)->all();
        $truncated = $totals['qtd'] > count($vendaIds);

        if ($truncated) {
            $summary[] = 'LISTAGEM TRUNCADA: exibindo '.count($vendaIds).' de '.$totals['qtd']
                .' venda(s). Os totalizadores abaixo referem-se ao universo filtrado completo.';
            if ($export) {
                $summary[] = 'EXPORTAÇÃO PARCIAL — este arquivo não contém todas as vendas do filtro.';
            }
        }

        if ($exibirPctLucro) {
            $summary[] = '% LUCRO: ((venda líquida − custo D2.6) / custo D2.6) × 100 — igual à Lucratividade por Venda; sem custo confiável = Indisponível';
        }

        $vendas = $vendaIds === []
            ? collect()
            : Venda::query()
                ->with([
                    'cliente:id,nome_razao,cidade_nome',
                    'vendedor:id,nome',
                    'pdvVenda:id,venda_id,origem',
                    'pdvVenda.nfce:id,pdv_venda_id,numero,status',
                    'nfes:id,venda_id,numero,status',
                    'forcaVendasOrder:id,venda_id,device_uuid',
                    'ordemServico:id,venda_id',
                ])
                ->whereIn('id', $vendaIds)
                ->get()
                ->sortBy(fn (Venda $v): array => [-(strtotime((string) $v->data?->format('Y-m-d') ?: '1970-01-01')), -$v->id])
                ->values();

        $itemsByVenda = [];
        if ($exibirProdutos && $vendaIds !== [] && $export) {
            $itemsByVenda = $this->loadItemsByVendaIds($vendaIds);
        }

        // Formas por venda: viewer + PDF/impressão. CSV omite (estrutura plana).
        $pagamentosByVenda = [];
        if ($exibirFormas && $vendaIds !== [] && ! $isCsv) {
            $pagamentosByVenda = $this->loadPagamentosByVendaIds($vendaIds);
        }

        // Custo histórico só quando a flag está marcada (performance).
        $pctLucroByVenda = [];
        if ($exibirPctLucro && $vendaIds !== []) {
            $totaisBrutos = [];
            foreach ($vendas as $venda) {
                $totaisBrutos[(int) $venda->id] = $venda->total ?? 0;
            }
            $pctLucroByVenda = LucratividadeVendaMargemBatch::rotulosPorVenda($totaisBrutos);
        }

        $interactive = ! $export;
        $rows = [];
        foreach ($vendas as $venda) {
            $vid = (int) $venda->id;
            $row = [
                'numero' => (string) $venda->numero,
                'data' => static::formatDate($venda->data),
                'cliente' => (string) ($venda->cliente?->nome_razao ?? ''),
                'cidade' => (string) ($venda->cliente?->cidade_nome ?? ''),
                'vendedor' => $venda->vendedorNome(),
                'forma' => (string) ($venda->forma_pagamento ?? ''),
                'tipo' => Venda::tipoLabels()[$venda->tipo] ?? (string) $venda->tipo,
                'plataforma' => $venda->plataformaLabel(),
                'doc_fiscal' => $this->docFiscalLabel($venda),
                'status' => Venda::statusLabels()[$venda->status] ?? (string) $venda->status,
                'total' => static::formatMoney((float) $venda->total),
            ];

            if ($exibirPctLucro) {
                $row['pct_lucro'] = $pctLucroByVenda[$vid]
                    ?? LucratividadeVendaCustoResolver::LABEL_INDISPONIVEL;
            }

            if ($exibirProdutos && $interactive) {
                $row['_expand'] = [
                    'venda_id' => $vid,
                    'mode' => 'produtos',
                ];
            }

            if ($exibirProdutos && $export) {
                $row['_items'] = $itemsByVenda[$vid] ?? [];
            }

            if ($exibirFormas && ! $isCsv) {
                $pagamentos = $pagamentosByVenda[$vid] ?? [];
                if ($pagamentos === []) {
                    $forma = trim((string) ($venda->forma_pagamento ?? ''));
                    if ($forma !== '' && ! VendasPorFormaPagamentoAggregator::isFormaMista($forma)) {
                        $pagamentos = [[
                            'forma' => VendasPorFormaPagamentoAggregator::rotuloFormaCadastro($forma),
                            'valor' => static::formatMoney((float) $venda->total),
                        ]];
                    }
                }
                if ($pagamentos !== []) {
                    $row['_pagamentos'] = $pagamentos;
                }
            }

            $rows[] = $row;
        }

        // Totais do rodapé = universo filtrado (não a página truncada).
        $footer = [];
        $labelPlaced = false;
        $numericColumns = $exibirPctLucro ? ['total', 'pct_lucro'] : ['total'];
        foreach ($columns as $column) {
            if ($column === 'total') {
                $footer[$column] = static::formatMoney($totals['total']);
                continue;
            }
            if ($column === 'qtd') {
                $footer[$column] = static::formatQuantity((float) $totals['qtd']);
                continue;
            }
            if ($column === 'pct_lucro') {
                // Margem não soma no rodapé (depende de custo por venda).
                $footer[$column] = '';
                continue;
            }
            $footer[$column] = $labelPlaced ? '' : ('TOTAL ('.$totals['qtd'].')');
            $labelPlaced = true;
        }

        return [
            'rows' => $rows,
            'totals' => $footer,
            'summary' => $summary,
            'filters' => $filters,
            'columns' => $columns,
            'numeric_columns' => $numericColumns,
            'meta' => array_merge($sectionMeta, [
                'show_chart' => $exibirGrafico,
                'row_expand' => $exibirProdutos && $interactive,
                'row_expand_mode' => $exibirProdutos && $interactive ? 'produtos' : '',
                'truncated' => $truncated,
                'total_count' => $totals['qtd'],
                'total_sum_formatted' => static::formatMoney($totals['total']),
                'detail_limit' => $limit,
            ]),
            'extra_sections' => $extraSections,
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array<string, mixed>  $filters
     * @param  list<string>  $summary
     * @param  array{qtd: int, total: float}  $totals
     * @param  list<array<string, mixed>>  $extraSections
     * @param  array<string, mixed>  $sectionMeta
     * @return array<string, mixed>
     */
    private function buildGrouped(
        Request $request,
        Builder $base,
        array $filters,
        array $summary,
        string $agrupar,
        array $totals,
        array $extraSections,
        bool $exibirGrafico,
        bool $export,
        array $sectionMeta = [],
    ): array {
        $query = clone $base;
        $v = static::sqlTable('vendas');

        if (in_array($agrupar, ['cliente', 'cidade'], true)) {
            $query->leftJoin('people', 'people.id', '=', 'vendas.cliente_id');
        }

        if ($agrupar === 'vendedor') {
            $query->leftJoin('vendedores', 'vendedores.id', '=', 'vendas.vendedor_id');
        }

        $columns = ['grupo', 'qtd', 'total'];
        $labels = ['grupo' => 'GRUPO', 'qtd' => 'QTD VENDAS', 'total' => 'TOTAL'];

        if ($agrupar === 'cliente') {
            $labels['grupo'] = 'CLIENTE';
            $query->groupBy('vendas.cliente_id', 'people.nome_razao')
                ->orderByDesc(DB::raw("SUM({$v}.total)"))
                ->select([
                    DB::raw('COALESCE('.static::sqlTable('people').'.nome_razao, \'CONSUMIDOR\') as grupo'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw("SUM({$v}.total) as total"),
                ]);
        } elseif ($agrupar === 'cidade') {
            $labels['grupo'] = 'CIDADE';
            $query->groupBy('people.cidade_nome')
                ->orderByDesc(DB::raw("SUM({$v}.total)"))
                ->select([
                    DB::raw('COALESCE(NULLIF('.static::sqlTable('people').'.cidade_nome, \'\'), \'SEM CIDADE\') as grupo'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw("SUM({$v}.total) as total"),
                ]);
        } elseif ($agrupar === 'vendedor') {
            $labels['grupo'] = 'VENDEDOR';
            $expr = 'COALESCE(NULLIF('.static::sqlTable('vendedores').'.nome, \'\'), NULLIF('.$v.'.vendedor_nome, \'\'), \'SEM VENDEDOR\')';
            $query->groupBy('vendas.vendedor_id', 'vendedores.nome', 'vendas.vendedor_nome')
                ->orderByDesc(DB::raw("SUM({$v}.total)"))
                ->select([
                    DB::raw($expr.' as grupo'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw("SUM({$v}.total) as total"),
                ]);
        } else { // dia
            $columns = ['dia', 'qtd', 'total'];
            $labels = ['dia' => 'DIA', 'qtd' => 'QTD VENDAS', 'total' => 'TOTAL'];
            $query->groupBy(DB::raw($v.'.data'))
                ->orderBy(DB::raw($v.'.data'))
                ->select([
                    DB::raw($v.'.data as dia'),
                    DB::raw('COUNT(*) as qtd'),
                    DB::raw("SUM({$v}.total) as total"),
                ]);
        }

        $rawRows = $query->limit(self::DETAIL_LIMIT)->get();
        $truncated = $rawRows->count() >= self::DETAIL_LIMIT;

        if ($truncated) {
            $summary[] = 'AGRUPAMENTO TRUNCADO EM '.self::DETAIL_LIMIT.' linhas. Totais comerciais do universo: '
                .$totals['qtd'].' venda(s) / '.static::formatMoney($totals['total']);
            if ($export) {
                $summary[] = 'EXPORTAÇÃO PARCIAL do agrupamento.';
            }
        }

        $summary[] = 'AGRUPAMENTO: '.mb_strtoupper($agrupar, 'UTF-8');

        $rows = $rawRows->map(function ($row) use ($agrupar): array {
            if ($agrupar === 'dia') {
                return [
                    'dia' => static::formatDate($row->dia),
                    'qtd' => static::formatQuantity((float) $row->qtd),
                    'total' => static::formatMoney((float) $row->total),
                ];
            }

            return [
                'grupo' => (string) $row->grupo,
                'qtd' => static::formatQuantity((float) $row->qtd),
                'total' => static::formatMoney((float) $row->total),
            ];
        })->all();

        $footer = [];
        foreach ($columns as $i => $column) {
            if ($column === 'total') {
                $footer[$column] = static::formatMoney($totals['total']);
            } elseif ($column === 'qtd') {
                $footer[$column] = static::formatQuantity((float) $totals['qtd']);
            } else {
                $footer[$column] = $i === 0 ? 'TOTAL UNIVERSO' : '';
            }
        }

        return [
            'rows' => $rows,
            'totals' => $footer,
            'summary' => $summary,
            'filters' => $filters,
            'columns' => $columns,
            'column_labels' => $labels,
            'numeric_columns' => ['qtd', 'total'],
            'meta' => array_merge($sectionMeta, [
                'show_chart' => $exibirGrafico,
                'row_expand' => false,
                'truncated' => $truncated,
                'total_count' => $totals['qtd'],
                'total_sum_formatted' => static::formatMoney($totals['total']),
            ]),
            'extra_sections' => $extraSections,
        ];
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function baseSalesQuery(Request $request, string $de, string $ate): Builder
    {
        $query = Venda::query()
            ->whereBetween('vendas.data', [$de, $ate]);

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId && Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $query->where('vendas.empresa_id', $empresaId);
        } elseif (Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $query->whereRaw('1 = 0');
        }

        $status = (string) $request->query('status', Venda::STATUS_FECHADO);
        if ($status !== 'todos') {
            $query->where('vendas.status', $status);
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

        $cidadeQ = trim((string) $request->query('cidade_q', ''));
        if ($cidadeQ !== '') {
            $query->whereHas('cliente', function (Builder $q) use ($cidadeQ): void {
                $q->where('cidade_nome', 'like', '%'.$cidadeQ.'%');
            });
        }

        $origem = (string) $request->query('origem', 'todos');
        if ($origem !== 'todos' && $origem !== '') {
            $this->applyOrigemFilter($query, $origem);
        }

        $forma = trim((string) $request->query('forma', 'todos'));
        if ($forma !== '' && $forma !== 'todos') {
            $this->applyFormaFilter($query, $forma);
        }

        $grupo = trim((string) $request->query('grupo', 'todos'));
        if ($grupo !== '' && $grupo !== 'todos') {
            $query->whereExists(function ($exists) use ($grupo): void {
                $exists->selectRaw('1')
                    ->from('venda_itens')
                    ->join('products', 'products.id', '=', 'venda_itens.product_id')
                    ->whereColumn('venda_itens.venda_id', 'vendas.id')
                    ->where('products.grupo', $grupo);
            });
        }

        $caixaId = (int) $request->query('caixa', 0);
        if ($caixaId > 0) {
            $query->whereHas('pdvVenda', function (Builder $q) use ($caixaId): void {
                $q->where('pdv_caixa_sessao_id', $caixaId)
                    ->where(function (Builder $o): void {
                        $o->whereNull('origem')->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                    });
            });
        }

        $usuarioId = (int) $request->query('usuario', 0);
        if ($usuarioId > 0) {
            $query->whereHas('pdvVenda', function (Builder $q) use ($usuarioId): void {
                $q->where('user_id', $usuarioId)
                    ->where(function (Builder $o): void {
                        $o->whereNull('origem')->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                    });
            });
        }

        $numeroQ = trim((string) $request->query('numero_q', ''));
        if ($numeroQ !== '') {
            $this->applyNumeroFilter($query, $numeroQ);
        }

        $docTipo = (string) $request->query('doc_tipo', 'todos');
        if ($docTipo === 'nfe') {
            $query->whereHas('nfes', function (Builder $nfe): void {
                $nfe->whereIn('status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA]);
            });
        } elseif ($docTipo === 'nfce') {
            $query->whereHas('pdvVenda.nfce', function (Builder $nfc): void {
                $nfc->whereIn('status', [
                    PdvVendaNfce::STATUS_AUTORIZADA,
                    PdvVendaNfce::STATUS_SIMULADA,
                    PdvVendaNfce::STATUS_CONTINGENCIA,
                ]);
            });
        } elseif ($docTipo === 'sem') {
            $query->semDocumentoFiscalEmitido();
        }

        return $query;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyOrigemFilter(Builder $query, string $origem): void
    {
        match ($origem) {
            Venda::PLATAFORMA_PDV => $query->where(function (Builder $q): void {
                $q->where('vendas.plataforma', Venda::PLATAFORMA_PDV)
                    ->orWhereHas('pdvVenda', function (Builder $pdv): void {
                        $pdv->comercial();
                    });
            }),
            Venda::PLATAFORMA_OS => $query->where(function (Builder $q): void {
                $q->where('vendas.plataforma', Venda::PLATAFORMA_OS)
                    ->orWhereHas('ordemServico');
            }),
            Venda::PLATAFORMA_MOBILE => $query->where(function (Builder $q): void {
                $q->where('vendas.plataforma', Venda::PLATAFORMA_MOBILE)
                    ->orWhereHas('forcaVendasOrder');
            }),
            Venda::PLATAFORMA_ERP => $query->where(function (Builder $q): void {
                $q->where('vendas.plataforma', Venda::PLATAFORMA_ERP)
                    ->orWhere(function (Builder $inner): void {
                        $inner->where(function (Builder $p): void {
                            $p->whereNull('vendas.plataforma')->orWhere('vendas.plataforma', '');
                        })
                            ->whereDoesntHave('pdvVenda', fn (Builder $pdv) => $pdv->comercial())
                            ->whereDoesntHave('ordemServico')
                            ->whereDoesntHave('forcaVendasOrder');
                    });
            }),
            default => $query->where('vendas.plataforma', $origem),
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyFormaFilter(Builder $query, string $forma): void
    {
        $upper = mb_strtoupper($forma, 'UTF-8');
        $query->where(function (Builder $q) use ($upper): void {
            $q->whereRaw('UPPER(COALESCE('.static::sqlTable('vendas').'.forma_pagamento, \'\')) LIKE ?', ['%'.$upper.'%'])
                ->orWhereExists(function ($exists) use ($upper): void {
                    $exists->selectRaw('1')
                        ->from('pdv_vendas')
                        ->join('pdv_venda_pagamentos', 'pdv_venda_pagamentos.pdv_venda_id', '=', 'pdv_vendas.id')
                        ->whereColumn('pdv_vendas.venda_id', 'vendas.id')
                        ->where(function ($o): void {
                            $o->whereNull('pdv_vendas.origem')
                                ->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                        })
                        ->whereRaw('UPPER(COALESCE(pdv_venda_pagamentos.forma, \'\')) LIKE ?', ['%'.$upper.'%']);
                });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyNumeroFilter(Builder $query, string $numeroQ): void
    {
        $digits = preg_replace('/\D+/', '', $numeroQ) ?: $numeroQ;
        $query->where(function (Builder $q) use ($numeroQ, $digits): void {
            $q->where('vendas.numero', $numeroQ)
                ->orWhere('vendas.numero', $digits)
                ->orWhere('vendas.numero', 'like', '%'.$digits.'%')
                ->orWhereHas('nfes', function (Builder $nfe) use ($digits): void {
                    $nfe->where('numero', $digits);
                })
                ->orWhereHas('pdvVenda.nfce', function (Builder $nfc) use ($digits): void {
                    $nfc->where('numero', $digits);
                });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array{qtd: int, total: float}
     */
    private function aggregateUniverseTotals(Builder $query): array
    {
        $row = $query->toBase()->selectRaw(
            'COUNT(*) as qtd, COALESCE(SUM('.static::sqlTable('vendas').'.total), 0) as total'
        )->first();

        return [
            'qtd' => (int) ($row->qtd ?? 0),
            'total' => (float) ($row->total ?? 0),
        ];
    }

    /**
     * Troco PDV do universo filtrado (não é receita; só informativo quando > 0).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private function aggregatePdvTrocoTotal(Builder $query): float
    {
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasColumn('pdv_vendas', 'troco')) {
            return 0.0;
        }

        $vendaIdsSub = (clone $query)->select('vendas.id');
        $value = DB::table('pdv_vendas')
            ->whereIn('venda_id', $vendaIdsSub)
            ->where(function ($o): void {
                $o->whereNull('origem')->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
            })
            ->sum('troco');

        return (float) $value;
    }

    /**
     * Devoluções comerciais finalizadas no período da empresa logada.
     *
     * @return array{qtd: int, total: float}|null
     */
    private function aggregateDevolucoesPeriodo(string $de, string $ate): ?array
    {
        if (! Schema::hasTable('devolucoes_venda')) {
            return null;
        }

        $query = DevolucaoVenda::query()
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->whereDate('data', '>=', $de)
            ->whereDate('data', '<=', $ate);

        $empresaId = ErpContext::currentEmpresaId();
        if ($empresaId) {
            $query->where('empresa_id', $empresaId);
        }

        $row = $query->toBase()
            ->selectRaw('COUNT(*) as qtd, COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'qtd' => (int) ($row->qtd ?? 0),
            'total' => (float) ($row->total ?? 0),
        ];
    }

    /**
     * @return list<string>
     */
    private function buildHeaderSummary(Request $request, $de, $ate, string $agrupar): array
    {
        $status = (string) $request->query('status', Venda::STATUS_FECHADO);
        $statusLabel = $status === 'todos'
            ? 'Todos'
            : (Venda::statusLabels()[$status] ?? $status);

        $summary = [
            'EMPRESA: '.$this->empresaAtualLabel(),
            'PERÍODO: '.$this->periodLabel($de, $ate),
            'SITUAÇÃO: '.mb_strtoupper($statusLabel, 'UTF-8'),
        ];

        foreach ($this->appliedFilterNotes($request) as $note) {
            $summary[] = $note;
        }

        if ($agrupar !== 'nenhum') {
            $summary[] = 'AGRUPAR: '.mb_strtoupper($agrupar, 'UTF-8');
        }

        return $summary;
    }

    /**
     * @return list<string>
     */
    private function appliedFilterNotes(Request $request): array
    {
        $notes = [];

        $cliente = trim((string) $request->query('cliente_q', ''));
        if ($cliente !== '') {
            $notes[] = 'CLIENTE: '.$cliente;
        }

        $vendedor = (string) $request->query('vendedor', 'todos');
        if ($vendedor !== '' && $vendedor !== 'todos') {
            $notes[] = 'VENDEDOR: '.$vendedor;
        }

        $cidade = trim((string) $request->query('cidade_q', ''));
        if ($cidade !== '') {
            $notes[] = 'CIDADE: '.$cidade;
        }

        $grupo = trim((string) $request->query('grupo', 'todos'));
        if ($grupo !== '' && $grupo !== 'todos') {
            $notes[] = 'GRUPO: '.$grupo.' (vendas que contêm o grupo; total = valor integral da venda)';
        }

        $forma = (string) $request->query('forma', 'todos');
        if ($forma !== '' && $forma !== 'todos') {
            $notes[] = 'FORMA: '.$forma;
        }

        $origem = (string) $request->query('origem', 'todos');
        if ($origem !== '' && $origem !== 'todos') {
            $notes[] = 'ORIGEM: '.(Venda::plataformaLabels()[$origem] ?? $origem);
        }

        $numero = trim((string) $request->query('numero_q', ''));
        if ($numero !== '') {
            $notes[] = 'NÚMERO/DOC: '.$numero;
        }

        $docTipo = (string) $request->query('doc_tipo', 'todos');
        if ($docTipo !== '' && $docTipo !== 'todos') {
            $notes[] = 'DOC. FISCAL: '.$docTipo;
        }

        if ((int) $request->query('caixa', 0) > 0 || (int) $request->query('usuario', 0) > 0) {
            $notes[] = 'CAIXA/USUÁRIO: apenas vendas com vínculo PDV';
        }

        return $notes;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array{qtd: int, total: float}  $totals
     * @param  array{
     *     bruto: float,
     *     acrescimo_fechamento: float,
     *     desconto_fechamento: float,
     *     acrescimo_itens: float,
     *     desconto_itens: float,
     *     liquido: float
     * }  $totaisDetalhe
     * @param  array{qtd: int, total: float}|null  $devolucoes
     * @return list<array<string, mixed>>
     */
    private function buildCommercialSummarySections(
        Request $request,
        Builder $base,
        array $totals,
        array $totaisDetalhe,
        float $trocoPdv,
        ?array $devolucoes,
        bool $mostrarResumo,
        bool $exibirFormas,
    ): array {
        $sections = [];

        if ($mostrarResumo) {
            $sections[] = $this->totaisVendasSection($totaisDetalhe);
        }

        if ($exibirFormas) {
            $formas = $this->formasPagamentoSection($base, $request, $totals, $trocoPdv);
            if (($formas['rows'] ?? []) !== []) {
                $sections[] = $formas;
            }
        }

        if ($mostrarResumo) {
            $sections[] = $this->indicadoresVendasSection($totals, $trocoPdv);

            if ($devolucoes !== null) {
                $sections[] = $this->devolucoesSection($totals, $devolucoes);
            }
        }

        return $sections;
    }

    /**
     * Composição comercial no universo filtrado:
     * - PDV: subtotal/desconto/acréscimo do cabeçalho + desconto/acréscimo dos itens
     * - Sem PDV: entra só pelo vendas.total (sem inventar descontos)
     * - Líquido: sempre SUM(vendas.total)
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array{qtd: int, total: float}  $totals
     * @return array{
     *     bruto: float,
     *     acrescimo_fechamento: float,
     *     desconto_fechamento: float,
     *     acrescimo_itens: float,
     *     desconto_itens: float,
     *     liquido: float
     * }
     */
    private function aggregateTotaisDetalhe(Builder $query, array $totals): array
    {
        $liquido = (float) $totals['total'];
        $vendaIdsSub = (clone $query)->select('vendas.id');

        $pdvSubtotal = 0.0;
        $pdvDesconto = 0.0;
        $pdvAcrescimo = 0.0;
        $pdvTotal = 0.0;
        $itemDesconto = 0.0;
        $itemAcrescimo = 0.0;

        if (Schema::hasTable('pdv_vendas')) {
            $pdv = DB::table('pdv_vendas')
                ->whereIn('venda_id', $vendaIdsSub)
                ->where(function ($o): void {
                    $o->whereNull('origem')->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                })
                ->selectRaw(
                    'COALESCE(SUM(subtotal),0) as subtotal,'
                    .' COALESCE(SUM(desconto),0) as desconto,'
                    .' COALESCE(SUM(acrescimo),0) as acrescimo,'
                    .' COALESCE(SUM(total),0) as total'
                )
                ->first();

            $pdvSubtotal = (float) ($pdv->subtotal ?? 0);
            $pdvDesconto = (float) ($pdv->desconto ?? 0);
            $pdvAcrescimo = (float) ($pdv->acrescimo ?? 0);
            $pdvTotal = (float) ($pdv->total ?? 0);
        }

        if (Schema::hasTable('pdv_venda_itens') && Schema::hasTable('pdv_vendas')) {
            $pdvIdsSub = DB::table('pdv_vendas')
                ->whereIn('venda_id', $vendaIdsSub)
                ->where(function ($o): void {
                    $o->whereNull('origem')->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                })
                ->select('id');

            // desconto/acréscimo do item no PDV são unitários (cupom multiplica pela qtd).
            $itens = DB::table('pdv_venda_itens')
                ->whereIn('pdv_venda_id', $pdvIdsSub)
                ->selectRaw(
                    'COALESCE(SUM(desconto * quantidade), 0) as desconto,'
                    .' COALESCE(SUM(acrescimo * quantidade), 0) as acrescimo'
                )
                ->first();

            $itemDesconto = (float) ($itens->desconto ?? 0);
            $itemAcrescimo = (float) ($itens->acrescimo ?? 0);
        }

        // Vendas do universo sem vínculo PDV comercial: só conhecemos vendas.total.
        $semPdv = max(0.0, $liquido - $pdvTotal);
        $bruto = $pdvSubtotal + $semPdv;

        return [
            'bruto' => $bruto,
            'acrescimo_fechamento' => $pdvAcrescimo,
            'desconto_fechamento' => $pdvDesconto,
            'acrescimo_itens' => $itemAcrescimo,
            'desconto_itens' => $itemDesconto,
            'liquido' => $liquido,
        ];
    }

    /**
     * @param  array{
     *     bruto: float,
     *     acrescimo_fechamento: float,
     *     desconto_fechamento: float,
     *     acrescimo_itens: float,
     *     desconto_itens: float,
     *     liquido: float
     * }  $detalhe
     * @return array<string, mixed>
     */
    private function totaisVendasSection(array $detalhe): array
    {
        return [
            'title' => 'Totais de venda',
            'columns' => ['label', 'valor'],
            'column_labels' => [
                'label' => '',
                'valor' => 'Total',
            ],
            'numeric_columns' => ['valor'],
            'rows' => [
                [
                    'label' => 'Total Bruto',
                    'valor' => static::formatMoney($detalhe['bruto']),
                ],
                [
                    'label' => '(+) Total de Acréscimos no fechamento da venda',
                    'valor' => static::formatMoney($detalhe['acrescimo_fechamento']),
                ],
                [
                    'label' => '(-) Total de Descontos no fechamento da venda',
                    'valor' => static::formatMoney($detalhe['desconto_fechamento']),
                ],
                [
                    'label' => '(+) Total de Acréscimos nos Itens',
                    'valor' => static::formatMoney($detalhe['acrescimo_itens']),
                ],
                [
                    'label' => '(-) Total de Descontos nos Itens',
                    'valor' => static::formatMoney($detalhe['desconto_itens']),
                ],
                [
                    'label' => 'Total líquido',
                    'valor' => static::formatMoney($detalhe['liquido']),
                ],
                [
                    'label' => 'Total pago',
                    'valor' => static::formatMoney($detalhe['liquido']),
                ],
            ],
        ];
    }

    /**
     * @param  array{qtd: int, total: float}  $totals
     * @return array<string, mixed>
     */
    private function indicadoresVendasSection(array $totals, float $trocoPdv): array
    {
        $qtd = max(0, (int) $totals['qtd']);
        $media = $qtd > 0 ? ((float) $totals['total'] / $qtd) : 0.0;

        $rows = [
            [
                'label' => 'Quantidade de vendas realizadas',
                'valor' => static::formatQuantity((float) $qtd).($qtd === 1 ? ' venda' : ' vendas'),
            ],
            [
                'label' => 'Valor médio das vendas realizadas',
                'valor' => static::formatMoney($media),
            ],
        ];

        if ($trocoPdv > 0.00005) {
            $rows[] = [
                'label' => 'Troco (PDV)',
                'valor' => static::formatMoney($trocoPdv),
            ];
        }

        return [
            'title' => '',
            'columns' => ['label', 'valor'],
            'column_labels' => [
                'label' => '',
                'valor' => '',
            ],
            'numeric_columns' => ['valor'],
            'rows' => $rows,
            'hide_header' => true,
        ];
    }

    /**
     * @param  array{qtd: int, total: float}  $totals
     * @param  array{qtd: int, total: float}  $devolucoes
     * @return array<string, mixed>
     */
    private function devolucoesSection(array $totals, array $devolucoes): array
    {
        $liquido = (float) $totals['total'] - (float) $devolucoes['total'];
        $qtd = (int) $devolucoes['qtd'];

        return [
            'title' => 'Devoluções no período',
            'columns' => ['label', 'valor'],
            'column_labels' => [
                'label' => '',
                'valor' => '',
            ],
            'numeric_columns' => ['valor'],
            'hide_header' => true,
            'rows' => [
                [
                    'label' => 'Quantidade de devoluções no período',
                    'valor' => static::formatQuantity((float) $qtd).($qtd === 1 ? ' devolução' : ' devoluções'),
                ],
                [
                    'label' => 'Valor total de devoluções no período',
                    'valor' => static::formatMoney((float) $devolucoes['total']),
                ],
                [
                    'label' => 'Valor total de venda real (venda − devoluções)',
                    'valor' => static::formatMoney($liquido),
                ],
            ],
        ];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array{qtd: int, total: float}  $totals
     * @return array<string, mixed>
     */
    private function formasPagamentoSection(Builder $base, Request $request, array $totals, float $trocoPdv = 0.0): array
    {
        $formaFiltro = (string) $request->query('forma', '');
        if ($formaFiltro === 'todos') {
            $formaFiltro = '';
        }

        $rawRows = VendasPorFormaPagamentoAggregator::aggregateForVendaIdsQuery(
            (clone $base)->select('vendas.id'),
            $formaFiltro,
        );

        $somaFormas = 0.0;
        foreach ($rawRows as $row) {
            $somaFormas += (float) $row['total'];
        }

        // Troco existe no cabeçalho PDV, não por meio — linhas sem valor inventado; rodapé com total PDV.
        $trocoFooter = static::formatMoney(max(0.0, $trocoPdv));

        $mapped = [];
        foreach ($rawRows as $row) {
            $valor = (float) $row['total'];
            $pct = $somaFormas > 0.00005 ? (($valor / $somaFormas) * 100.0) : 0.0;
            $mapped[] = [
                'forma' => (string) $row['forma'],
                'valor' => static::formatMoney($valor),
                'troco' => '',
                'total' => static::formatMoney($valor),
                'percentual' => number_format($pct, 2, ',', '.').'%',
            ];
        }

        return [
            'title' => 'Resumo de meios de pagamento',
            'columns' => ['forma', 'valor', 'troco', 'total', 'percentual'],
            'column_labels' => [
                'forma' => 'Meio de pagamento',
                'valor' => 'Valor por meio',
                'troco' => 'Troco',
                'total' => 'Total',
                'percentual' => '% Relativo',
            ],
            'numeric_columns' => ['valor', 'troco', 'total', 'percentual'],
            'rows' => $mapped,
            'totals' => [
                'forma' => 'Total dos meios de pagamento',
                'valor' => static::formatMoney($somaFormas),
                'troco' => $trocoFooter,
                'total' => static::formatMoney($somaFormas),
                'percentual' => $somaFormas > 0.00005 ? '100,00%' : '0,00%',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function resolveDetailColumns(Request $request, bool $exibirPctLucro = false): array
    {
        $columns = [
            'numero',
            'data',
            'cliente',
            'vendedor',
            'forma',
            'plataforma',
            'status',
            'total',
        ];

        if (trim((string) $request->query('cidade_q', '')) !== '') {
            array_splice($columns, 3, 0, ['cidade']);
        }

        $docTipo = (string) $request->query('doc_tipo', 'todos');
        $numeroQ = trim((string) $request->query('numero_q', ''));
        if ($docTipo !== 'todos' || $numeroQ !== '') {
            $idx = array_search('plataforma', $columns, true);
            array_splice($columns, $idx === false ? 5 : $idx + 1, 0, ['doc_fiscal']);
        }

        if ($exibirPctLucro) {
            $columns[] = 'pct_lucro';
        }

        return $columns;
    }

    /**
     * Formas de pagamento por venda (linhas PDV). Não inclui troco; não duplica total da venda.
     *
     * @param  list<int>  $vendaIds
     * @return array<int, list<array{forma: string, valor: string}>>
     */
    private function loadPagamentosByVendaIds(array $vendaIds): array
    {
        $out = [];
        if ($vendaIds === [] || ! Schema::hasTable('pdv_venda_pagamentos') || ! Schema::hasTable('pdv_vendas')) {
            return $out;
        }

        $rows = DB::table('pdv_venda_pagamentos')
            ->join('pdv_vendas', 'pdv_vendas.id', '=', 'pdv_venda_pagamentos.pdv_venda_id')
            ->whereIn('pdv_vendas.venda_id', $vendaIds)
            ->where(function ($o): void {
                $o->whereNull('pdv_vendas.origem')->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
            })
            ->where('pdv_venda_pagamentos.valor', '>', 0)
            ->orderBy('pdv_venda_pagamentos.id')
            ->get([
                'pdv_vendas.venda_id',
                'pdv_venda_pagamentos.forma',
                'pdv_venda_pagamentos.valor',
            ]);

        foreach ($rows as $row) {
            $vid = (int) ($row->venda_id ?? 0);
            if ($vid <= 0) {
                continue;
            }

            $formaBruta = (string) ($row->forma ?? '');
            if (VendasPorFormaPagamentoAggregator::isFormaMista($formaBruta)) {
                continue;
            }

            $out[$vid][] = [
                'forma' => VendasPorFormaPagamentoAggregator::rotuloFormaCadastro($formaBruta),
                'valor' => static::formatMoney((float) ($row->valor ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $vendaIds
     * @return array<int, list<array<string, string>>>
     */
    private function loadItemsByVendaIds(array $vendaIds): array
    {
        $out = [];
        VendaItem::query()
            ->leftJoin('products', 'products.id', '=', 'venda_itens.product_id')
            ->whereIn('venda_itens.venda_id', $vendaIds)
            ->orderBy('venda_itens.id')
            ->get([
                'venda_itens.venda_id',
                'products.codigo',
                'products.descricao',
                'products.grupo',
                'venda_itens.quantidade',
                'venda_itens.valor_item',
                'venda_itens.total',
            ])
            ->each(function ($row) use (&$out): void {
                $vid = (int) $row->venda_id;
                $out[$vid][] = [
                    'codigo' => (string) ($row->codigo ?? ''),
                    'descricao' => (string) ($row->descricao ?? ''),
                    'grupo' => (string) ($row->grupo ?? ''),
                    'qtd' => static::formatQuantity((float) $row->quantidade),
                    'unitario' => static::formatMoney((float) $row->valor_item),
                    'total' => static::formatMoney((float) $row->total),
                ];
            });

        return $out;
    }

    private function docFiscalLabel(Venda $venda): string
    {
        $parts = [];
        foreach ($venda->nfes ?? [] as $nfe) {
            if (in_array($nfe->status, [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA], true)) {
                $parts[] = 'NF-e '.$nfe->numero;
            }
        }
        $nfce = $venda->pdvVenda?->nfce;
        if ($nfce && in_array($nfce->status, [
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_SIMULADA,
            PdvVendaNfce::STATUS_CONTINGENCIA,
        ], true)) {
            $parts[] = 'NFC-e '.$nfce->numero;
        }

        return $parts !== [] ? implode(' / ', $parts) : '—';
    }

    /**
     * Resumo por hora (vendas.hora = horário comercial da venda).
     * Só considera registros com hora preenchida; se houver vendas sem hora, sinaliza cobertura parcial.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $base
     * @param  array{qtd: int, total: float}  $totals
     * @return array<string, mixed>
     */
    private function buildHourlySummary(Builder $base, array $totals): array
    {
        $vendaTable = static::sqlTable('vendas');
        $withHora = (clone $base)
            ->whereNotNull('vendas.hora')
            ->where('vendas.hora', '!=', '');

        $coverage = $this->aggregateUniverseTotals(clone $withHora);
        $complete = ((int) $coverage['qtd'] === (int) $totals['qtd'])
            && abs((float) $coverage['total'] - (float) $totals['total']) < 0.009;

        $rawRows = (clone $withHora)->toBase()
            ->selectRaw(
                'HOUR('.$vendaTable.'.hora) as h,'
                .' COUNT(*) as qtd,'
                .' COALESCE(SUM('.$vendaTable.'.total), 0) as total'
            )
            ->groupBy(DB::raw('HOUR('.$vendaTable.'.hora)'))
            ->orderBy('h')
            ->get();

        $rows = [];
        $labels = [];
        $qtdValues = [];
        $valorValues = [];
        $ticketValues = [];
        $sumQtd = 0;
        $sumTotal = 0.0;
        $pending = [];

        foreach ($rawRows as $row) {
            $hour = max(0, min(23, (int) ($row->h ?? 0)));
            $qtd = (int) ($row->qtd ?? 0);
            $total = (float) ($row->total ?? 0);
            $ticket = $qtd > 0 ? ($total / $qtd) : 0.0;
            $label = sprintf('%02d:00 até %02d:59', $hour, $hour);

            $sumQtd += $qtd;
            $sumTotal += $total;
            $labels[] = sprintf('%02dh', $hour);
            $qtdValues[] = (float) $qtd;
            $valorValues[] = $total;
            $ticketValues[] = $ticket;

            $pending[] = [
                'hora' => $label,
                'qtd' => $qtd,
                'total' => $total,
                'ticket' => $ticket,
            ];
        }

        foreach ($pending as $item) {
            $pct = $sumTotal > 0.00005 ? (($item['total'] / $sumTotal) * 100.0) : 0.0;
            $rows[] = [
                'hora' => $item['hora'],
                'percentual' => number_format($pct, 2, ',', '.').'%',
                'qtd' => static::formatQuantity((float) $item['qtd']),
                'valor' => static::formatMoney((float) $item['total']),
                'ticket' => static::formatMoney((float) $item['ticket']),
                'qtd_raw' => (int) $item['qtd'],
                'valor_raw' => (float) $item['total'],
                'ticket_raw' => (float) $item['ticket'],
            ];
        }

        $ticketGeral = $sumQtd > 0 ? ($sumTotal / $sumQtd) : 0.0;
        $maxQtd = 0.00001;
        $maxValor = 0.00001;
        $maxTicket = 0.00001;
        foreach ($qtdValues as $value) {
            $maxQtd = max($maxQtd, (float) $value);
        }
        foreach ($valorValues as $value) {
            $maxValor = max($maxValor, (float) $value);
        }
        foreach ($ticketValues as $value) {
            $maxTicket = max($maxTicket, (float) $value);
        }

        $note = 'Fonte do horário: vendas.hora (horário comercial da venda).';
        if (! $complete) {
            $note .= ' Cobertura parcial: '
                .$coverage['qtd'].' de '.$totals['qtd']
                .' venda(s) com horário válido. Totais por hora não equivalem ao universo completo.';
        }

        return [
            'title' => 'Relatório de Vendas — Resumo por Hora',
            'hora_field' => 'vendas.hora',
            'complete' => $complete,
            'note' => $note,
            'rows' => $rows,
            'totals' => [
                'hora' => 'Total ('.$sumQtd.' registro'.($sumQtd === 1 ? '' : 's').' de hora)',
                'percentual' => $sumTotal > 0.00005 ? '100,00%' : '0,00%',
                'qtd' => static::formatQuantity((float) $sumQtd),
                'valor' => static::formatMoney($sumTotal),
                'ticket' => static::formatMoney($ticketGeral),
            ],
            'charts' => [
                'qtd' => [
                    'title' => 'Gráfico por Quantidade de Vendas',
                    'labels' => $labels,
                    'values' => $qtdValues,
                    'formatted' => array_map(static fn (float $v): string => (string) (int) round($v), $qtdValues),
                    'short' => array_map(static fn (float $v): string => (string) (int) round($v), $qtdValues),
                    'max' => $maxQtd,
                ],
                'valor' => [
                    'title' => 'Gráfico por Valor de Vendas',
                    'labels' => $labels,
                    'values' => $valorValues,
                    'formatted' => array_map(static fn (float $v): string => AbstractTabularReport::formatMoney($v), $valorValues),
                    'short' => array_map(static fn (float $v): string => self::formatMoneyShort($v), $valorValues),
                    'max' => $maxValor,
                ],
                'ticket' => [
                    'title' => 'Gráfico por Ticket Médio',
                    'labels' => $labels,
                    'values' => $ticketValues,
                    'formatted' => array_map(static fn (float $v): string => AbstractTabularReport::formatMoney($v), $ticketValues),
                    'short' => array_map(static fn (float $v): string => self::formatMoneyShort($v), $ticketValues),
                    'max' => $maxTicket,
                ],
            ],
        ];
    }

    /**
     * Rótulo monetário compacto para gráficos (valor completo fica no tooltip/tabela).
     */
    private static function formatMoneyShort(float $value): string
    {
        $abs = abs($value);
        if ($abs >= 1000000) {
            return 'R$ '.number_format($value / 1000000, 1, ',', '.').' mi';
        }
        if ($abs >= 1000) {
            return 'R$ '.number_format($value / 1000, 1, ',', '.').' mil';
        }

        return static::formatMoney($value);
    }

    /**
     * Série diária do universo filtrado (vendas.total), com zeros nos dias sem venda.
     *
     * @return array{
     *     title: string,
     *     de: string,
     *     ate: string,
     *     period_label: string,
     *     clamped: bool,
     *     labels: list<string>,
     *     keys: list<string>,
     *     values: list<float>,
     *     rows: list<array{dia: string, valor: string, raw: float, pct: float}>,
     *     max: float
     * }
     */
    private function buildDailyChartPayload(Request $request): array
    {
        [$deOrig, $ateOrig] = $this->periodFromRequest($request);
        $de = $deOrig->copy()->startOfDay();
        $ate = $ateOrig->copy()->startOfDay();
        $clamped = false;

        // Períodos longos: limita a 93 dias para manter o gráfico legível.
        if ($de->diffInDays($ate) > 92) {
            $de = $ate->copy()->subDays(92)->startOfDay();
            $clamped = true;
        }

        $axis = $this->chartDayAxis($de, $ate);
        $map = $this->aggregateDailyTotalsFiltered($request, $de, $ate);
        $values = $this->chartFillDays($axis['keys'], $map);
        $max = 0.00001;
        foreach ($values as $value) {
            $max = max($max, (float) $value);
        }

        $rows = [];
        foreach ($axis['labels'] as $i => $label) {
            $raw = (float) ($values[$i] ?? 0);
            $rows[] = [
                'dia' => $label,
                'valor' => static::formatMoney($raw),
                'raw' => $raw,
                'pct' => round(($raw / $max) * 100, 2),
            ];
        }

        return [
            'title' => 'Análise Gráfica Diária',
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'period_label' => $this->periodLabel($de, $ate),
            'clamped' => $clamped,
            'labels' => $axis['labels'],
            'keys' => $axis['keys'],
            'values' => $values,
            'rows' => $rows,
            'max' => $max,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function aggregateDailyTotalsFiltered(Request $request, $de, $ate): array
    {
        $query = $this->baseSalesQuery($request, $de->toDateString(), $ate->toDateString());
        $dataCol = static::sqlTable('vendas').'.data';
        $rows = $query
            ->groupBy(DB::raw($dataCol))
            ->orderBy(DB::raw($dataCol))
            ->get([
                DB::raw($dataCol.' as dia'),
                DB::raw('SUM('.static::sqlTable('vendas').'.total) as total'),
            ]);

        $out = [];
        foreach ($rows as $row) {
            $out[Carbon::parse((string) $row->dia)->format('Y-m-d')] = (float) $row->total;
        }

        return $out;
    }

    /**
     * @param  list<string>  $periodLabels
     * @param  list<array<string, mixed>>  $datasets
     * @return array<string, array<string, mixed>>
     */
    private function chartSummaryCards(array $periodLabels, array $datasets): array
    {
        $periodCount = count($periodLabels);
        $perPeriod = array_fill(0, max($periodCount, 0), 0.0);

        foreach ($datasets as $dataset) {
            foreach (($dataset['data'] ?? []) as $i => $value) {
                if (array_key_exists((int) $i, $perPeriod)) {
                    $perPeriod[(int) $i] += (float) $value;
                }
            }
        }

        $total = array_sum($perPeriod);
        $bestIdx = 0;
        $bestVal = $perPeriod[0] ?? 0.0;
        for ($i = 1; $i < $periodCount; $i++) {
            if ($perPeriod[$i] > $bestVal) {
                $bestVal = $perPeriod[$i];
                $bestIdx = $i;
            }
        }

        return [
            'total' => [
                'label' => 'Total vendido',
                'value' => $total,
                'text' => static::formatMoney($total),
            ],
            'best_day' => [
                'label' => 'Melhor dia',
                'value' => $bestVal,
                'text' => static::formatMoney($bestVal),
                'day' => (string) ($periodLabels[$bestIdx] ?? ''),
            ],
            'avg' => [
                'label' => 'Média diária',
                'value' => $periodCount > 0 ? ($total / $periodCount) : 0.0,
                'text' => static::formatMoney($periodCount > 0 ? ($total / $periodCount) : 0.0),
            ],
        ];
    }

    /**
     * @param  list<float>  $data
     * @return array<string, mixed>
     */
    private function barDataset(string $label, array $data): array
    {
        $colors = [];
        $palette = self::CHART_COLORS;
        $n = count($palette);
        foreach (array_keys($data) as $i) {
            $colors[] = $palette[$i % $n];
        }

        return [
            'label' => $label,
            'data' => $data,
            'backgroundColor' => $colors,
            'borderColor' => $colors,
            'borderWidth' => 0,
            'borderRadius' => 3,
            'maxBarThickness' => 36,
        ];
    }

    private function agruparFromRequest(Request $request): string
    {
        $raw = mb_strtolower(trim((string) $request->query('agrupar', 'nenhum')), 'UTF-8');
        $allowed = ['nenhum', 'cliente', 'cidade', 'vendedor', 'forma', 'dia'];

        return in_array($raw, $allowed, true) ? $raw : 'nenhum';
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersSnapshot(Request $request, $de, $ate): array
    {
        return [
            'de' => $de->toDateString(),
            'ate' => $ate->toDateString(),
            'cliente_q' => (string) $request->query('cliente_q', ''),
            'vendedor' => (string) $request->query('vendedor', 'todos'),
            'cidade_q' => (string) $request->query('cidade_q', ''),
            'grupo' => (string) $request->query('grupo', 'todos'),
            'caixa' => (string) $request->query('caixa', 'todos'),
            'forma' => (string) $request->query('forma', 'todos'),
            'usuario' => (string) $request->query('usuario', 'todos'),
            'numero_q' => (string) $request->query('numero_q', ''),
            'origem' => (string) $request->query('origem', 'todos'),
            'status' => (string) $request->query('status', Venda::STATUS_FECHADO),
            'doc_tipo' => (string) $request->query('doc_tipo', 'todos'),
            'exibir_produtos' => $request->boolean('exibir_produtos') ? '1' : '',
            'mostrar_resumo' => $request->boolean('mostrar_resumo') ? '1' : '',
            'apenas_resumo' => $request->boolean('apenas_resumo') ? '1' : '',
            'exibir_formas' => $request->boolean('exibir_formas') ? '1' : '',
            'exibir_grafico' => $request->boolean('exibir_grafico') ? '1' : '',
            'exibir_resumo_hora' => $request->boolean('exibir_resumo_hora') ? '1' : '',
            'exibir_pct_lucro' => $request->boolean('exibir_pct_lucro') ? '1' : '',
            'agrupar' => $this->agruparFromRequest($request),
            'gerar' => $request->boolean('gerar') ? '1' : '',
            'empresa_label' => $this->empresaAtualLabel(),
        ];
    }

    private function empresaAtualLabel(): string
    {
        $empresa = ErpContext::currentEmpresa();
        if (! $empresa) {
            return '—';
        }

        return trim((string) ($empresa->fantasia ?: $empresa->nome ?: $empresa->razao_social ?: ('#'.$empresa->id)));
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

    /**
     * @return array<string, string>
     */
    private function grupoOptions(): array
    {
        $options = ['todos' => 'Todos'];
        if (! Schema::hasColumn('products', 'grupo')) {
            return $options;
        }

        Product::query()
            ->whereNotNull('grupo')
            ->where('grupo', '!=', '')
            ->distinct()
            ->orderBy('grupo')
            ->limit(400)
            ->pluck('grupo')
            ->each(function ($grupo) use (&$options): void {
                $g = trim((string) $grupo);
                if ($g !== '') {
                    $options[$g] = $g;
                }
            });

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function formaOptions(): array
    {
        $options = ['todos' => 'Todas'];
        FormaPagamento::query()
            ->where('ativo', true)
            ->orderBy('codigo')
            ->limit(200)
            ->get(['id', 'codigo', 'descricao'])
            ->each(function (FormaPagamento $f) use (&$options): void {
                $desc = trim((string) ($f->descricao ?? ''));
                if ($desc !== '') {
                    $options[$desc] = mb_strtoupper($desc, 'UTF-8');
                }
            });

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function caixaOptions(): array
    {
        $options = ['todos' => 'Todos'];
        if (! Schema::hasTable('pdv_caixa_sessoes')) {
            return $options;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $query = PdvCaixaSessao::query()
            ->with(['user:id,name', 'caixaConta:id,nome'])
            ->orderByDesc('aberto_em')
            ->limit(80);

        if ($empresaId && Schema::hasColumn('pdv_caixa_sessoes', 'empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        $query->get()->each(function (PdvCaixaSessao $s) use (&$options): void {
            $aberto = $s->aberto_em?->format('d/m/Y H:i') ?: '—';
            $user = $s->user?->name ?: 'UsSuário';
            $conta = $s->caixaConta?->nome ?: 'Caixa';
            $options[(string) $s->id] = '#'.$s->id.' · '.$conta.' · '.$user.' · '.$aberto;
        });

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function usuarioOptions(): array
    {
        $options = ['todos' => 'Todos'];
        if (! Schema::hasTable('pdv_vendas') || ! Schema::hasColumn('pdv_vendas', 'user_id')) {
            return $options;
        }

        $empresaId = ErpContext::currentEmpresaId();
        $userQuery = PdvVenda::query()
            ->whereNotNull('pdv_vendas.user_id')
            ->where(function ($o): void {
                $o->whereNull('pdv_vendas.origem')
                    ->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
            });

        if ($empresaId && Schema::hasColumn((new Venda)->getTable(), 'empresa_id')) {
            $userQuery->join('vendas', 'vendas.id', '=', 'pdv_vendas.venda_id')
                ->where('vendas.empresa_id', $empresaId);
        }

        $userIds = $userQuery
            ->distinct()
            ->orderByDesc('pdv_vendas.id')
            ->limit(200)
            ->pluck('pdv_vendas.user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            return $options;
        }

        User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->each(function (User $u) use (&$options): void {
                $options[(string) $u->id] = (string) ($u->name ?: ('#'.$u->id));
            });

        return $options;
    }
}
