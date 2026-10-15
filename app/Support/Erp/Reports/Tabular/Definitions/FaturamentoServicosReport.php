<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Models\Vendedor;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Reports\ReportEmpresaScope;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Faturamento comercial de OS (serviços/peças/total do cabeçalho).
 * NFS-e apenas por vínculo real (ordem_servico_id no cabeçalho ou item).
 * Não usa venda espelho, Contas a Receber nem Livro Caixa.
 */
class FaturamentoServicosReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'faturamento-servicos';
    }

    public function title(): string
    {
        return 'FATURAMENTO DE SERVIÇOS';
    }

    public function permission(): string
    {
        return 'ordens_servico.print';
    }

    public function columns(): array
    {
        return [
            'numero' => 'OS',
            'faturamento' => 'FATURAMENTO',
            'empresa' => 'EMPRESA',
            'cliente' => 'CLIENTE',
            'tecnico' => 'TÉCNICO',
            'servicos' => 'SERVIÇOS',
            'pecas' => 'PEÇAS',
            'total' => 'TOTAL',
            'nfse' => 'NFS-e',
            'sit_fiscal' => 'SIT. FISCAL',
        ];
    }

    public function columnWidthWeights(): array
    {
        return [
            'numero' => 4,
            'faturamento' => 8,
            'empresa' => 9,
            'cliente' => 24,
            'tecnico' => 10,
            'servicos' => 8,
            'pecas' => 8,
            'total' => 8,
            'nfse' => 6,
            'sit_fiscal' => 9,
        ];
    }

    public function nowrapColumns(): array
    {
        return ['numero', 'faturamento', 'servicos', 'pecas', 'total', 'nfse', 'sit_fiscal'];
    }

    public function defaultColumns(): array
    {
        return [
            'numero',
            'faturamento',
            'cliente',
            'tecnico',
            'servicos',
            'pecas',
            'total',
            'nfse',
            'sit_fiscal',
        ];
    }

    public function numericColumns(): array
    {
        return ['servicos', 'pecas', 'total'];
    }

    public function filterFields(): array
    {
        return $this->withColumnsField($this->withEmpresaFilter([
            ...$this->periodFilterFields(),
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
            [
                'key' => 'sit_fiscal',
                'label' => 'Situação fiscal',
                'type' => 'select',
                'options' => $this->situacaoFiscalOptions(),
            ],
        ]));
    }

    public function build(Request $request): array
    {
        [$de, $ate] = $this->periodFromRequest($request);
        $columns = $this->columnsForEmpresaScope(
            $this->resolveColumns($request->query('cols')),
            $request,
            after: 'faturamento',
        );
        $clienteQ = trim((string) $request->query('cliente', ''));
        $tecnicoId = (int) $request->query('tecnico', 0);
        $sitFiscal = (string) $request->query('sit_fiscal', 'todos');
        $multi = $this->isMultiEmpresaScope($request);

        $base = $this->filteredOsQuery(
            $request,
            $de->toDateString(),
            $ate->toDateString(),
            $clienteQ,
            $tecnicoId,
        );

        /** @var Collection<int, OrdemServico> $ordens */
        $ordens = (clone $base)
            ->with([
                'cliente:id,nome_razao',
                'atendente:id,codigo,nome',
            ])
            ->select([
                'ordens_servico.id',
                'ordens_servico.empresa_id',
                'ordens_servico.numero',
                'ordens_servico.data_termino',
                'ordens_servico.cliente_id',
                'ordens_servico.atendente_id',
                'ordens_servico.nome',
                'ordens_servico.total_servicos',
                'ordens_servico.total_produtos',
                'ordens_servico.total_geral',
            ])
            ->orderBy('ordens_servico.data_termino')
            ->orderBy('ordens_servico.numero')
            ->orderBy('ordens_servico.id')
            ->get();

        $nfseByOs = $this->preferedNfseByOsId($ordens->pluck('id')->map(fn ($id) => (int) $id)->all());
        $labels = $multi ? ReportEmpresaScope::labelsById() : [];

        $rowsBuilt = [];
        $sumServicos = 0.0;
        $sumPecas = 0.0;
        $sumTotal = 0.0;
        $qtdComNfse = 0;

        foreach ($ordens as $ordem) {
            $osId = (int) $ordem->id;
            $nfse = $nfseByOs[$osId] ?? null;
            $sitCodigo = $nfse !== null
                ? (string) ($nfse->status ?? '')
                : 'sem_nfse';

            if ($sitFiscal !== 'todos' && $sitCodigo !== $sitFiscal) {
                continue;
            }

            $servicos = round((float) ($ordem->total_servicos ?? 0), 2);
            $pecas = round((float) ($ordem->total_produtos ?? 0), 2);
            $total = round((float) ($ordem->total_geral ?? 0), 2);

            $sumServicos += $servicos;
            $sumPecas += $pecas;
            $sumTotal += $total;

            if ($nfse !== null) {
                $qtdComNfse++;
            }

            $numeroNfse = trim((string) ($nfse?->numero_nfse ?? ''));

            $row = [
                'numero' => (string) ($ordem->numero ?? ''),
                'faturamento' => static::formatDate($ordem->data_termino),
                'cliente' => $ordem->clienteNome(),
                'tecnico' => $this->formatTecnico($ordem->atendente) ?: 'SEM TÉCNICO',
                'servicos' => static::formatMoney($servicos),
                'pecas' => static::formatMoney($pecas),
                'total' => static::formatMoney($total),
                'nfse' => $numeroNfse !== '' ? $numeroNfse : '—',
                'sit_fiscal' => $this->situacaoFiscalLabel($sitCodigo),
            ];

            if ($multi) {
                $row['empresa'] = $labels[(int) ($ordem->empresa_id ?? 0)]
                    ?? ReportEmpresaScope::labelEmpresa(null);
            }

            $rowsBuilt[] = $row;
        }

        $qtdOs = count($rowsBuilt);
        $rows = array_slice($rowsBuilt, 0, 5000);
        $limitHint = $qtdOs > 5000
            ? ' (limite de exibição; totais do rodapé incluem todos os filtros)'
            : '';

        $tecnicoLabel = 'TODOS';
        if ($tecnicoId > 0) {
            $tecnico = Vendedor::query()->select(['id', 'codigo', 'nome'])->find($tecnicoId);
            $tecnicoLabel = $this->formatTecnico($tecnico) ?: (string) $tecnicoId;
        }

        $built = $this->result(
            $this->withEmpresaFilterValue([
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'cliente' => $clienteQ,
                'tecnico' => $tecnicoId > 0 ? (string) $tecnicoId : 'todos',
                'sit_fiscal' => $sitFiscal,
                'cols' => $columns,
            ], $request),
            $columns,
            $rows,
            $this->withEmpresaSummary([
                'PERÍODO (DATA FATURAMENTO): '.$this->periodLabel($de, $ate),
                'TÉCNICO: '.mb_strtoupper($tecnicoLabel, 'UTF-8'),
                'SITUAÇÃO FISCAL: '.$this->situacaoFiscalLabel($sitFiscal === 'todos' ? 'todos' : $sitFiscal),
                'OS FATURADAS: '.$qtdOs.$limitHint.' | COM NFS-e: '.$qtdComNfse,
                'VALORES: cabeçalho da OS (líquidos após desconto); não são valores recebidos',
                'NFS-e: vínculo por ordem_servico_id (cabeçalho/item); sem NFS-e continua na lista',
            ], $request),
            withTotals: false,
        );

        $built['totals'] = $this->totalsFromAggregate(
            $columns,
            $sumServicos,
            $sumPecas,
            $sumTotal,
            $qtdOs,
        );

        return $built;
    }

    /**
     * @return Builder<\App\Models\OrdemServico>
     */
    private function filteredOsQuery(
        Request $request,
        string $de,
        string $ate,
        string $clienteQ,
        int $tecnicoId,
    ): Builder {
        $query = OrdemServico::query()
            ->whereIn('ordens_servico.situacao', [
                OrdemServico::SITUACAO_FINALIZADA,
                OrdemServico::SITUACAO_ENTREGUE,
            ])
            ->whereNotNull('ordens_servico.data_termino')
            ->whereDate('ordens_servico.data_termino', '>=', $de)
            ->whereDate('ordens_servico.data_termino', '<=', $ate);

        ReportEmpresaScope::applyToQuery($query, $request, 'ordens_servico.empresa_id');

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

    /**
     * Uma NFS-e por OS (preferência: autorizada → transmitida → demais; maior id).
     * Inclui vínculo por item (importação multi-OS).
     *
     * @param  list<int>  $osIds
     * @return array<int, Nfse>
     */
    private function preferedNfseByOsId(array $osIds): array
    {
        if ($osIds === []) {
            return [];
        }

        /** @var array<int, list<Nfse>> $candidates */
        $candidates = [];

        $headerNfses = Nfse::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->select(['id', 'ordem_servico_id', 'numero_nfse', 'status'])
            ->orderByDesc('id')
            ->get();

        foreach ($headerNfses as $nfse) {
            $osId = (int) $nfse->ordem_servico_id;
            $candidates[$osId][] = $nfse;
        }

        $itemLinks = NfseItem::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->whereNotNull('nfse_id')
            ->select(['id', 'ordem_servico_id', 'nfse_id'])
            ->get();

        $nfseIds = $itemLinks->pluck('nfse_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($nfseIds !== []) {
            $nfsesById = Nfse::query()
                ->whereIn('id', $nfseIds)
                ->select(['id', 'ordem_servico_id', 'numero_nfse', 'status'])
                ->get()
                ->keyBy('id');

            foreach ($itemLinks as $item) {
                $nfse = $nfsesById->get((int) $item->nfse_id);

                if (! $nfse instanceof Nfse) {
                    continue;
                }

                $osId = (int) $item->ordem_servico_id;
                $candidates[$osId][] = $nfse;
            }
        }

        $preferred = [];

        foreach ($candidates as $osId => $list) {
            $best = null;
            $bestScore = PHP_INT_MAX;
            $bestId = -1;

            foreach ($list as $nfse) {
                $score = $this->nfsePriorityScore((string) ($nfse->status ?? ''));
                $id = (int) $nfse->id;

                if ($score < $bestScore || ($score === $bestScore && $id > $bestId)) {
                    $best = $nfse;
                    $bestScore = $score;
                    $bestId = $id;
                }
            }

            if ($best instanceof Nfse) {
                $preferred[$osId] = $best;
            }
        }

        return $preferred;
    }

    private function nfsePriorityScore(string $status): int
    {
        return match ($status) {
            Nfse::STATUS_AUTORIZADA => 0,
            Nfse::STATUS_TRANSMITIDA => 1,
            Nfse::STATUS_CONTINGENCIA => 2,
            Nfse::STATUS_ABERTA => 3,
            Nfse::STATUS_CANCELADA => 4,
            Nfse::STATUS_REJEITADA => 5,
            Nfse::STATUS_SUBSTITUIDA => 6,
            default => 9,
        };
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function totalsFromAggregate(
        array $columns,
        float $servicos,
        float $pecas,
        float $total,
        int $qtdOs,
    ): array {
        $totals = [];
        $labelPlaced = false;

        foreach ($columns as $column) {
            if ($column === 'servicos') {
                $totals[$column] = static::formatMoney($servicos);

                continue;
            }

            if ($column === 'pecas') {
                $totals[$column] = static::formatMoney($pecas);

                continue;
            }

            if ($column === 'total') {
                $totals[$column] = static::formatMoney($total);

                continue;
            }

            if (! $labelPlaced) {
                $totals[$column] = 'TOTAL ('.$qtdOs.' OS)';
                $labelPlaced = true;

                continue;
            }

            $totals[$column] = '';
        }

        return $totals;
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

    /**
     * @return array<string, string>
     */
    private function situacaoFiscalOptions(): array
    {
        $options = [
            'todos' => 'Todas',
            'sem_nfse' => 'Sem NFS-e',
        ];

        foreach (Nfse::statusLabels() as $key => $label) {
            $options[(string) $key] = $label;
        }

        return $options;
    }

    private function situacaoFiscalLabel(string $codigo): string
    {
        if ($codigo === 'todos') {
            return 'TODAS';
        }

        if ($codigo === 'sem_nfse' || $codigo === '') {
            return 'SEM NFS-e';
        }

        return mb_strtoupper(Nfse::statusLabels()[$codigo] ?? $codigo, 'UTF-8');
    }
}
