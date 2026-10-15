<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\OrdemServico;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OsPendentesAtrasadasReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'os-pendentes-atrasadas';
    }

    public function title(): string
    {
        return 'OS PENDENTES E ATRASADAS';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'OS',
            'abertura' => 'ABERTURA',
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'equipamento' => 'EQUIP./VEÍC.',
            'tecnico' => 'TÉCNICO',
            'situacao' => 'SIT.',
            'dias_aberto' => 'DIAS ABERTO',
            'previsao' => 'PREVISÃO',
            'dias_atraso' => 'DIAS ATRASO',
            'total' => 'TOTAL',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 3,
            'abertura' => 7,
            'empresa' => 8,
            'cliente' => 18,
            'equipamento' => 22,
            'tecnico' => 9,
            'situacao' => 6,
            'dias_aberto' => 7,
            'previsao' => 8,
            'dias_atraso' => 7,
            'total' => 7,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'abertura', 'situacao', 'dias_aberto', 'previsao', 'dias_atraso', 'equipamento'];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'abertura',
            'cliente',
            'equipamento',
            'tecnico',
            'situacao',
            'dias_aberto',
            'previsao',
            'dias_atraso',
            'total',
        ];
    }

    public function numericColumns(): array
    {
        return ['dias_aberto', 'dias_atraso', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
            [
                'key' => 'situacao',
                'label' => 'Situação',
                'type' => 'select',
                'options' => [
                    'todos' => 'Todas pendentes',
                    'aberta' => 'Abertas',
                    'andamento' => 'Em andamento',
                ],
            ],
            [
                'key' => 'somente_atrasadas',
                'label' => 'Somente atrasadas',
                'type' => 'select',
                'options' => [
                    'nao' => 'Não',
                    'sim' => 'Sim',
                ],
            ],
            [
                'key' => 'cliente',
                'label' => 'Cliente',
                'type' => 'text',
            ],
            [
                'key' => 'tecnico',
                'label' => 'Técnico',
                'type' => 'select',
                'options' => $this->tecnicoOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'abertura',
        );
        $situacao = (string) $request->query('situacao', 'todos');
        $somenteAtrasadas = (string) $request->query('somente_atrasadas', 'nao') === 'sim';
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $multi = $this->isMultiEmpresaScope($request);

        $hoje = ErpTimezone::toLocal()->startOfDay();
        $hojeSql = $hoje->toDateString();
        $os = static::sqlTable('ordens_servico');

        $base = $this->filteredPendingQuery(
            $request,
            $de->toDateString(),
            $ate->toDateString(),
            $situacao,
            $somenteAtrasadas,
            $clienteQ,
            $tecnicoId,
            $hojeSql,
        );

        $totaisAgg = (clone $base)->select([
            DB::raw('COUNT(*) as qtd_pendentes'),
            DB::raw(
                "SUM(CASE WHEN {$os}.previsao_entrega IS NOT NULL "
                ."AND DATE({$os}.previsao_entrega) < '{$hojeSql}' THEN 1 ELSE 0 END) as qtd_atrasadas"
            ),
            DB::raw("COALESCE(SUM({$os}.total_geral), 0) as valor_total"),
            DB::raw(
                "COALESCE(SUM(CASE WHEN {$os}.previsao_entrega IS NOT NULL "
                ."AND DATE({$os}.previsao_entrega) < '{$hojeSql}' THEN {$os}.total_geral ELSE 0 END), 0) as valor_atrasadas"
            ),
        ])->first();

        $ordens = (clone $base)
            ->with([
                'cliente:id,nome_razao',
                'atendente:id,codigo,nome',
            ])
            ->select([
                'ordens_servico.id',
                'ordens_servico.empresa_id',
                'ordens_servico.numero',
                'ordens_servico.situacao',
                'ordens_servico.data_inicio',
                'ordens_servico.previsao_entrega',
                'ordens_servico.cliente_id',
                'ordens_servico.atendente_id',
                'ordens_servico.nome',
                'ordens_servico.descricao',
                'ordens_servico.modelo',
                'ordens_servico.marca',
                'ordens_servico.placa',
                'ordens_servico.placa_veiculo',
                'ordens_servico.modelo_veiculo',
                'ordens_servico.marca_veiculo',
                'ordens_servico.total_geral',
            ])
            ->orderByRaw(
                "CASE WHEN {$os}.previsao_entrega IS NOT NULL AND DATE({$os}.previsao_entrega) < ? THEN 0 ELSE 1 END",
                [$hojeSql]
            )
            ->orderByRaw(
                "CASE WHEN {$os}.previsao_entrega IS NOT NULL AND DATE({$os}.previsao_entrega) < ? "
                ."THEN DATEDIFF(?, DATE({$os}.previsao_entrega)) ELSE 0 END DESC",
                [$hojeSql, $hojeSql]
            )
            ->orderBy('ordens_servico.data_inicio')
            ->orderBy('ordens_servico.numero')
            ->limit(5000)
            ->get();

        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rows = $ordens->map(function (OrdemServico $ordem) use ($multi, $labels, $hoje): array {
            $abertura = $ordem->data_inicio;
            $previsao = $ordem->previsao_entrega;
            $diasAberto = $abertura !== null
                ? (int) $abertura->copy()->startOfDay()->diffInDays($hoje)
                : null;

            $atrasada = $previsao !== null && $previsao->copy()->startOfDay()->lt($hoje);
            $diasAtraso = $atrasada
                ? (int) $previsao->copy()->startOfDay()->diffInDays($hoje)
                : null;

            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'abertura' => static::formatDate($abertura),
                'cliente' => $ordem->clienteNome(),
                'equipamento' => $this->formatEquipamento($ordem),
                'tecnico' => $this->formatTecnico($ordem->atendente) ?: 'SEM TÉCNICO',
                'situacao' => mb_strtoupper($ordem->situacaoLabel(), 'UTF-8'),
                'dias_aberto' => $diasAberto !== null ? static::formatQuantity((float) $diasAberto) : '',
                'previsao' => $previsao !== null ? static::formatDate($previsao) : '—',
                'dias_atraso' => $diasAtraso !== null ? static::formatQuantity((float) $diasAtraso) : '',
                'total' => static::formatMoney((float) ($ordem->total_geral ?? 0)),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            return $row;
        })->all();

        $situacaoLabel = match ($situacao) {
            'aberta' => 'ABERTAS',
            'andamento' => 'EM ANDAMENTO',
            default => 'TODAS PENDENTES',
        };

        $tecnicoLabel = 'TODOS';
        if ($tecnicoId > 0) {
            $tecnico = Vendedor::query()->select(['id', 'codigo', 'nome'])->find($tecnicoId);
            $tecnicoLabel = $this->formatTecnico($tecnico) ?: (string) $tecnicoId;
        }

        $qtdPendentes = (int) ($totaisAgg->qtd_pendentes ?? 0);
        $qtdAtrasadas = (int) ($totaisAgg->qtd_atrasadas ?? 0);
        $valorTotal = (float) ($totaisAgg->valor_total ?? 0);
        $valorAtrasadas = (float) ($totaisAgg->valor_atrasadas ?? 0);

        $limitHint = count($rows) >= 5000 ? ' (limite de exibição; totais do rodapé incluem todos os filtros)' : '';

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'situacao' => $situacao,
                'somente_atrasadas' => $somenteAtrasadas ? 'sim' : 'nao',
                'cliente' => $clienteQ,
                'tecnico' => $tecnicoId > 0 ? (string) $tecnicoId : 'todos',
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA ABERTURA): '.$this->periodLabel($de, $ate),
                'SITUAÇÃO: '.$situacaoLabel,
                'TÉCNICO: '.mb_strtoupper($tecnicoLabel, 'UTF-8'),
                'SOMENTE ATRASADAS: '.($somenteAtrasadas ? 'SIM' : 'NÃO'),
                'PENDENTES: '.$qtdPendentes.' | VALOR PENDENTE: '.static::formatMoney($valorTotal),
                'ATRASADAS: '.$qtdAtrasadas.' | VALOR ATRASADAS: '.static::formatMoney($valorAtrasadas),
                'ATRASO: somente com previsão de entrega preenchida e vencida (sem inventar atraso)',
                'OS EXIBIDAS: '.count($rows).$limitHint,
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate($columns, $qtdPendentes, $qtdAtrasadas, $valorTotal);

        return $built;
    }

    /**
     * Universo: apenas OS pendentes (não finalizadas/entregues/canceladas).
     *
     * @return Builder<\App\Models\OrdemServico>
     */
    private function filteredPendingQuery(
        Request $request,
        string $de,
        string $ate,
        string $situacao,
        bool $somenteAtrasadas,
        string $clienteQ,
        int $tecnicoId,
        string $hojeSql,
    ): Builder {
        $query = OrdemServico::query()
            ->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_ABERTA,
                OrdemServico::SITUACAO_ANDAMENTO,
            ])
            ->whereDate('ordens_servico.data_inicio', '>=', $de)
            ->whereDate('ordens_servico.data_inicio', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

        if ($situacao === OrdemServico::SITUACAO_ABERTA) {
            $query->where('ordens_servico.situacao', OrdemServico::SITUACAO_ABERTA);
        } elseif ($situacao === OrdemServico::SITUACAO_ANDAMENTO) {
            $query->where('ordens_servico.situacao', OrdemServico::SITUACAO_ANDAMENTO);
        }

        if ($somenteAtrasadas) {
            $query->whereNotNull('ordens_servico.previsao_entrega')
                ->whereDate('ordens_servico.previsao_entrega', '<', $hojeSql);
        }

        if ($clienteQ !== '') {
            $like = '%'.$clienteQ.'%';
            $query->where(function (Builder $q) use ($like, $clienteQ): void {
                $q->where('ordens_servico.nome', 'like', $like)
                    ->orWhereHas('cliente', function (Builder $cliente) use ($like, $clienteQ): void {
                        $cliente->where(function (Builder $inner) use ($like, $clienteQ): void {
                            $inner->where('nome_razao', 'like', $like);

                            if (ctype_digit($clienteQ)) {
                                $inner->orWhere('id', (int) $clienteQ)
                                    ->orWhere('codigo', 'like', $like);
                            }
                        });
                    });
            });
        }

        if ($tecnicoId > 0) {
            $query->where('ordens_servico.atendente_id', $tecnicoId);
        }

        return $query;
    }

    private function formatEquipamento(OrdemServico $ordem): string
    {
        $marca = trim((string) ($ordem->descricao ?: $ordem->marca ?: $ordem->marca_veiculo ?: ''));
        $modelo = trim((string) ($ordem->modelo ?: $ordem->modelo_veiculo ?: ''));
        $placa = trim((string) ($ordem->placa ?: $ordem->placa_veiculo ?: ''));

        $nome = match (true) {
            $marca !== '' && $modelo !== '' => $marca.' '.$modelo,
            $marca !== '' => $marca,
            $modelo !== '' => $modelo,
            default => '',
        };

        if ($nome !== '' && $placa !== '') {
            return $nome.' / '.$placa;
        }

        return $nome !== '' ? $nome : $placa;
    }

    private function formatTecnico(?Vendedor $tecnico): string
    {
        if ($tecnico === null) {
            return '';
        }

        $codigo = trim((string) ($tecnico->codigo ?? ''));
        $nome = trim((string) ($tecnico->nome ?? ''));

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' - '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function totalsFromAggregate(array $columns, int $qtdPendentes, int $qtdAtrasadas, float $valorTotal): array
    {
        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'total') {
                $totals[$column] = static::formatMoney($valorTotal);

                continue;
            }

            if (in_array($column, ['dias_aberto', 'dias_atraso'], true)) {
                $totals[$column] = '';

                continue;
            }

            if (! $labelPlaced) {
                $totals[$column] = 'PEND. '.$qtdPendentes.' | ATRAS. '.$qtdAtrasadas;
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = '';
        }

        return $totals;
    }

    /**
     * @return array<string, string>
     */
    private function tecnicoOptions(): array
    {
        $options = ['todos' => 'Todos'];
        $empresaId = ErpContext::currentEmpresaId();

        $query = Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->limit(500);

        if ($empresaId) {
            $query->where(function (Builder $q) use ($empresaId): void {
                $q->whereHas('empresas', fn (Builder $e) => $e->where('empresas.id', (int) $empresaId))
                    ->orWhere('empresa_id', (int) $empresaId);
            });
        }

        foreach ($query->get(['id', 'codigo', 'nome']) as $vendedor) {
            $options[(string) $vendedor->id] = $this->formatTecnico($vendedor);
        }

        return $options;
    }
}
